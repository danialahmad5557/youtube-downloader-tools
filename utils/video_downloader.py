import os
import json
import hashlib
import time
import uuid
import threading
import requests
import re
import logging
import glob
import concurrent.futures
from datetime import datetime
import yt_dlp

logger = logging.getLogger(__name__)

# Add local bin folder (where ffmpeg is downloaded on Render) to system PATH
local_bin = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'bin')
if os.path.exists(local_bin):
    os.environ["PATH"] = local_bin + os.pathsep + os.environ.get("PATH", "")


CACHE_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'cache')
CACHE_TTL = 3600

YT_REGEX = re.compile(
    r'(?:https?:\/\/)?'
    r'(?:www\.|m\.)?'
    r'(?:youtube\.com|youtu\.be)\/'
    r'(?:watch\?v=|embed\/|shorts\/|v\/|.+\?v=)?'
    r'([a-zA-Z0-9_-]{11})(?![a-zA-Z0-9_-])'
)

SIZE_ESTIMATES = {
    '1080p': (20 * 1024 * 1024),
    '720p': (10 * 1024 * 1024),
    '480p': (5 * 1024 * 1024),
    '360p': (2.5 * 1024 * 1024),
    'mp3': (1.5 * 1024 * 1024),
}

download_tasks = {}
download_tasks_lock = threading.Lock()

def _cache_key(url):
    return hashlib.md5(url.encode()).hexdigest()

def _get_cached(key):
    path = os.path.join(CACHE_DIR, f"{key}.json")
    if not os.path.exists(path):
        return None
    try:
        with open(path, 'r') as f:
            data = json.load(f)
        if time.time() - data.get('_cached_at', 0) > CACHE_TTL:
            os.remove(path)
            return None
        return data.get('result')
    except Exception:
        return None

def _set_cache(key, result):
    os.makedirs(CACHE_DIR, exist_ok=True)
    path = os.path.join(CACHE_DIR, f"{key}.json")
    try:
        with open(path, 'w') as f:
            json.dump({'_cached_at': time.time(), 'result': result}, f)
    except Exception:
        pass

def extract_video_id(url):
    if not url:
        return None
    url = url.strip()
    if len(url) == 11 and re.match(r'^[a-zA-Z0-9_-]{11}$', url):
        return url
    m = YT_REGEX.search(url)
    return m.group(1) if m else None

def fetch_oembed(video_id):
    url = f"https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={video_id}&format=json"
    headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
    try:
        r = requests.get(url, headers=headers, timeout=5)
        if r.status_code == 200:
            d = r.json()
            return {
                'title': d.get('title', 'YouTube Video'),
                'channel_name': d.get('author_name', 'YouTube'),
                'channel_url': d.get('author_url', ''),
                'thumbnail': d.get('thumbnail_url', ''),
            }
    except Exception:
        pass
    return None

def format_duration(seconds):
    if not seconds:
        return "Unknown"
    seconds = int(seconds)
    h = seconds // 3600
    m = (seconds % 3600) // 60
    s = seconds % 60
    if h > 0:
        return f"{h:02d}:{m:02d}:{s:02d}"
    return f"{m:02d}:{s:02d}"

def format_views(views):
    if not views:
        return "Unknown"
    views = int(views)
    if views >= 1000000000:
        return f"{views / 1000000000:.1f}B views"
    elif views >= 1000000:
        return f"{views / 1000000:.1f}M views"
    elif views >= 1000:
        return f"{views / 1000:.1f}K views"
    return f"{views} views"

def format_date(date_str):
    if not date_str or len(date_str) != 8:
        return "Unknown"
    try:
        dt = datetime.strptime(date_str, "%Y%m%d")
        return dt.strftime("%b %d, %Y")
    except Exception:
        return date_str

def _get_ydl_opts_base():
    """Return base yt-dlp options that work reliably on cloud servers."""
    cache_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'cache', 'yt-dlp')
    return {
        'quiet': True,
        'no_warnings': True,
        'extractor_retries': 3,
        'socket_timeout': 20,
        'remote_components': ['ejs:github'],
        'cache_dir': cache_path,
        'http_headers': {
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
            'Accept-Language': 'en-US,en;q=0.9',
        },
        'extractor_args': {
            'youtube': {
                'player_client': ['tv', 'web_safari', 'android', 'ios']
            }
        }
    }

def try_ytdlp_flat(url):
    try:
        ydl_opts = _get_ydl_opts_base()
        ydl_opts.update({
            'extract_flat': True,
            'skip_download': True,
        })
        with concurrent.futures.ThreadPoolExecutor(max_workers=1) as ex:
            f = ex.submit(lambda: yt_dlp.YoutubeDL(ydl_opts).extract_info(url, download=False))
            info = f.result(timeout=20)
            return info
    except Exception as e:
        logger.warning(f"try_ytdlp_flat failed: {e}")
        return None

def get_video_info(url):
    video_id = extract_video_id(url)
    if not video_id:
        return {'success': False, 'error': 'Invalid YouTube URL'}

    ckey = _cache_key(url)
    cached = _get_cached(ckey)
    if cached:
        return cached

    meta = fetch_oembed(video_id)
    if not meta:
        return {'success': False, 'error': 'Could not fetch video info'}

    duration = None
    views = None
    upload_date = None

    flat = try_ytdlp_flat(url)
    if flat:
        duration = flat.get('duration')
        views = flat.get('view_count')
        upload_date = flat.get('upload_date')
        if flat.get('title'):
            meta['title'] = flat['title']
        if flat.get('channel') or flat.get('uploader'):
            meta['channel_name'] = flat.get('channel') or flat.get('uploader')

    duration_sec = int(duration) if duration else 120

    options = []
    # Use format selectors that prefer native MP4+M4A to avoid re-encoding
    res_data = [
        ('1080p', '1080p Full HD', 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=1080]+bestaudio/best[height<=1080]/best', 'mp4'),
        ('720p', '720p HD', 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=720]+bestaudio/best[height<=720]/best', 'mp4'),
        ('480p', '480p SD', 'bestvideo[height<=480][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=480]+bestaudio/best[height<=480]/best', 'mp4'),
        ('360p', '360p Medium', 'bestvideo[height<=360][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=360]+bestaudio/best[height<=360]/best', 'mp4'),
        ('mp3', 'Audio Only (MP3)', 'bestaudio/best', 'mp3'),
    ]
    for key, name, selector, ext in res_data:
        est = SIZE_ESTIMATES.get(key, 5 * 1024 * 1024)
        size = int(est * (duration_sec / 60))
        options.append({
            'key': key, 'name': name,
            'format_selector': selector, 'ext': ext,
            'size_bytes': max(size, 1024 * 1024),
        })

    result = {
        'success': True,
        'video_id': video_id,
        'title': meta['title'],
        'channel_name': meta['channel_name'],
        'channel_url': meta.get('channel_url', ''),
        'duration': format_duration(duration),
        'views': format_views(views),
        'upload_date': format_date(upload_date),
        'thumbnail': meta['thumbnail'] or f"https://img.youtube.com/vi/{video_id}/hqdefault.jpg",
        'options': options,
    }

    _set_cache(ckey, result)
    return result

def start_download_task(url, format_selector, ext, temp_dir, title='video'):
    download_id = uuid.uuid4().hex[:12]
    task = {
        'id': download_id,
        'title': title,
        'status': 'starting',
        'progress': 0,
        'progress_text': 'Initializing...',
        'file_path': None,
        'error': None,
        'started_at': time.time(),
    }
    with download_tasks_lock:
        download_tasks[download_id] = task

    def progress_hook(d):
        nonlocal task
        if d['status'] == 'downloading':
            total = d.get('total_bytes') or d.get('total_bytes_estimate', 0)
            downloaded = d.get('downloaded_bytes', 0)
            speed = d.get('speed', 0)
            
            # Check format details to identify video/audio phase
            info = d.get('info_dict', {})
            vcodec = info.get('vcodec', 'none')
            acodec = info.get('acodec', 'none')
            
            phase = "Downloading"
            if vcodec != 'none' and acodec == 'none':
                phase = "Downloading video"
            elif vcodec == 'none' and acodec != 'none':
                phase = "Downloading audio"
                
            if total > 0:
                pct = int(downloaded * 100 / total)
                speed_str = f"{speed / 1024 / 1024:.1f} MB/s" if speed else ""
                task['progress'] = min(pct, 99)
                task['progress_text'] = f"{phase}: {downloaded/1024/1024:.1f} / {total/1024/1024:.1f} MB ({pct}%)"
                if speed_str:
                    task['progress_text'] += f" @ {speed_str}"
            else:
                task['progress_text'] = f"{phase}: {downloaded/1024/1024:.1f} MB downloaded"
                if speed and speed > 0:
                    speed_str = f"{speed / 1024 / 1024:.1f} MB/s"
                    task['progress_text'] += f" @ {speed_str}"
        elif d['status'] == 'finished':
            task['progress'] = 99
            task['progress_text'] = 'Merging streams...'

    def run():
        try:
            os.makedirs(temp_dir, exist_ok=True)
            outtmpl = os.path.join(temp_dir, f"{download_id}.%(ext)s")
            task['status'] = 'downloading'

            ydl_opts = _get_ydl_opts_base()
            ydl_opts.update({
                'format': format_selector,
                'outtmpl': outtmpl,
                'merge_output_format': 'mp4' if ext == 'mp4' else None,
                'socket_timeout': 60,
                'retries': 5,
                'fragment_retries': 5,
                'progress_hooks': [progress_hook],
                'noprogress': True,
            })

            # Remove None values
            ydl_opts = {k: v for k, v in ydl_opts.items() if v is not None}

            if ext == 'mp3':
                ydl_opts['postprocessors'] = [{
                    'key': 'FFmpegExtractAudio',
                    'preferredcodec': 'mp3',
                    'preferredquality': '192',
                }]

            logger.info(f"Starting download {download_id}: format={format_selector}, ext={ext}")
            
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                ydl.download([url])

            # Find the downloaded file
            expected = os.path.join(temp_dir, f"{download_id}.{ext}")
            if os.path.exists(expected):
                task['file_path'] = expected
            else:
                # Search for any file with our download_id prefix
                pattern = os.path.join(temp_dir, f"{download_id}.*")
                matches = glob.glob(pattern)
                if matches:
                    # Prefer the expected extension
                    ext_match = [m for m in matches if m.endswith(f".{ext}")]
                    if ext_match:
                        task['file_path'] = ext_match[0]
                    else:
                        task['file_path'] = matches[0]
                else:
                    raise FileNotFoundError(f"Could not locate downloaded file. Expected: {expected}")

            task['status'] = 'completed'
            task['progress'] = 100
            task['progress_text'] = 'Completed!'
            logger.info(f"Download {download_id} completed: {task['file_path']}")
            
        except Exception as e:
            logger.error(f"Download {download_id} failed: {e}")
            task['status'] = 'error'
            task['error'] = str(e)
            task['progress_text'] = f'Error: {str(e)[:100]}'

    thread = threading.Thread(target=run, daemon=True)
    thread.start()
    return download_id

def get_download_task(download_id):
    with download_tasks_lock:
        return download_tasks.get(download_id)

def cleanup_old_tasks():
    now = time.time()
    with download_tasks_lock:
        expired = [k for k, v in download_tasks.items()
                   if v['status'] in ('completed', 'error')
                   and now - v['started_at'] > 300]
        for k in expired:
            fp = download_tasks[k].get('file_path')
            if fp and os.path.exists(fp):
                try:
                    os.remove(fp)
                except Exception:
                    pass
            del download_tasks[k]
