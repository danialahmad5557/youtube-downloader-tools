import re
import os
import json
import hashlib
import time
import logging
import requests
import yt_dlp

logger = logging.getLogger(__name__)

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

def _extract_video_id(url):
    """Extract video ID from YouTube URL."""
    patterns = [
        r'(?:v=|/v/|youtu\.be/|/embed/)([a-zA-Z0-9_-]{11})',
        r'^([a-zA-Z0-9_-]{11})$',
    ]
    for pat in patterns:
        m = re.search(pat, url)
        if m:
            return m.group(1)
    return None

def _fetch_page_hashtags(video_id):
    """
    Scrape hashtags directly from the YouTube video page HTML.
    This works even when yt-dlp can't get tags due to bot detection.
    """
    page_url = f"https://www.youtube.com/watch?v={video_id}"
    headers = {
        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Accept-Language': 'en-US,en;q=0.9',
    }
    try:
        r = requests.get(page_url, headers=headers, timeout=10)
        if r.status_code == 200:
            text = r.text
            
            # Extract hashtags from above-title hashtag links
            # YouTube renders hashtags above the title as links
            above_title = re.findall(r'"hashtag":"(#[^"]+)"', text)
            
            # Extract from description in the initial data JSON
            desc_hashtags = re.findall(r'#[A-Za-z0-9_\u0600-\u06FF\u0980-\u09FF\u0900-\u097F]+', text)
            
            # Extract keywords/tags from meta
            meta_keywords = re.findall(r'<meta\s+name="keywords"\s+content="([^"]*)"', text)
            keyword_tags = []
            if meta_keywords:
                for kw_str in meta_keywords:
                    for kw in kw_str.split(','):
                        kw = kw.strip()
                        if kw:
                            keyword_tags.append(kw)
            
            return {
                'above_title': above_title,
                'desc_hashtags': desc_hashtags,
                'keyword_tags': keyword_tags,
            }
    except Exception as e:
        logger.warning(f"_fetch_page_hashtags failed: {e}")
    return None

def get_video_hashtags(url):
    ckey = _cache_key(url)
    cached = _get_cached(ckey)
    if cached:
        return cached

    video_id = _extract_video_id(url)
    
    # Try yt-dlp first for metadata
    title = 'Unknown Title'
    channel = 'Unknown Channel'
    thumbnail = ''
    description = ''
    tags = []
    duration = 0
    views = 0

    try:
        ydl_opts = {
            'skip_download': True,
            'quiet': True,
            'no_warnings': True,
            'extractor_retries': 3,
            'ignore_no_formats_error': True,
            'socket_timeout': 20,
            'remote_components': ['ejs:github'],
            'http_headers': {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
                'Accept-Language': 'en-US,en;q=0.9',
            },
            'extractor_args': {
                'youtube': {
                    'player_client': ['web_safari', 'android', 'ios']
                }
            }
        }
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=False)

            title = info.get('title', 'Unknown Title')
            channel = info.get('uploader') or info.get('channel', 'Unknown Channel')
            thumbnail = info.get('thumbnail', '')
            video_id = info.get('id', video_id or '')
            description = info.get('description', '') or ''
            tags = info.get('tags', []) or []
            duration = info.get('duration', 0)
            views = info.get('view_count', 0)
            
            logger.info(f"yt-dlp extracted {len(tags)} tags, description length: {len(description)}")
            
    except Exception as e:
        logger.warning(f"yt-dlp extract failed for hashtags: {e}")
        # Fallback: try oembed for basic info
        if video_id:
            try:
                oembed_url = f"https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={video_id}&format=json"
                r = requests.get(oembed_url, timeout=5)
                if r.status_code == 200:
                    d = r.json()
                    title = d.get('title', title)
                    channel = d.get('author_name', channel)
                    thumbnail = d.get('thumbnail_url', thumbnail)
            except Exception:
                pass

    # Also scrape page directly for additional hashtags
    page_data = None
    if video_id:
        page_data = _fetch_page_hashtags(video_id)
    
    # Build hashtag list from all sources
    hashtags_from_tags = []
    for tag in tags:
        tag_clean = tag.strip()
        if not tag_clean.startswith('#'):
            tag_clean = '#' + tag_clean.replace(' ', '').replace('-', '').replace('_', '')
        hashtags_from_tags.append(tag_clean)

    hashtags_from_desc = extract_hashtags_from_description(description)
    
    # Add hashtags from page scraping
    hashtags_from_page = []
    if page_data:
        for h in page_data.get('above_title', []):
            if h and h.startswith('#'):
                hashtags_from_page.append(h)
        for h in page_data.get('desc_hashtags', []):
            if h and h.startswith('#') and len(h) > 2:
                hashtags_from_page.append(h)
        # Convert keyword tags to hashtags
        for kw in page_data.get('keyword_tags', []):
            kw_tag = '#' + re.sub(r'[^a-zA-Z0-9_]', '', kw)
            if len(kw_tag) > 2:
                hashtags_from_page.append(kw_tag)
    
    all_hashtags = list(dict.fromkeys(hashtags_from_tags + hashtags_from_desc + hashtags_from_page))

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
        'video_id': video_id or '',
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
