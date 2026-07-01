FROM python:3.10-slim

# Install system dependencies, including ffmpeg, curl, and unzip (needed for Deno installer)
RUN apt-get update && apt-get install -y \
    ffmpeg \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Deno (extremely robust JS/signature challenge solver for yt-dlp)
RUN curl -fsSL https://deno.land/x/install/install.sh | sh
ENV PATH="/root/.deno/bin:${PATH}"

WORKDIR /app

# Copy and install python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy all project files
COPY . .

# Create temp directories
RUN mkdir -p temp_downloads cache

# Expose port (must match original port 7860 configured in Railway settings)
ENV PORT=7860
EXPOSE 7860

# Run the app binding to port 7860
CMD ["gunicorn", "--bind", "0.0.0.0:7860", "wsgi:app"]
