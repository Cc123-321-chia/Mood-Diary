(() => {
  let csrfToken = '';
  const $ = id => document.getElementById(id);
  const fmt = value => {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
  };
  const statusNames = {
    up_to_date: 'Up to date',
    update_available: 'Update available',
    security_update: 'Security update available',
    unsupported: 'Unsupported / End of Life'
  };

  async function request(url, options = {}) {
    const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', ...options});
    const data = await response.json();
    if (data.csrf_token) csrfToken = data.csrf_token;
    if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
    return data;
  }

  function renderStatus(payload, checkedAt) {
    if (!payload || !statusNames[payload.status] || typeof payload.current_version !== 'string') {
      throw new Error('The PHP release status was invalid. Run a fresh release check.');
    }
    $('currentVersion').textContent = payload.current_version || '—';
    $('latestVersion').textContent = payload.latest_version || 'Not available';
    $('releaseBranch').textContent = payload.release_branch || '—';
    $('supportStatus').textContent = payload.supported ? 'Supported' : 'End of life';
    $('releaseDate').textContent = payload.release_date || '—';
    $('lastChecked').textContent = fmt(checkedAt || payload.checked_at);
    $('securityStatus').textContent = payload.security_update ? 'Security fixes available' : 'No security update detected';
    $('environment').textContent = payload.environment?.label || 'Unknown';
    $('environmentGuidance').textContent = payload.environment?.guidance || 'This page monitors PHP releases only.';

    const badge = $('statusBadge');
    badge.className = `php-badge ${payload.status}`;
    badge.textContent = statusNames[payload.status];
    const notice = $('notice');
    const critical = payload.status === 'security_update' || payload.status === 'unsupported';
    notice.hidden = !critical;
    if (payload.status === 'security_update') {
      notice.textContent = `Security update available: this server runs PHP ${payload.current_version}; PHP ${payload.latest_version} on branch ${payload.release_branch} includes security fixes. Follow the manual guide below.`;
    } else if (payload.status === 'unsupported') {
      notice.textContent = `PHP ${payload.current_version} is no longer supported. Plan a move to a supported PHP/XAMPP package.`;
    }
  }

  async function loadStatus() {
    const message = $('actionMessage');
    try {
      const data = await request('api/php-update/status.php');
      renderStatus(data.status, data.settings?.last_check_at);
      message.textContent = data.check_error || '';
      if (data.check_error) {
        $('notice').hidden = false;
        $('notice').textContent = data.check_error;
      }
    } catch (error) {
      $('statusBadge').textContent = 'Unavailable';
      $('statusBadge').className = 'php-badge unsupported';
      $('notice').hidden = false;
      $('notice').textContent = error.message;
      message.textContent = error.message;
    }
  }

  $('checkButton').addEventListener('click', async () => {
    const button = $('checkButton');
    button.disabled = true;
    button.textContent = 'Checking…';
    $('actionMessage').textContent = '';
    try {
      const data = await request('api/php-update/check.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf_token: csrfToken})
      });
      renderStatus(data.status, data.settings?.last_check_at);
      $('actionMessage').textContent = data.check_error || 'PHP release status refreshed.';
    } catch (error) {
      $('actionMessage').textContent = error.message;
      $('notice').hidden = false;
      $('notice').textContent = error.message;
    } finally {
      button.disabled = false;
      button.textContent = 'Check for Updates';
    }
  });

  loadStatus();
})();
