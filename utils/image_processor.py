import io
import re
import requests
import zipfile
from PIL import Image
from concurrent.futures import ThreadPoolExecutor

def download_image(url):
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    }
    try:
        response = requests.get(url, headers=headers, timeout=10)
        if response.status_code == 200:
            return response.content
    except Exception:
        pass
    return None

def analyze_image(image_bytes, quality_key):
    try:
        img = Image.open(io.BytesIO(image_bytes))
        width, height = img.size
        img_format = img.format or 'JPEG'

        if img.mode != 'RGB':
            img = img.convert('RGB')

        img_small = img.resize((100, 100))
        quantized = img_small.quantize(colors=8, method=Image.Quantize.FASTOCTREE)
        colors_list = quantized.getcolors()
        palette = quantized.getpalette()

        dominant_colors = []
        if colors_list and palette:
            colors_list.sort(key=lambda x: x[0], reverse=True)
            for count, idx in colors_list:
                r = palette[idx * 3]
                g = palette[idx * 3 + 1]
                b = palette[idx * 3 + 2]
                hex_color = f"#{r:02x}{g:02x}{b:02x}"
                dominant_colors.append({
                    'hex': hex_color,
                    'rgb': (r, g, b),
                    'count': count,
                    'percentage': round((count / 10000.0) * 100, 1)
                })

        base_scores = {
            'maxresdefault': 85, 'sddefault': 70, 'hqdefault': 55,
            'mqdefault': 40, 'default': 25
        }
        score = base_scores.get(quality_key, 50)

        ratio = width / height
        if abs(ratio - 1.777) < 0.05:
            score += 5
        elif abs(ratio - 1.333) < 0.05:
            score += 2

        if width >= 1280:
            score += 5

        vibrant_count = 0
        for item in dominant_colors:
            r, g, b = item['rgb']
            avg = (r + g + b) / 3.0
            variance = ((r - avg)**2 + (g - avg)**2 + (b - avg)**2) / 3.0
            std_dev = variance ** 0.5
            if std_dev > 18:
                vibrant_count += 1
        if vibrant_count >= 3:
            score += 5

        score = min(100, max(0, score))

        rating = 'Standard'
        if score >= 90:
            rating = '4K/Ultra HD'
        elif score >= 75:
            rating = 'Full HD'
        elif score >= 60:
            rating = 'High Definition'
        elif score >= 45:
            rating = 'Medium Quality'
        else:
            rating = 'Low Quality'

        return {
            'width': width,
            'height': height,
            'format': img_format,
            'dominant_colors': dominant_colors[:6],
            'quality_score': score,
            'quality_rating': rating
        }
    except Exception:
        return {
            'width': 1280,
            'height': 720,
            'format': 'JPEG',
            'dominant_colors': [{'hex': '#2d3748', 'rgb': (45, 55, 72), 'percentage': 100}],
            'quality_score': 50,
            'quality_rating': 'Standard'
        }

def slugify(text):
    text = text.lower()
    text = re.sub(r'[^a-z0-9]', '_', text)
    text = re.sub(r'_+', '_', text)
    return text.strip('_')[:50]

def download_and_name_thumbnail(item):
    video_id = item['video_id']
    url = item['url']
    title = item.get('title', 'thumbnail')
    quality = item.get('quality', 'maxresdefault')

    img_bytes = download_image(url)
    if not img_bytes:
        return None

    safe_title = slugify(title)
    filename = f"{safe_title}_{video_id}_{quality}.jpg"
    return filename, img_bytes

def generate_zip_archive(items):
    zip_buffer = io.BytesIO()

    with ThreadPoolExecutor(max_workers=5) as executor:
        download_results = list(executor.map(download_and_name_thumbnail, items))

    downloaded_files = [res for res in download_results if res is not None]

    if not downloaded_files:
        return None

    with zipfile.ZipFile(zip_buffer, 'w', zipfile.ZIP_DEFLATED) as zip_file:
        for filename, data in downloaded_files:
            zip_file.writestr(filename, data)

    zip_buffer.seek(0)
    return zip_buffer
