#!/usr/bin/env bash
# exit on error
set -o errexit

pip install -r requirements.txt

# Download ffmpeg static binary if on Render and not exists
if [ -n "$RENDER" ]; then
  mkdir -p bin
  if [ ! -f "bin/ffmpeg" ]; then
    echo "Downloading static ffmpeg for Render..."
    curl -L https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o ffmpeg.tar.xz
    tar -xf ffmpeg.tar.xz
    mv ffmpeg-*-amd64-static/ffmpeg bin/
    mv ffmpeg-*-amd64-static/ffprobe bin/
    rm -rf ffmpeg-*-amd64-static ffmpeg.tar.xz
    chmod +x bin/ffmpeg bin/ffprobe
    echo "ffmpeg installed in bin/"
  fi
fi
