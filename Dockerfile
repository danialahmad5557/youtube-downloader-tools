FROM python:3.10-slim

# Install system dependencies
# - ffmpeg: for merging video/audio streams
# - curl: for downloading files  
# - nodejs + npm: for yt-dlp JavaScript signature challenge solving
RUN apt-get update && apt-get install -y \
    ffmpeg \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy and install python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy all project files
COPY . .

# Create temp directories
RUN mkdir -p temp_downloads cache

# Expose port (dynamic environment variable PORT)
EXPOSE 5000

# Run with gunicorn - increase timeout to 300s for large downloads
CMD gunicorn --bind 0.0.0.0:$PORT --timeout 300 --workers 2 --threads 4 wsgi:app
