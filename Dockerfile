FROM python:3.10-slim

# Install system dependencies, including ffmpeg and nodejs (needed for yt-dlp to solve YouTube JS/signature challenges)
RUN apt-get update && apt-get install -y \
    ffmpeg \
    curl \
    nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy and install python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy all project files
COPY . .

# Expose port (dynamic environment variable PORT)
EXPOSE 5000

# Run the app dynamically binding to the port specified by the hosting platform (Railway/Render)
CMD gunicorn --bind 0.0.0.0:$PORT wsgi:app
