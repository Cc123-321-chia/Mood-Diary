<?php
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard · Mood Diary</title>
  <link rel="stylesheet" href="admin.css">
  <style>
    .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.stat-card{padding:19px;background:#f8fcfa;border:1px solid var(--line);border-radius:18px;box-shadow:0 10px 22px -20px #203c35}.stat-label{color:var(--muted);font-size:13px;font-weight:650}.stat-value{margin-top:7px;font-size:30px;line-height:1.1;font-weight:800;letter-spacing:-.7px}.stat-note{margin-top:6px;color:#81968f;font-size:12px}.critical-panel{margin-bottom:20px}.critical-heading{display:flex;align-items:center;gap:10px;padding:17px 20px;background:#f1f8f4;border-bottom:1px solid var(--line)}.critical-heading h2{margin:0;font-size:17px}.critical-count{margin-left:auto;padding:4px 11px;border-radius:999px;background:#fde8e8;color:#a52f32;font-size:12px;font-weight:800}.critical-count.clear{background:#e5f5e9;color:#257747}.critical-empty{padding:22px 20px;color:var(--muted)}.critical-list{list-style:none;padding:0;margin:0}.critical-item{display:grid;grid-template-columns:115px minmax(0,1fr) auto auto;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #e2ede8}.critical-item:last-child{border-bottom:0}.critical-time{color:var(--muted);font-size:13px;white-space:nowrap}.critical-event{font-weight:750;color:#9f3033}.critical-description{margin-top:3px;color:var(--muted);font-size:13px;overflow-wrap:anywhere}.critical-meta{color:var(--muted);font-size:12px;text-align:right;overflow-wrap:anywhere;max-width:190px}.quick-links{margin-top:20px}.dashboard-error{color:#a63232}.feature-card h2{margin-top:9px}@media(max-width:1000px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.stats-grid{grid-template-columns:1fr 1fr}.stat-value{font-size:25px}.critical-item{grid-template-columns:1fr;gap:4px}.critical-meta{text-align:left;max-width:none}.critical-count{white-space:nowrap}}
    .resolve-btn{display:inline-flex;align-items:center;justify-content:center;padding:7px 13px;background:#fff;color:#46685c;border:1px solid #c2d9d2;border-radius:999px;font-size:12px;white-space:nowrap}.resolve-btn:hover{background:#5f7e73;color:#fff}.resolve-btn:disabled{opacity:.55;cursor:wait}.php-update-banner{display:flex;align-items:center;gap:12px;margin:0 0 20px;padding:14px 18px;border:1px solid #efbbbb;border-radius:13px;background:#fff2f1;color:#922d2d;line-height:1.45}.php-update-banner a{margin-left:auto;color:inherit;font-weight:750;white-space:nowrap}.php-update-banner.safe{border-color:#cfe3d7;background:#f3faf5;color:#355c49}
  </style>
  <link rel="stylesheet" href="admin-logout.css">
</head>
<body>
<div class="admin-layout">
  <aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark">📔</span><span>Mood Diary<br><small class="muted">Admin workspace</small></span></a>
    <div class="side-label">Workspace</div>
    <nav class="nav" aria-label="Admin navigation">
      <a class="active" href="index.php"><span class="nav-icon">⌂</span> Dashboard</a>
      <a href="users.php"><span class="nav-icon">♙</span> Users</a>
      <a href="security_logs.php"><span class="nav-icon">◷</span> Security logs</a>
      <a href="php_updates.php"><span class="nav-icon">⬆</span> PHP update monitor</a>
    </nav>
    <div class="sidebar-note">Manage accounts and review activity across Mood Diary.</div>
      <button class="admin-logout" id="adminLogout" type="button">Log out</button><p class="logout-error" id="logoutError" role="alert"></p>
  </aside>
  <main class="admin-main">
    <header class="page-heading"><div><h1>Admin dashboard</h1><p class="sub" id="dashboardDate">Today at a glance</p></div></header>
    <section class="stats-grid" aria-label="Today's dashboard statistics">
      <article class="stat-card"><div class="stat-label">New users today</div><div class="stat-value" id="newUsersToday">—</div><div class="stat-note">User accounts created today</div></article>
      <article class="stat-card"><div class="stat-label">Total users</div><div class="stat-value" id="totalUsers">—</div><div class="stat-note">All registered user accounts</div></article>
      <article class="stat-card"><div class="stat-label">Diaries made today</div><div class="stat-value" id="diariesToday">—</div><div class="stat-note">Diary records created today</div></article>
      <article class="stat-card"><div class="stat-label">Security logs today</div><div class="stat-value" id="logsToday">—</div><div class="stat-note">Events recorded since midnight</div></article>
    </section>
    <section class="panel critical-panel" aria-labelledby="criticalTitle">
      <header class="critical-heading"><span aria-hidden="true">⚠️</span><h2 id="criticalTitle">Critical security activity today</h2><span id="criticalCount" class="critical-count clear">Checking…</span></header>
      <div id="resolutionNotice" class="critical-empty" hidden>To enable the Resolve buttons, run <strong>resolve_security_logs.sql</strong> once in phpMyAdmin with <strong>mood_diary</strong> selected.</div>
      <div id="criticalLogs" class="critical-empty" aria-live="polite">Loading today's security events…</div>
    </section>
    <section id="phpUpdateBanner" class="php-update-banner safe" hidden aria-live="polite"><span id="phpUpdateMessage">Checking PHP release status…</span><a href="php_updates.php">PHP Update Monitor →</a></section>
    <section class="cards quick-links" aria-label="Admin tools">
      <a class="feature-card" href="users.php"><span class="feature-icon">👥</span><h2>Users & admins</h2><p>View accounts, add users or issue temporary admin passwords.</p></a>
      <a class="feature-card" href="security_logs.php"><span class="feature-icon">🛡️</span><h2>Security logs</h2><p>Search account and administrator security events.</p></a>
      <a class="feature-card" href="php_updates.php"><span class="feature-icon">⬆️</span><h2>PHP Update Monitor</h2><p>Review PHP support, official releases, security status, and manual XAMPP guidance.</p></a>
    </section>
  </main>
</div>
<script>
let csrfToken='';
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const n=value=>new Intl.NumberFormat().format(Number(value)||0);
function dateTime(value){const d=new Date(String(value).replace(' ','T'));return Number.isNaN(d.getTime())?value:d.toLocaleString(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});}
async function loadDashboard(){try{const response=await fetch('dashboard_stats.php',{cache:'no-store'});const data=await response.json();if(!response.ok||!data.success)throw Error(data.message||'Dashboard statistics could not be loaded.');csrfToken=data.csrf_token||'';document.getElementById('newUsersToday').textContent=n(data.stats.new_users_today);document.getElementById('totalUsers').textContent=n(data.stats.total_users);document.getElementById('diariesToday').textContent=n(data.stats.diaries_today);document.getElementById('logsToday').textContent=n(data.stats.security_logs_today);document.getElementById('dashboardDate').textContent=`Today at a glance · ${new Date(`${data.date}T12:00:00`).toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric',year:'numeric'})}`;const count=document.getElementById('criticalCount'),list=document.getElementById('criticalLogs');document.getElementById('resolutionNotice').hidden=Boolean(data.resolution_ready);count.textContent=`${n(data.critical_count)} open`;count.className='critical-count'+(Number(data.critical_count)?'':' clear');if(!data.critical_logs.length){list.className='critical-empty';list.textContent='No unresolved critical security events recorded today.';return;}list.className='';list.innerHTML=`<ul class="critical-list">${data.critical_logs.map(log=>`<li class="critical-item"><time class="critical-time">${esc(dateTime(log.created_at))}</time><div><div class="critical-event">${esc(log.event_type)}</div><div class="critical-description">${esc(log.description||'No description provided.')}</div></div><div class="critical-meta">${esc(log.account_name)}${log.ip_address?`<br>${esc(log.ip_address)}`:''}</div><button class="resolve-btn" type="button" data-log-id="${Number(log.log_id)}" ${data.resolution_ready?'':'disabled title="Run resolve_security_logs.sql first"'}>Resolve</button></li>`).join('')}</ul>`;list.querySelectorAll('.resolve-btn:not(:disabled)').forEach(button=>button.addEventListener('click',()=>resolveCriticalLog(button)));}catch(error){document.getElementById('criticalCount').textContent='Unavailable';document.getElementById('criticalCount').className='critical-count';document.getElementById('criticalLogs').className='critical-empty dashboard-error';document.getElementById('criticalLogs').textContent=error.message;}}
async function resolveCriticalLog(button){button.disabled=true;button.textContent='Resolving…';try{const response=await fetch('resolve_security_log.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken},body:JSON.stringify({log_id:Number(button.dataset.logId)})});const data=await response.json();if(!response.ok||!data.success)throw Error(data.message||'Could not resolve this log.');await loadDashboard();}catch(error){button.disabled=false;button.textContent='Resolve';alert(error.message);}}
async function loadPhpUpdateNotice(){try{const response=await fetch('api/php-update/status.php',{cache:'no-store'});const data=await response.json();if(!response.ok||!data.ok)return;const s=data.status;if(s.status==='up_to_date')return;const banner=document.getElementById('phpUpdateBanner');banner.hidden=false;banner.className='php-update-banner'+(s.status==='update_available'?' safe':'');document.getElementById('phpUpdateMessage').textContent=s.status==='security_update'?`⚠ Critical PHP security update available: PHP ${s.current_version} → ${s.latest_version}.`:s.status==='unsupported'?`⚠ PHP ${s.current_version} is unsupported and no longer receives official security updates.`:`PHP ${s.current_version} has an update available: ${s.latest_version}.`;}catch(_){}}
loadDashboard();
loadPhpUpdateNotice();
</script>
  <script src="admin_logout.js"></script>
</body></html>
