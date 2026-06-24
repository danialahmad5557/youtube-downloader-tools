import re
import os
import json
import hashlib
import time
import yt_dlp

CACHE_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'cache')
CACHE_TTL = 3600

def _cache_key(url):
    return 'h_' + hashlib.md5(url.encode()).hexdigest()

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

def extract_hashtags_from_description(description):
    if not description:
        return []
    hashtags = re.findall(r'#\w+', description)
    return list(dict.fromkeys(hashtags))

def get_video_hashtags(url):
    ckey = _cache_key(url)
    cached = _get_cached(ckey)
    if cached:
        return cached

    ydl_opts = {
        'skip_download': True,
        'quiet': True,
        'no_warnings': True,
        'extractor_retries': 1,
        'ignore_no_formats_error': True,
        'socket_timeout': 15,
    }
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(url, download=False)

        title = info.get('title', 'Unknown Title')
        channel = info.get('uploader', 'Unknown Channel')
        thumbnail = info.get('thumbnail', '')
        video_id = info.get('id', '')
        description = info.get('description', '') or ''
        tags = info.get('tags', []) or []
        duration = info.get('duration', 0)
        views = info.get('view_count', 0)

        hashtags_from_tags = []
        for tag in tags:
            tag_clean = tag.strip()
            if not tag_clean.startswith('#'):
                tag_clean = '#' + tag_clean.replace(' ', '').replace('-', '').replace('_', '')
            hashtags_from_tags.append(tag_clean)

        hashtags_from_desc = extract_hashtags_from_description(description)
        all_hashtags = list(dict.fromkeys(hashtags_from_tags + hashtags_from_desc))

        freq = {}
        for tag in all_hashtags:
            name = tag.lstrip('#').lower()
            count = 0
            count += sum(1 for t in tags if name in t.lower())
            if description:
                count += len(re.findall(re.escape(tag), description, re.IGNORECASE))
            freq[tag] = max(count, 1)

        sorted_hashtags = sorted(freq.items(), key=lambda x: x[1], reverse=True)

        result = {
            'success': True,
            'video_id': video_id,
            'title': title,
            'channel_name': channel,
            'thumbnail': thumbnail,
            'duration': duration,
            'views': views,
            'hashtags': [{'tag': tag, 'count': count} for tag, count in sorted_hashtags],
            'total_hashtags': len(sorted_hashtags),
            'tags_raw': tags,
        }

        _set_cache(ckey, result)
        return result
