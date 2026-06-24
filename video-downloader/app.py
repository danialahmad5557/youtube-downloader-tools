import logging
import os
import re
from flask import Flask, render_template, request, jsonify, send_file, after_this_request
from flask_cors import CORS
from utils.video_downloader import get_video_info, start_download_task, get_download_task, cleanup_old_tasks

app = Flask(__name__)
CORS(app)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/')
def index():
    return render_template('index.html')

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
    port = int(os.environ.get('PORT', 5002))
    debug = os.environ.get('FLASK_DEBUG', 'false').lower() == 'true'
    app.run(host='0.0.0.0', port=port, debug=debug)
