import re
import requests
from concurrent.futures import ThreadPoolExecutor

YOUTUBE_REGEX = re.compile(
    r'(?:https?:\/\/)?'
    r'(?:www\.|m\.)?'
    r'(?:youtube\.com|youtu\.be)\/'
    r'(?:watch\?v=|embed\/|shorts\/|v\/|.+\?v=)?'
    r'([a-zA-Z0-9_-]{11})(?![a-zA-Z0-9_-])'
)

THUMBNAIL_TEMPLATES = {
    'maxresdefault': {
        'name': 'Maximum Resolution (Ultra HD / 1080p)',
        'key': 'maxresdefault',
        'url': 'https://img.youtube.com/vi/{video_id}/maxresdefault.jpg',
        'badge': '4K/HD Available',
        'quality_score': 95,
        'label': 'Full HD'
    },
    'sddefault': {
        'name': 'Standard Definition (480p)',
        'key': 'sddefault',
        'url': 'https://img.youtube.com/vi/{video_id}/sddefault.jpg',
        'badge': 'HQ Standard',
        'quality_score': 70,
        'label': 'SD'
    },
    'hqdefault': {
        'name': 'High Quality (360p)',
        'key': 'hqdefault',
        'url': 'https://img.youtube.com/vi/{video_id}/hqdefault.jpg',
        'badge': 'Standard',
        'quality_score': 55,
        'label': 'HQ'
    },
    'mqdefault': {
        'name': 'Medium Quality (180p)',
        'key': 'mqdefault',
        'url': 'https://img.youtube.com/vi/{video_id}/mqdefault.jpg',
        'badge': 'Medium',
        'quality_score': 40,
        'label': 'MQ'
    },
    'default': {
        'name': 'Default Quality (90p)',
        'key': 'default',
        'url': 'https://img.youtube.com/vi/{video_id}/default.jpg',
        'badge': 'Low',
        'quality_score': 20,
        'label': 'Default'
    }
}

def extract_video_id(url):
    if not url:
        return None
    url = url.strip()
    if len(url) == 11 and re.match(r'^[a-zA-Z0-9_-]{11}$', url):
        return url
    match = YOUTUBE_REGEX.search(url)
    if match:
        return match.group(1)
    return None

def fetch_video_metadata(video_id):
    url = f"https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={video_id}&format=json"
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    }
    try:
        response = requests.get(url, headers=headers, timeout=5)
        if response.status_code == 200:
            data = response.json()
            return {
                'title': data.get('title', 'Unknown Title'),
                'channel_name': data.get('author_name', 'Unknown Channel'),
                'channel_url': data.get('author_url', ''),
                'video_id': video_id
            }
    except Exception:
        pass
    return {
        'title': f"YouTube Video ({video_id})",
        'channel_name': "YouTube Channel",
        'channel_url': "",
        'video_id': video_id
    }

def check_single_thumbnail(args):
    key, url = args
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    }
    try:
        response = requests.head(url, headers=headers, timeout=3)
        if response.status_code == 200:
            content_length = response.headers.get('Content-Length')
            size_bytes = int(content_length) if content_length else None
            return key, True, size_bytes
    except Exception:
        pass
    return key, False, None

def get_available_thumbnails(video_id):
    check_tasks = []
    for key, item in THUMBNAIL_TEMPLATES.items():
        url = item['url'].format(video_id=video_id)
        check_tasks.append((key, url))

    results = {}
    with ThreadPoolExecutor(max_workers=5) as executor:
        futures = executor.map(check_single_thumbnail, check_tasks)
        for key, exists, size_bytes in futures:
            if exists:
                template = THUMBNAIL_TEMPLATES[key].copy()
                template['url'] = template['url'].format(video_id=video_id)
                template['size_bytes'] = size_bytes
                results[key] = template

    if not results:
        template = THUMBNAIL_TEMPLATES['default'].copy()
        template['url'] = template['url'].format(video_id=video_id)
        template['size_bytes'] = None
        results['default'] = template

    return results
