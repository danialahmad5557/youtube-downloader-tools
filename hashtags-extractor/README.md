# YouTube Hashtags Extractor

## Deploy Backend to Render
1. Push this folder to a new GitHub repo
2. On Render.com → New Web Service → Connect repo
3. Settings: Runtime = Python, Build Command = `pip install -r requirements.txt`, Start Command = `gunicorn wsgi:app`
4. Deploy → copy the URL (e.g. `https://yt-hashtags.onrender.com`)

## Install WordPress Plugin
1. Upload `yt-hashtags-extractor.php` to `/wp-content/plugins/`
2. Edit the file: change `YT_HASHTAGS_BACKEND_URL` to your Render backend URL
3. Activate plugin in WordPress admin
4. Add shortcode `[yt_hashtags_extractor]` to any page

## API Endpoints
- `POST /api/hashtags` - Extract hashtags from a YouTube video
