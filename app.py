import io
import logging
import os
import re
import time
from flask import Flask, render_template, request, jsonify, send_file, after_this_request
from flask_cors import CORS

# Import utility functions from root-level utils
from utils.youtube import extract_video_id, fetch_video_metadata, get_available_thumbnails
from utils.image_processor import download_image, analyze_image, generate_zip_archive, slugify
from utils.hashtags_extractor import get_video_hashtags
from utils.video_downloader import get_video_info, start_download_task, get_download_task, cleanup_old_tasks

app = Flask(__name__)
CORS(app)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/')
def index():
    cookies_txt_exists = os.path.exists('cookies.txt')
    cookies_txt_size = os.path.getsize('cookies.txt') if cookies_txt_exists else 0
    
    from utils.video_downloader import get_cookie_options
    cookie_opts = get_cookie_options()
    
    node_version = "Not Found"
    try:
        import subprocess
        node_version = subprocess.check_output(['node', '-v'], text=True).strip()
    except Exception as e:
        node_version = f"Error: {e}"
        
    return jsonify({
        'status': 'online',
        'cookies_debug': {
            'cookies_txt_exists': cookies_txt_exists,
            'cookies_txt_size': cookies_txt_size,
            'resolved_cookie_file': cookie_opts.get('cookiefile'),
            'resolved_cookies_from_browser': cookie_opts.get('cookiesfrombrowser') is not None
        },
        'node_debug': {
            'node_version': node_version
        },
        'message': 'Combined YouTube Downloader and Tools API is running.',
        'endpoints': {
            'thumbnail_fetch': '/api/fetch [POST]',
            'thumbnail_fetch_bulk': '/api/fetch_bulk [POST]',
            'thumbnail_download': '/api/download [GET]',
            'thumbnail_download_zip': '/api/download_zip [POST]',
            'hashtags_extract': '/api/hashtags [POST]',
            'video_info': '/api/video/info [POST]',
            'video_download_start': '/api/video/download/start [POST]',
            'video_download_status': '/api/video/download/status/<download_id> [GET]',
            'video_download_file': '/api/video/download/file/<download_id> [GET]'
        }
    })

@app.route('/api/debug/extract', methods=['POST'])
def debug_extract():
    data = request.get_json() or {}
    url = data.get('url')
    if not url:
        return jsonify({'error': 'No URL provided'}), 400
        
    import io
    from contextlib import redirect_stdout, redirect_stderr
    import yt_dlp
    from utils.video_downloader import _get_ydl_opts_base
    
    ydl_opts = _get_ydl_opts_base()
    ydl_opts['quiet'] = False
    ydl_opts['no_warnings'] = False
    
    f_stdout = io.StringIO()
    f_stderr = io.StringIO()
    
    error_str = None
    info_dict = None
    
    with redirect_stdout(f_stdout), redirect_stderr(f_stderr):
        try:
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                info_dict = ydl.extract_info(url, download=False)
        except Exception as e:
            error_str = str(e)
            
    return jsonify({
        'success': error_str is None,
        'error': error_str,
        'stdout': f_stdout.getvalue(),
        'stderr': f_stderr.getvalue(),
        'title': info_dict.get('title') if info_dict else None
    })

# ==========================================
# THUMBNAIL DOWNLOADER ENDPOINTS
# ==========================================

@app.route('/api/fetch', methods=['POST'])
def fetch_thumbnail_data():
    data = request.get_json() or {}
    url = data.get('url')

    if not url:
        return jsonify({'success': False, 'error': 'No URL provided'}), 400

    video_id = extract_video_id(url)
    if not video_id:
        return jsonify({'success': False, 'error': 'Invalid YouTube URL'}), 400

    try:
        metadata = fetch_video_metadata(video_id)
        thumbnails = get_available_thumbnails(video_id)
        if not thumbnails:
            return jsonify({'success': False, 'error': 'No thumbnails found'}), 404

        quality_order = ['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault', 'default']
        highest_quality_key = 'default'
        for q in quality_order:
            if q in thumbnails:
                highest_quality_key = q
                break

        highest_thumb_url = thumbnails[highest_quality_key]['url']
        image_bytes = download_image(highest_thumb_url)

        analysis = None
        if image_bytes:
            analysis = analyze_image(image_bytes, highest_quality_key)
            thumbnails[highest_quality_key]['size_bytes'] = len(image_bytes)

        return jsonify({
            'success': True,
            'video_id': video_id,
            'title': metadata['title'],
            'channel_name': metadata['channel_name'],
            'channel_url': metadata['channel_url'],
            'thumbnails': thumbnails,
            'highest_quality_key': highest_quality_key,
            'analysis': analysis
        })

    except Exception as e:
        logger.error(f"Error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/fetch_bulk', methods=['POST'])
def fetch_bulk_thumbnails():
    data = request.get_json() or {}
    urls = data.get('urls', [])

    if not urls or not isinstance(urls, list):
        return jsonify({'success': False, 'error': 'Invalid URLs list'}), 400

    urls = urls[:30]
    results = []

    for url in urls:
        if not url or not url.strip():
            continue

        video_id = extract_video_id(url)
        if not video_id:
            results.append({'url': url, 'success': False, 'error': 'Invalid URL'})
            continue

        try:
            metadata = fetch_video_metadata(video_id)
            thumbnails = get_available_thumbnails(video_id)

            quality_order = ['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault', 'default']
            highest_quality_key = 'default'
            for q in quality_order:
                if q in thumbnails:
                    highest_quality_key = q
                    break

            results.append({
                'url': url, 'video_id': video_id, 'success': True,
                'title': metadata['title'], 'channel_name': metadata['channel_name'],
                'thumbnails': thumbnails, 'highest_quality_key': highest_quality_key,
                'highest_url': thumbnails[highest_quality_key]['url']
            })
        except Exception as e:
            results.append({'url': url, 'success': False, 'error': str(e)})

    return jsonify({'success': True, 'results': results})

@app.route('/api/download', methods=['GET'])
def download_thumbnail():
    video_id = request.args.get('video_id')
    quality = request.args.get('quality', 'maxresdefault')
    title = request.args.get('title', 'youtube_thumbnail')

    if not video_id:
        return 'Missing video_id', 400

    thumbnail_url = f"https://img.youtube.com/vi/{video_id}/{quality}.jpg"
    img_bytes = download_image(thumbnail_url)

    if not img_bytes:
        fallback_url = f"https://img.youtube.com/vi/{video_id}/hqdefault.jpg"
        img_bytes = download_image(fallback_url)
        if not img_bytes:
            return 'Thumbnail not found', 404
        quality = 'hqdefault'

    safe_title = slugify(title)
    filename = f"{safe_title}_{video_id}_{quality}.jpg"

    return send_file(
        io.BytesIO(img_bytes),
        mimetype='image/jpeg',
        as_attachment=True,
        download_name=filename
    )

@app.route('/api/download_zip', methods=['POST'])
def download_zip():
    data = request.get_json() or {}
    items = data.get('items', [])

    if not items:
        return jsonify({'success': False, 'error': 'No items'}), 400

    try:
        zip_buffer = generate_zip_archive(items)
        if not zip_buffer:
            return jsonify({'success': False, 'error': 'Could not download'}), 404

        return send_file(
            zip_buffer,
            mimetype='application/zip',
            as_attachment=True,
            download_name='yt_thumbnails_pro.zip'
        )
    except Exception as e:
        logger.error(f"ZIP error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

# ==========================================
# HASHTAGS EXTRACTOR ENDPOINTS
# ==========================================

@app.route('/api/hashtags', methods=['POST'])
def hashtags_extract():
    data = request.get_json() or {}
    url = data.get('url')

    if not url:
        return jsonify({'success': False, 'error': 'No URL provided'}), 400

    try:
        result = get_video_hashtags(url)
        return jsonify(result)
    except Exception as e:
        logger.error(f"Error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

# ==========================================
# VIDEO DOWNLOADER ENDPOINTS
# ==========================================

@app.route('/api/video/info', methods=['POST'])
def video_info():
    data = request.get_json() or {}
    url = data.get('url')

    if not url:
        return jsonify({'success': False, 'error': 'No URL provided'}), 400

    try:
        info = get_video_info(url)
        return jsonify(info)
    except Exception as e:
        logger.error(f"Error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/video/download/start', methods=['POST'])
def download_start():
    data = request.get_json() or {}
    url = data.get('url')
    format_selector = data.get('format')
    ext = data.get('ext', 'mp4')
    title = data.get('title', 'video')

    if not url or not format_selector:
        return jsonify({'success': False, 'error': 'Missing parameters'}), 400

    try:
        # Periodic cleanup of old tasks
        try:
            cleanup_old_tasks()
        except Exception as ex_cleanup:
            logger.error(f"Cleanup error: {str(ex_cleanup)}")

        temp_dir = os.path.join(app.root_path, 'temp_downloads')
        download_id = start_download_task(url, format_selector, ext, temp_dir, title=title)
        return jsonify({'success': True, 'download_id': download_id})
    except Exception as e:
        logger.error(f"Download start error: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/video/download/status/<download_id>')
def download_status(download_id):
    task = get_download_task(download_id)
    if not task:
        return jsonify({'success': False, 'error': 'Download not found'}), 404
    return jsonify({
        'success': True,
        'status': task['status'],
        'progress': task['progress'],
        'progress_text': task.get('progress_text', ''),
        'error': task.get('error'),
    })

@app.route('/api/video/download/file/<download_id>')
def download_file(download_id):
    task = get_download_task(download_id)
    if not task:
        return 'Download not found', 404
    if task['status'] != 'completed':
        return 'Download not ready yet', 400
    if not task['file_path'] or not os.path.exists(task['file_path']):
        return 'File not found', 404

    safe_title = re.sub(r'[^a-z0-9]', '_', task.get('title', 'video').lower()).strip('_')[:50] or 'video'
    filename = f"{safe_title}.{task['file_path'].split('.')[-1]}"

    @after_this_request
    def remove(response):
        try:
            if os.path.exists(task['file_path']):
                os.remove(task['file_path'])
        except Exception:
            pass
        return response

    return send_file(
        task['file_path'],
        as_attachment=True,
        download_name=filename
    )

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    debug = os.environ.get('FLASK_DEBUG', 'false').lower() == 'true'
    app.run(host='0.0.0.0', port=port, debug=debug)
