<?php
/**
 * Plugin Name: YT Thumbnail Downloader
 * Description: Download YouTube thumbnails in HD quality with color analysis.
 * Version: 1.0
 * Requires at least: 5.0
 */

define('YT_THUMB_BACKEND_URL', 'https://yt-thumbnail.onrender.com');

function yt_thumb_enqueue_fa() { wp_enqueue_style('fa-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'); }
add_action('wp_enqueue_scripts', 'yt_thumb_enqueue_fa');

add_shortcode('yt_thumbnail_downloader', 'yt_thumb_downloader_render');
function yt_thumb_downloader_render() {
ob_start(); ?>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f0f0f;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.tool-container{width:100%;max-width:700px;background:#1a1a1a;border-radius:16px;padding:30px;border:1px solid #333}
.tool-header{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.tool-header i{font-size:28px;color:#ff0000}
.tool-header h1{font-size:22px;font-weight:700}
.tool-header span{background:#ff0000;color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600}
.input-group{display:flex;gap:10px;margin-bottom:20px}
.input-group input{flex:1;padding:14px 16px;border-radius:10px;border:1px solid #333;background:#252525;color:#fff;font-size:14px;outline:none}
.input-group input:focus{border-color:#ff0000}
.input-group input::placeholder{color:#666}
.btn{padding:14px 24px;border-radius:10px;border:none;background:#ff0000;color:#fff;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap}
.btn:hover{background:#cc0000}.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-secondary{background:#333;color:#fff;padding:8px 16px;border-radius:8px;border:1px solid #555;cursor:pointer;font-size:13px}
.btn-secondary:hover{background:#444}
.loading{display:none;text-align:center;padding:40px}
.loading i{font-size:32px;color:#ff0000;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.loading p{margin-top:12px;color:#888}
.results{display:none}
.preview{width:100%;border-radius:12px;overflow:hidden;margin-bottom:16px;position:relative}
.preview img{width:100%;display:block}
.preview-badge{position:absolute;top:12px;right:12px;background:rgba(0,0,0,.8);padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.meta-item{background:#252525;padding:12px;border-radius:8px}
.meta-item .label{font-size:11px;color:#888}
.meta-item .value{font-size:14px;font-weight:600;margin-top:4px}
.qualities{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.quality-btn{padding:8px 16px;border-radius:8px;border:1px solid #444;background:#252525;color:#fff;cursor:pointer;font-size:13px}
.quality-btn:hover{border-color:#ff0000}
.quality-btn.active{background:#ff0000;border-color:#ff0000}
.download-section{display:flex;gap:10px}
.error-msg{background:#2d1a1a;border:1px solid #ff4444;color:#ff6666;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px;display:none}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:12px 24px;border-radius:8px;font-size:14px;display:none;z-index:999}
</style>
<div class="tool-container">
<div class="tool-header"><i class="fa-brands fa-youtube"></i><h1>Thumbnail Downloader</h1><span>Tool</span></div>
<div class="input-group"><input type="text" id="url-input" placeholder="Paste YouTube link..." /><button class="btn" id="fetch-btn"><i class="fa-solid fa-download"></i> Fetch</button></div>
<div class="error-msg" id="error-msg"></div>
<div class="loading" id="loading"><i class="fa-solid fa-spinner"></i><p>Fetching thumbnail details...</p></div>
<div class="results" id="results">
<div class="preview"><img id="preview-img" src="" alt="Thumbnail preview"><span class="preview-badge" id="quality-badge">HD</span></div>
<div class="meta-grid">
<div class="meta-item" style="grid-column:span 2"><div class="label">Video Title</div><div class="value" id="video-title">-</div></div>
<div class="meta-item"><div class="label">Channel</div><div class="value" id="channel-name">-</div></div>
<div class="meta-item"><div class="label">Video ID</div><div class="value" id="video-id">-</div></div>
</div>
<div class="qualities" id="qualities"></div>
<div class="download-section"><button class="btn" id="download-btn" style="flex:1"><i class="fa-solid fa-download"></i> Download</button><button class="btn-secondary" id="copy-btn"><i class="fa-solid fa-copy"></i></button></div>
</div>
</div>
<div class="toast" id="toast"></div>
<script>
const BACKEND='<?php echo YT_THUMB_BACKEND_URL; ?>';
let currentData=null,selectedQuality='maxresdefault';
function showToast(msg,type){const t=document.getElementById('toast');t.textContent=msg;t.style.background=type==='error'?'#cc0000':'#333';t.style.display='block';setTimeout(()=>t.style.display='none',3000)}
document.getElementById('fetch-btn').addEventListener('click',fetchThumbnail);
document.getElementById('url-input').addEventListener('keydown',e=>{if(e.key==='Enter')fetchThumbnail()});
async function fetchThumbnail(){const url=document.getElementById('url-input').value.trim();if(!url)return showToast('Enter a YouTube link','error');document.getElementById('loading').style.display='block';document.getElementById('results').style.display='none';document.getElementById('error-msg').style.display='none';try{const res=await fetch(BACKEND+'/api/fetch',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})});const data=await res.json();document.getElementById('loading').style.display='none';if(!res.ok||!data.success){document.getElementById('error-msg').textContent=data.error||'Failed';document.getElementById('error-msg').style.display='block';return}currentData=data;selectedQuality=data.highest_quality_key;renderResults(data)}catch(e){document.getElementById('loading').style.display='none';document.getElementById('error-msg').textContent='Network error';document.getElementById('error-msg').style.display='block'}}
function renderResults(data){const thumb=data.thumbnails[selectedQuality];document.getElementById('preview-img').src=thumb.url;document.getElementById('quality-badge').textContent=thumb.label;document.getElementById('video-title').textContent=data.title;document.getElementById('channel-name').textContent=data.channel_name;document.getElementById('video-id').textContent=data.video_id;const container=document.getElementById('qualities');container.innerHTML='';const order=['maxresdefault','sddefault','hqdefault','mqdefault','default'];order.forEach(key=>{if(!data.thumbnails[key])return;const btn=document.createElement('button');btn.className='quality-btn'+(key===selectedQuality?' active':'');btn.textContent=data.thumbnails[key].label;btn.onclick=()=>selectQuality(key);container.appendChild(btn)});document.getElementById('results').style.display='block'}
function selectQuality(key){if(!currentData||!currentData.thumbnails[key])return;selectedQuality=key;const thumb=currentData.thumbnails[key];document.getElementById('preview-img').src=thumb.url;document.getElementById('quality-badge').textContent=thumb.label;document.querySelectorAll('.quality-btn').forEach(b=>b.classList.remove('active'));document.querySelectorAll('.quality-btn').forEach(b=>{if(b.textContent===thumb.label)b.classList.add('active')})}
document.getElementById('download-btn').addEventListener('click',()=>{if(!currentData)return;window.open(BACKEND+'/api/download?video_id='+currentData.video_id+'&quality='+selectedQuality+'&title='+encodeURIComponent(currentData.title),'_blank')});
document.getElementById('copy-btn').addEventListener('click',()=>{if(!currentData||!currentData.thumbnails[selectedQuality])return;navigator.clipboard.writeText(currentData.thumbnails[selectedQuality].url);showToast('URL copied!')});
</script>
<?php return ob_get_clean();
}
