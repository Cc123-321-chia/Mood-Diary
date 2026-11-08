(function () {
  const form = document.getElementById('loginForm');
  const password = document.getElementById('password');
  const error = document.getElementById('loginError');
  const button = document.getElementById('loginButton');
  const toggle = document.getElementById('togglePassword');
  function validate() {
    const checks = {
      ruleLength: Array.from(password.value).length >= 8,
      ruleSpecial: /[\p{P}\p{S}]/u.test(password.value),
      ruleUppercase: /[A-Z]/.test(password.value)
    };
    Object.entries(checks).forEach(([id, met]) => document.getElementById(id).classList.toggle('met', met));
    const valid = Object.values(checks).every(Boolean);
    password.setCustomValidity(valid ? '' : 'Use at least 8 characters, 1 special character and 1 uppercase letter (A–Z).');
    return valid;
  }
  password.addEventListener('input', () => { validate(); error.textContent = ''; });
  toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(show));
  });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!validate() || !form.reportValidity()) return;
    error.textContent = '';
    button.disabled = true;
    button.textContent = 'Logging in…';
    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: document.getElementById('email').value.trim(), password: password.value })
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Unable to log in. Please try again.');
      window.location.replace('index.html');
    } catch (err) {
      error.textContent = err instanceof SyntaxError || err instanceof TypeError ? 'Unable to connect. Please try again.' : err.message;
    } finally {
      button.disabled = false;
      button.textContent = 'Log in';
    }
  });
  validate();
})();
