# YouTube Video Downloader

## Deploy Backend to Render
1. Push this folder to a new GitHub repo
2. On Render.com → New Web Service → Connect repo
3. Settings: Runtime = Python, Build Command = `pip install -r requirements.txt`, Start Command = `gunicorn wsgi:app`
4. Deploy → copy the URL (e.g. `https://yt-video.onrender.com`)

## Install WordPress Plugin
1. Upload `yt-video-downloader.php` to `/wp-content/plugins/`
2. Edit the file: change `YT_VIDEO_BACKEND_URL` to your Render backend URL
3. Activate plugin in WordPress admin
4. Add shortcode `[yt_video_downloader]` to any page

## API Endpoints
- `POST /api/video/info` - Get video info + available formats
- `GET /api/video/download` - Download video in selected format
