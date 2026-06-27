<?php
/**
 * Plugin Name: YT Thumbnail Downloader
 * Description: Download YouTube thumbnails in HD quality with color analysis.
 * Version: 1.1
 * Requires at least: 5.0
 */

define('YT_THUMB_BACKEND_URL', 'https://youtube-downloader-tools-production.up.railway.app');

function yt_thumb_enqueue_fa() { 
    wp_enqueue_style('fa-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'); 
}
add_action('wp_enqueue_scripts', 'yt_thumb_enqueue_fa');

add_shortcode('yt_thumbnail_downloader', 'yt_thumb_downloader_render');
function yt_thumb_downloader_render($atts) {
    $atts = shortcode_atts(array(
        'backend_url' => YT_THUMB_BACKEND_URL,
    ), $atts, 'yt_thumbnail_downloader');
    $backend_url = esc_url($atts['backend_url']);
    
    ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

.yttd-wrapper {
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #ffffff;
    width: 100%;
    max-width: 720px;
    margin: 20px auto;
    background: #121212;
    border-radius: 20px;
    padding: 35px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 0 5px 15px rgba(0, 0, 0, 0.3);
}

.yttd-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 20px;
}

.yttd-header i {
    font-size: 32px;
    color: #ff2a2a;
    text-shadow: 0 0 10px rgba(255, 42, 42, 0.3);
}

.yttd-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.yttd-header span {
    background: linear-gradient(135deg, #ff2a2a, #ff7a00);
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.yttd-input-group {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.yttd-input-group input {
    flex: 1;
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: #181818;
    color: #ffffff;
    font-size: 15px;
    outline: none;
    transition: all 0.3s ease;
}

.yttd-input-group input:focus {
    border-color: #ff2a2a;
    box-shadow: 0 0 0 3px rgba(255, 42, 42, 0.2);
    background: #1f1f1f;
}

.yttd-input-group input::placeholder {
    color: #777777;
}

.yttd-btn {
    padding: 16px 28px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #ff2a2a, #ff7a00);
    color: #ffffff;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(255, 42, 42, 0.2);
}

.yttd-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 42, 42, 0.35);
}

.yttd-btn:active {
    transform: translateY(0);
}

.yttd-btn-secondary {
    background: #222;
    color: #fff;
    padding: 16px 24px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    font-size: 15px;
    transition: all 0.2s ease;
}

.yttd-btn-secondary:hover {
    background: #2a2a2a;
    border-color: rgba(255, 255, 255, 0.2);
}

.yttd-loading {
    display: none;
    text-align: center;
    padding: 40px 20px;
}

.yttd-loading i {
    font-size: 36px;
    color: #ff2a2a;
    animation: yttd-spin 1.2s linear infinite;
}

@keyframes yttd-spin {
    to { transform: rotate(360deg); }
}

.yttd-loading p {
    margin-top: 16px;
    color: #aaaaaa;
    font-size: 15px;
}

.yttd-results {
    display: none;
    animation: yttd-fadeIn 0.4s ease;
}

@keyframes yttd-fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.yttd-preview {
    width: 100%;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
    position: relative;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.yttd-preview img {
    width: 100%;
    display: block;
    object-fit: cover;
}

.yttd-preview-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.yttd-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 24px;
}

.yttd-meta-item {
    background: #181818;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.04);
}

.yttd-meta-item .label {
    font-size: 11px;
    color: #777777;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.yttd-meta-item .value {
    font-size: 15px;
    font-weight: 600;
    margin-top: 6px;
    color: #ffffff;
}

.yttd-qualities {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 24px;
}

.yttd-quality-btn {
    padding: 10px 18px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: #181818;
    color: #ffffff;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.yttd-quality-btn:hover {
    border-color: #ff2a2a;
    background: rgba(255, 42, 42, 0.05);
}

.yttd-quality-btn.yttd-active {
    background: linear-gradient(135deg, #ff2a2a, #ff7a00);
    border-color: transparent;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(255, 42, 42, 0.2);
}

.yttd-download-section {
    display: flex;
    gap: 12px;
}

.yttd-error-msg {
    background: rgba(255, 42, 42, 0.1);
    border: 1px solid rgba(255, 42, 42, 0.3);
    color: #ff6666;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    display: none;
    align-items: center;
    gap: 10px;
}

.yttd-toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: #1e1e1e;
    color: #fff;
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: none;
    z-index: 99999;
    animation: yttd-toast-fade 0.3s ease;
}

@keyframes yttd-toast-fade {
    from { opacity: 0; transform: translate(-50%, 10px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}
</style>

<div class="yttd-wrapper">
    <div class="yttd-header">
        <i class="fa-brands fa-youtube"></i>
        <h1>Thumbnail Downloader</h1>
        <span>PRO</span>
    </div>
    
    <div class="yttd-input-group">
        <input type="text" id="yttd-url-input" placeholder="Paste YouTube link here..." />
        <button class="yttd-btn" id="yttd-fetch-btn">
            <i class="fa-solid fa-download"></i> Fetch
        </button>
    </div>
    
    <div class="yttd-error-msg" id="yttd-error-msg"></div>
    
    <div class="yttd-loading" id="yttd-loading">
        <i class="fa-solid fa-spinner"></i>
        <p>Fetching thumbnail details...</p>
    </div>
    
    <div class="yttd-results" id="yttd-results">
        <div class="yttd-preview">
            <img id="yttd-preview-img" src="" alt="Thumbnail preview">
            <span class="yttd-preview-badge" id="yttd-quality-badge">HD</span>
        </div>
        
        <div class="yttd-meta-grid">
            <div class="yttd-meta-item" style="grid-column: span 2">
                <div class="label">Video Title</div>
                <div class="value" id="yttd-video-title">-</div>
            </div>
            <div class="yttd-meta-item">
                <div class="label">Channel</div>
                <div class="value" id="yttd-channel-name">-</div>
            </div>
            <div class="yttd-meta-item">
                <div class="label">Video ID</div>
                <div class="value" id="yttd-video-id">-</div>
            </div>
        </div>
        
        <div class="yttd-qualities" id="yttd-qualities"></div>
        
        <div class="yttd-download-section">
            <button class="yttd-btn" id="yttd-download-btn" style="flex: 1">
                <i class="fa-solid fa-download"></i> Download Thumbnail
            </button>
            <button class="yttd-btn-secondary" id="yttd-copy-btn" title="Copy Thumbnail Image URL">
                <i class="fa-solid fa-copy"></i>
            </button>
        </div>
    </div>
</div>

<div class="yttd-toast" id="yttd-toast"></div>

<script>
(function() {
    const BACKEND = '<?php echo $backend_url; ?>';
    let currentData = null;
    let selectedQuality = 'maxresdefault';
    
    function showToast(msg, type) {
        const t = document.getElementById('yttd-toast');
        t.textContent = msg;
        t.style.background = type === 'error' ? 'rgba(204, 0, 0, 0.95)' : 'rgba(30, 30, 30, 0.95)';
        t.style.borderColor = type === 'error' ? '#ff4444' : 'rgba(255, 255, 255, 0.1)';
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
    
    document.getElementById('yttd-fetch-btn').addEventListener('click', fetchThumbnail);
    document.getElementById('yttd-url-input').addEventListener('keydown', e => {
        if (e.key === 'Enter') fetchThumbnail();
    });
    
    async function fetchThumbnail() {
        const url = document.getElementById('yttd-url-input').value.trim();
        if (!url) {
            return showToast('Please enter a YouTube link', 'error');
        }
        
        document.getElementById('yttd-loading').style.display = 'block';
        document.getElementById('yttd-results').style.display = 'none';
        document.getElementById('yttd-error-msg').style.display = 'none';
        
        try {
            const res = await fetch(BACKEND + '/api/fetch', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({url})
            });
            const data = await res.json();
            document.getElementById('yttd-loading').style.display = 'none';
            
            if (!res.ok || !data.success) {
                document.getElementById('yttd-error-msg').textContent = data.error || 'Failed to extract thumbnail details.';
                document.getElementById('yttd-error-msg').style.display = 'flex';
                return;
            }
            
            currentData = data;
            selectedQuality = data.highest_quality_key;
            renderResults(data);
        } catch (e) {
            document.getElementById('yttd-loading').style.display = 'none';
            document.getElementById('yttd-error-msg').textContent = 'Network error: Unable to connect to the backend API.';
            document.getElementById('yttd-error-msg').style.display = 'flex';
        }
    }
    
    function renderResults(data) {
        const thumb = data.thumbnails[selectedQuality];
        document.getElementById('yttd-preview-img').src = thumb.url;
        document.getElementById('yttd-quality-badge').textContent = thumb.label;
        document.getElementById('yttd-video-title').textContent = data.title;
        document.getElementById('yttd-channel-name').textContent = data.channel_name;
        document.getElementById('yttd-video-id').textContent = data.video_id;
        
        const container = document.getElementById('yttd-qualities');
        container.innerHTML = '';
        
        const order = ['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault', 'default'];
        order.forEach(key => {
            if (!data.thumbnails[key]) return;
            const btn = document.createElement('button');
            btn.className = 'yttd-quality-btn' + (key === selectedQuality ? ' yttd-active' : '');
            btn.textContent = data.thumbnails[key].label;
            btn.onclick = () => selectQuality(key);
            container.appendChild(btn);
        });
        
        document.getElementById('yttd-results').style.display = 'block';
    }
    
    function selectQuality(key) {
        if (!currentData || !currentData.thumbnails[key]) return;
        selectedQuality = key;
        const thumb = currentData.thumbnails[key];
        document.getElementById('yttd-preview-img').src = thumb.url;
        document.getElementById('yttd-quality-badge').textContent = thumb.label;
        
        document.querySelectorAll('.yttd-quality-btn').forEach(b => b.classList.remove('yttd-active'));
        document.querySelectorAll('.yttd-quality-btn').forEach(b => {
            if (b.textContent === thumb.label) b.classList.add('yttd-active');
        });
    }
    
    document.getElementById('yttd-download-btn').addEventListener('click', () => {
        if (!currentData) return;
        window.open(BACKEND + '/api/download?video_id=' + currentData.video_id + '&quality=' + selectedQuality + '&title=' + encodeURIComponent(currentData.title), '_blank');
    });
    
    document.getElementById('yttd-copy-btn').addEventListener('click', () => {
        if (!currentData || !currentData.thumbnails[selectedQuality]) return;
        navigator.clipboard.writeText(currentData.thumbnails[selectedQuality].url);
        showToast('Thumbnail URL copied to clipboard!');
    });
})();
</script>
<?php 
    return ob_get_clean();
}
