<?php
/**
 * Plugin Name: YT Video Downloader
 * Description: Download YouTube videos in multiple formats (MP4/MP3).
 * Version: 1.1
 * Requires at least: 5.0
 */

define('YT_VIDEO_BACKEND_URL', 'https://youtube-downloader-tools-production.up.railway.app');

function yt_video_enqueue_fa() { 
    wp_enqueue_style('fa-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'); 
}
add_action('wp_enqueue_scripts', 'yt_video_enqueue_fa');

add_shortcode('yt_video_downloader', 'yt_video_downloader_render');
function yt_video_downloader_render($atts) {
    $atts = shortcode_atts(array(
        'backend_url' => YT_VIDEO_BACKEND_URL,
    ), $atts, 'yt_video_downloader');
    $backend_url = esc_url($atts['backend_url']);
    
    ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

.ytvd-wrapper {
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

.ytvd-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 20px;
}

.ytvd-header i {
    font-size: 32px;
    color: #ff2a2a;
    text-shadow: 0 0 10px rgba(255, 42, 42, 0.3);
}

.ytvd-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.ytvd-header span {
    background: linear-gradient(135deg, #ff2a2a, #ff7a00);
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ytvd-input-group {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.ytvd-input-group input {
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

.ytvd-input-group input:focus {
    border-color: #ff2a2a;
    box-shadow: 0 0 0 3px rgba(255, 42, 42, 0.2);
    background: #1f1f1f;
}

.ytvd-input-group input::placeholder {
    color: #777777;
}

.ytvd-btn {
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

.ytvd-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 42, 42, 0.35);
}

.ytvd-btn:active {
    transform: translateY(0);
}

.ytvd-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.ytvd-loading {
    display: none;
    text-align: center;
    padding: 40px 20px;
}

.ytvd-loading i {
    font-size: 36px;
    color: #ff2a2a;
    animation: ytvd-spin 1.2s linear infinite;
}

@keyframes ytvd-spin {
    to { transform: rotate(360deg); }
}

.ytvd-loading p {
    margin-top: 16px;
    color: #aaaaaa;
    font-size: 15px;
}

.ytvd-results {
    display: none;
    animation: ytvd-fadeIn 0.4s ease;
}

@keyframes ytvd-fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.ytvd-preview {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
    background: #181818;
    padding: 20px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.ytvd-preview img {
    width: 220px;
    border-radius: 10px;
    flex-shrink: 0;
    object-fit: cover;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.ytvd-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.ytvd-info h2 {
    font-size: 18px;
    margin: 0 0 10px 0;
    line-height: 1.4;
    color: #ffffff;
    font-weight: 600;
}

.ytvd-info p {
    color: #aaaaaa;
    font-size: 14px;
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ytvd-info p i {
    width: 18px;
    color: #ff2a2a;
}

.ytvd-table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    background: #181818;
}

.ytvd-table {
    width: 100%;
    border-collapse: collapse;
}

.ytvd-table th, 
.ytvd-table td {
    text-align: left;
    padding: 14px 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    font-size: 14px;
}

.ytvd-table th {
    color: #777777;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: rgba(255, 255, 255, 0.02);
}

.ytvd-table tr:last-child td {
    border-bottom: none;
}

.ytvd-table td {
    color: #ffffff;
}

.ytvd-download-link {
    color: #ff2a2a;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s ease;
}

.ytvd-download-link:hover {
    color: #ff7a00;
    text-decoration: underline;
}

.ytvd-error-msg {
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

.ytvd-toast {
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
    animation: ytvd-toast-fade 0.3s ease;
}

@keyframes ytvd-toast-fade {
    from { opacity: 0; transform: translate(-50%, 10px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}

.ytvd-progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    margin-top: 14px;
    overflow: hidden;
}

.ytvd-progress-fill {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #ff2a2a, #ff7a00);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.ytvd-progress-text {
    font-size: 12px;
    color: #777777;
    margin-top: 8px;
}
</style>

<div class="ytvd-wrapper">
    <div class="ytvd-header">
        <i class="fa-brands fa-youtube"></i>
        <h1>Video Downloader</h1>
        <span>PRO</span>
    </div>
    
    <div class="ytvd-input-group">
        <input type="text" id="ytvd-url-input" placeholder="Paste YouTube video link here..." />
        <button class="ytvd-btn" id="ytvd-fetch-btn">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Fetch
        </button>
    </div>
    
    <div class="ytvd-error-msg" id="ytvd-error-msg"></div>
    
    <div class="ytvd-loading" id="ytvd-loading">
        <i class="fa-solid fa-spinner"></i>
        <p>Fetching video details from YouTube...</p>
    </div>
    
    <div class="ytvd-results" id="ytvd-results">
        <div class="ytvd-preview">
            <img id="ytvd-thumb-img" src="" alt="Video thumbnail">
            <div class="ytvd-info">
                <h2 id="ytvd-video-title">-</h2>
                <p><i class="fa-brands fa-youtube"></i> <span id="ytvd-channel-name">-</span></p>
                <p><i class="fa-regular fa-clock"></i> <span id="ytvd-duration">-</span></p>
                <p><i class="fa-regular fa-eye"></i> <span id="ytvd-views">-</span></p>
            </div>
        </div>
        
        <div class="ytvd-table-wrapper">
            <table class="ytvd-table">
                <thead>
                    <tr>
                        <th>Quality / Format</th>
                        <th>File Extension</th>
                        <th>Estimated Size</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ytvd-formats-tbody">
                    <!-- Formats injected dynamically -->
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="ytvd-loading" id="ytvd-dl-loading" style="display:none">
        <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 32px; color: #ff2a2a;"></i>
        <p id="ytvd-dl-status" style="margin-top: 14px;">Preparing download...</p>
        <div class="ytvd-progress-bar">
            <div class="ytvd-progress-fill" id="ytvd-progress-fill"></div>
        </div>
        <p id="ytvd-progress-text" class="ytvd-progress-text"></p>
    </div>
</div>

<div class="ytvd-toast" id="ytvd-toast"></div>

<script>
(function() {
    const BACKEND = '<?php echo $backend_url; ?>';
    let activePoll = null;
    let isDownloadingTask = false;
    
    function showToast(msg, type) {
        const t = document.getElementById('ytvd-toast');
        t.textContent = msg;
        t.style.background = type === 'error' ? 'rgba(204, 0, 0, 0.95)' : 'rgba(30, 30, 30, 0.95)';
        t.style.borderColor = type === 'error' ? '#ff4444' : 'rgba(255, 255, 255, 0.1)';
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
    
    document.getElementById('ytvd-fetch-btn').addEventListener('click', fetchVideo);
    document.getElementById('ytvd-url-input').addEventListener('keydown', e => {
        if (e.key === 'Enter') fetchVideo();
    });
    
    async function fetchVideo() {
        if (isDownloadingTask) {
            return showToast('Please wait for the current download to finish', 'error');
        }
        
        const url = document.getElementById('ytvd-url-input').value.trim();
        if (!url) {
            return showToast('Please enter a YouTube link', 'error');
        }
        
        document.getElementById('ytvd-loading').style.display = 'block';
        document.getElementById('ytvd-results').style.display = 'none';
        document.getElementById('ytvd-error-msg').style.display = 'none';
        document.getElementById('ytvd-dl-loading').style.display = 'none';
        
        try {
            const res = await fetch(BACKEND + '/api/video/info', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({url})
            });
            const data = await res.json();
            document.getElementById('ytvd-loading').style.display = 'none';
            
            if (!data.success) {
                document.getElementById('ytvd-error-msg').textContent = data.error || 'Failed to extract video info.';
                document.getElementById('ytvd-error-msg').style.display = 'flex';
                return;
            }
            renderVideo(data);
        } catch (e) {
            document.getElementById('ytvd-loading').style.display = 'none';
            document.getElementById('ytvd-error-msg').textContent = 'Network error: Unable to connect to the backend API.';
            document.getElementById('ytvd-error-msg').style.display = 'flex';
        }
    }
    
    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '-';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    function renderVideo(data) {
        document.getElementById('ytvd-thumb-img').src = data.thumbnail || 'https://img.youtube.com/vi/' + data.video_id + '/hqdefault.jpg';
        document.getElementById('ytvd-video-title').textContent = data.title;
        document.getElementById('ytvd-channel-name').textContent = data.channel_name;
        document.getElementById('ytvd-duration').textContent = data.duration;
        document.getElementById('ytvd-views').textContent = data.views;
        
        const tbody = document.getElementById('ytvd-formats-tbody');
        tbody.innerHTML = '';
        
        data.options.forEach((opt, idx) => {
            const tr = document.createElement('tr');
            let icon = 'fa-solid fa-video';
            if (opt.key === 'mp3') icon = 'fa-solid fa-music';
            
            tr.innerHTML = `
                <td>
                    <i class="${icon}" style="margin-right: 8px; color: #ff2a2a;"></i>
                    ${opt.name}
                </td>
                <td>${opt.ext.toUpperCase()}</td>
                <td>${formatBytes(opt.size_bytes)}</td>
                <td>
                    <button class="ytvd-download-link" data-idx="${idx}">
                        <i class="fa-solid fa-download"></i> Download
                    </button>
                </td>
            `;
            
            tr.querySelector('.ytvd-download-link').addEventListener('click', () => {
                downloadVideo(opt.format_selector, opt.ext, data.title);
            });
            tbody.appendChild(tr);
        });
        
        document.getElementById('ytvd-results').style.display = 'block';
    }
    
    function downloadVideo(format, ext, title) {
        if (isDownloadingTask) {
            return showToast('A download is already in progress. Please wait.', 'error');
        }
        
        const url = document.getElementById('ytvd-url-input').value.trim();
        isDownloadingTask = true;
        
        const dlLoading = document.getElementById('ytvd-dl-loading');
        const dlStatus = document.getElementById('ytvd-dl-status');
        const progressFill = document.getElementById('ytvd-progress-fill');
        const progressText = document.getElementById('ytvd-progress-text');
        
        dlLoading.style.display = 'block';
        dlStatus.textContent = 'Contacting server...';
        progressFill.style.width = '0%';
        progressText.textContent = '';
        
        if (activePoll) clearInterval(activePoll);
        
        fetch(BACKEND + '/api/video/download/start', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({url, format, ext, title})
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                isDownloadingTask = false;
                throw new Error(data.error || 'Failed to start download task.');
            }
            
            const did = data.download_id;
            activePoll = setInterval(() => {
                fetch(BACKEND + '/api/video/download/status/' + did)
                .then(r => r.json())
                .then(s => {
                    if (!s.success) {
                        clearInterval(activePoll);
                        isDownloadingTask = false;
                        throw new Error(s.error || 'Task polling failed.');
                    }
                    
                    if (s.status === 'downloading') {
                        dlStatus.textContent = 'Downloading to server...';
                    } else if (s.status === 'starting') {
                        dlStatus.textContent = 'Initializing download...';
                    } else {
                        dlStatus.textContent = s.status;
                    }
                    
                    progressFill.style.width = s.progress + '%';
                    progressText.textContent = s.progress_text || '';
                    
                    if (s.status === 'completed') {
                        clearInterval(activePoll);
                        isDownloadingTask = false;
                        dlStatus.textContent = 'Download finished! Saving to your device...';
                        progressFill.style.width = '100%';
                        
                        const a = document.createElement('a');
                        a.href = BACKEND + '/api/video/download/file/' + did;
                        a.download = title.replace(/[^a-z0-9]/gi, '_').substring(0, 50) + '.' + ext;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        
                        setTimeout(() => dlLoading.style.display = 'none', 3000);
                    } else if (s.status === 'error') {
                        clearInterval(activePoll);
                        isDownloadingTask = false;
                        dlLoading.style.display = 'none';
                        showToast(s.error || 'Download failed on backend server.', 'error');
                    }
                })
                .catch(e => {
                    clearInterval(activePoll);
                    isDownloadingTask = false;
                    dlLoading.style.display = 'none';
                    showToast('Polling error: ' + e.message, 'error');
                });
            }, 1000);
        })
        .catch(e => {
            isDownloadingTask = false;
            dlLoading.style.display = 'none';
            showToast('Download start failed: ' + e.message, 'error');
        });
    }
})();
</script>
<?php 
    return ob_get_clean();
}
