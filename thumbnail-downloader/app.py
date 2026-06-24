import io
import logging
import os
from flask import Flask, render_template, request, jsonify, send_file
from flask_cors import CORS
from utils.youtube import extract_video_id, fetch_video_metadata, get_available_thumbnails
from utils.image_processor import download_image, analyze_image, generate_zip_archive, slugify

app = Flask(__name__)
CORS(app)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/')
def index():
    return render_template('index.html')

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

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5001))
    debug = os.environ.get('FLASK_DEBUG', 'false').lower() == 'true'
    app.run(host='0.0.0.0', port=port, debug=debug)
