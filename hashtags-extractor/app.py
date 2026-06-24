import logging
import os
from flask import Flask, render_template, request, jsonify
from flask_cors import CORS
from utils.hashtags_extractor import get_video_hashtags

app = Flask(__name__)
CORS(app)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/')
def index():
    return render_template('index.html')

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

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5003))
    debug = os.environ.get('FLASK_DEBUG', 'false').lower() == 'true'
    app.run(host='0.0.0.0', port=port, debug=debug)
