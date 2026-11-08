(function () {
  const reset = document.getElementById('resetForm');
  const form = reset || document.getElementById('forgotForm');
  const feedback = document.getElementById('feedback');
  const button = form.querySelector('[type="submit"]');
  if (reset) {
    document.getElementById('email').value = new URLSearchParams(location.search).get('email') || '';
    history.replaceState(null, '', location.pathname);
  }
  const password = document.getElementById('password');
  const confirm = document.getElementById('confirmPassword');
  function validate() {
    if (!reset) return;
    const rules = {length: Array.from(password.value).length >= 8, special: /[\p{P}\p{S}]/u.test(password.value), upper: /[A-Z]/.test(password.value)};
    Object.entries(rules).forEach(([id, met]) => document.getElementById(id).classList.toggle('met', met));
    password.setCustomValidity(Object.values(rules).every(Boolean) && new TextEncoder().encode(password.value).length <= 72 && !password.value.includes('\0') ? '' : 'Use at least 8 characters, 1 special character and 1 uppercase letter, with at most 72 UTF-8 bytes.');
    const mismatch = confirm.value !== password.value;
    confirm.setCustomValidity(mismatch ? 'Passwords do not match.' : '');
    document.getElementById('match').textContent = confirm.value && mismatch ? 'Passwords do not match.' : '';
  }
  if (reset) {
    password.addEventListener('input', validate);
    confirm.addEventListener('input', validate);
    form.querySelectorAll('[data-toggle]').forEach(toggle => {
      toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.toggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Hide' : 'Show';
        toggle.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input === password ? 'new' : 'confirm'} password`);
        toggle.setAttribute('aria-pressed', String(show));
      });
    });
    validate();

  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    validate();
    if (!form.reportValidity()) return;
    button.disabled = true;
    feedback.textContent = '';
    try {
      const payload = reset ? {email: document.getElementById('email').value.trim(), code: document.getElementById('code').value.trim(), password: password.value, confirmPassword: confirm.value} : {email: document.getElementById('email').value.trim()};
      const response = await fetch(reset ? 'api/reset_password.php' : 'api/forgot_password.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Please try again later.');
      feedback.className = 'success';
      feedback.textContent = reset ? 'Password updated. You can now log in with your new password.' : data.message;
      form.hidden = true;
      if (!reset) {
        const link = document.getElementById('enterCode');
        link.href = 'reset_password.html?email=' + encodeURIComponent(payload.email);
        link.textContent = 'Enter verification code';
        link.focus();
      }
    } catch (error) {
      feedback.className = 'error';
      feedback.textContent = error instanceof TypeError || error instanceof SyntaxError ? 'Unable to connect. Please try again.' : error.message;
      button.disabled = false;
    }
  });
})();
