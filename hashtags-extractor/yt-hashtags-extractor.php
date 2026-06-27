<?php
/**
 * Plugin Name: YT Hashtags Extractor
 * Description: Extract all hashtags from any YouTube video with frequency sorting.
 * Version: 1.1
 * Requires at least: 5.0
 */

define('YT_HASHTAGS_BACKEND_URL', 'https://youtube-downloader-tools-production.up.railway.app');

function yt_hashtags_enqueue_fa() { 
    wp_enqueue_style('fa-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'); 
}
add_action('wp_enqueue_scripts', 'yt_hashtags_enqueue_fa');

add_shortcode('yt_hashtags_extractor', 'yt_hashtags_extractor_render');
function yt_hashtags_extractor_render($atts) {
    $atts = shortcode_atts(array(
        'backend_url' => YT_HASHTAGS_BACKEND_URL,
    ), $atts, 'yt_hashtags_extractor');
    $backend_url = esc_url($atts['backend_url']);
    
    ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

.ythe-wrapper {
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

.ythe-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 20px;
}

.ythe-header i {
    font-size: 32px;
    color: #ff2a2a;
    text-shadow: 0 0 10px rgba(255, 42, 42, 0.3);
}

.ythe-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.ythe-header span {
    background: linear-gradient(135deg, #ff2a2a, #ff7a00);
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ythe-input-group {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.ythe-input-group input {
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

.ythe-input-group input:focus {
    border-color: #ff2a2a;
    box-shadow: 0 0 0 3px rgba(255, 42, 42, 0.2);
    background: #1f1f1f;
}

.ythe-input-group input::placeholder {
    color: #777777;
}

.ythe-btn {
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

.ythe-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 42, 42, 0.35);
}

.ythe-btn:active {
    transform: translateY(0);
}

.ythe-loading {
    display: none;
    text-align: center;
    padding: 40px 20px;
}

.ythe-loading i {
    font-size: 36px;
    color: #ff2a2a;
    animation: ythe-spin 1.2s linear infinite;
}

@keyframes ythe-spin {
    to { transform: rotate(360deg); }
}

.ythe-loading p {
    margin-top: 16px;
    color: #aaaaaa;
    font-size: 15px;
}

.ythe-results {
    display: none;
    animation: ythe-fadeIn 0.4s ease;
}

@keyframes ythe-fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.ythe-video-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
    background: #181818;
    padding: 20px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.ythe-video-meta img {
    width: 180px;
    border-radius: 10px;
    flex-shrink: 0;
    object-fit: cover;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.ythe-video-meta h3 {
    font-size: 18px;
    margin: 0 0 10px 0;
    line-height: 1.4;
    color: #ffffff;
    font-weight: 600;
}

.ythe-video-meta p {
    color: #aaaaaa;
    font-size: 14px;
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ythe-video-meta p i {
    color: #ff2a2a;
}

.ythe-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding: 14px 20px;
    background: #181818;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.04);
}

.ythe-stats span {
    font-size: 14px;
    color: #aaaaaa;
}

.ythe-stats strong {
    color: #ffffff;
    font-weight: 600;
}

.ythe-hashtag-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 24px;
}

.ythe-hashtag {
    background: #181818;
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 10px 18px;
    border-radius: 30px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 10px;
    color: #ffffff;
}

.ythe-hashtag:hover {
    background: linear-gradient(135deg, #ff2a2a, #ff7a00);
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(255, 42, 42, 0.25);
}

.ythe-hashtag .count {
    background: rgba(255, 255, 255, 0.08);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    color: #aaaaaa;
    transition: all 0.2s ease;
}

.ythe-hashtag:hover .count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

.ythe-error-msg {
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

.ythe-toast {
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
    animation: ythe-toast-fade 0.3s ease;
}

@keyframes ythe-toast-fade {
    from { opacity: 0; transform: translate(-50%, 10px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}
</style>

<div class="ythe-wrapper">
    <div class="ythe-header">
        <i class="fa-solid fa-hashtag"></i>
        <h1>Hashtags Extractor</h1>
        <span>PRO</span>
    </div>
    
    <div class="ythe-input-group">
        <input type="text" id="ythe-url-input" placeholder="Paste YouTube link here..." />
        <button class="ythe-btn" id="ythe-fetch-btn">
            <i class="fa-solid fa-hashtag"></i> Extract
        </button>
    </div>
    
    <div class="ythe-error-msg" id="ythe-error-msg"></div>
    
    <div class="ythe-loading" id="ythe-loading">
        <i class="fa-solid fa-spinner"></i>
        <p>Extracting hashtags from YouTube video...<br><span style="font-size: 12px; color: #777777;">This might take 5-15 seconds</span></p>
    </div>
    
    <div class="ythe-results" id="ythe-results">
        <div class="ythe-video-meta">
            <img id="ythe-thumb-img" src="" alt="Video thumbnail">
            <div>
                <h3 id="ythe-video-title">-</h3>
                <p><i class="fa-brands fa-youtube"></i> <span id="ythe-channel-name">-</span></p>
                <p style="margin-top: 4px;"><i class="fa-regular fa-clock"></i> <span id="ythe-duration">-</span></p>
            </div>
        </div>
        
        <div class="ythe-stats">
            <span>Total Unique Hashtags Found: <strong id="ythe-total-count">0</strong></span>
        </div>
        
        <div class="ythe-hashtag-grid" id="ythe-hashtag-grid"></div>
        
        <button class="ythe-btn" id="ythe-copy-all-btn" style="width: 100%; justify-content: center;">
            <i class="fa-solid fa-copy"></i> Copy All Hashtags
        </button>
    </div>
</div>

<div class="ythe-toast" id="ythe-toast"></div>

<script>
(function() {
    const BACKEND = '<?php echo $backend_url; ?>';
    let currentHashtags = [];
    
    function showToast(msg, type) {
        const t = document.getElementById('ythe-toast');
        t.textContent = msg;
        t.style.background = type === 'error' ? 'rgba(204, 0, 0, 0.95)' : 'rgba(30, 30, 30, 0.95)';
        t.style.borderColor = type === 'error' ? '#ff4444' : 'rgba(255, 255, 255, 0.1)';
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
    
    document.getElementById('ythe-fetch-btn').addEventListener('click', extractHashtags);
    document.getElementById('ythe-url-input').addEventListener('keydown', e => {
        if (e.key === 'Enter') extractHashtags();
    });
    
    async function extractHashtags() {
        const url = document.getElementById('ythe-url-input').value.trim();
        if (!url) {
            return showToast('Please enter a YouTube link', 'error');
        }
        
        document.getElementById('ythe-loading').style.display = 'block';
        document.getElementById('ythe-results').style.display = 'none';
        document.getElementById('ythe-error-msg').style.display = 'none';
        
        try {
            const res = await fetch(BACKEND + '/api/hashtags', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({url})
            });
            const data = await res.json();
            document.getElementById('ythe-loading').style.display = 'none';
            
            if (!data.success) {
                document.getElementById('ythe-error-msg').textContent = data.error || 'Failed to extract hashtags.';
                document.getElementById('ythe-error-msg').style.display = 'flex';
                return;
            }
            renderHashtags(data);
        } catch (e) {
            document.getElementById('ythe-loading').style.display = 'none';
            document.getElementById('ythe-error-msg').textContent = 'Network error: Unable to connect to the backend API.';
            document.getElementById('ythe-error-msg').style.display = 'flex';
        }
    }
    
    function formatDuration(seconds) {
        if (!seconds) return '00:00';
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return m + ':' + String(s).padStart(2, '0');
    }
    
    function renderHashtags(data) {
        document.getElementById('ythe-thumb-img').src = data.thumbnail || 'https://img.youtube.com/vi/' + data.video_id + '/hqdefault.jpg';
        document.getElementById('ythe-video-title').textContent = data.title;
        document.getElementById('ythe-channel-name').textContent = data.channel_name;
        document.getElementById('ythe-duration').textContent = formatDuration(data.duration);
        document.getElementById('ythe-total-count').textContent = data.total_hashtags;
        
        currentHashtags = data.hashtags.map(h => h.tag);
        
        const grid = document.getElementById('ythe-hashtag-grid');
        grid.innerHTML = '';
        
        data.hashtags.forEach(h => {
            const div = document.createElement('div');
            div.className = 'ythe-hashtag';
            div.innerHTML = h.tag + ' <span class="count">' + h.count + '</span>';
            div.title = 'Click to copy ' + h.tag;
            div.onclick = () => {
                navigator.clipboard.writeText(h.tag);
                showToast('Copied ' + h.tag + ' to clipboard!');
            };
            grid.appendChild(div);
        });
        
        document.getElementById('ythe-results').style.display = 'block';
    }
    
    document.getElementById('ythe-copy-all-btn').addEventListener('click', () => {
        if (currentHashtags.length === 0) return;
        navigator.clipboard.writeText(currentHashtags.join(' '));
        showToast('All ' + currentHashtags.length + ' hashtags copied to clipboard!');
    });
})();
</script>
<?php 
    return ob_get_clean();
}
