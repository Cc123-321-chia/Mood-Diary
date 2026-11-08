(function () {
  const form = document.getElementById('changePasswordForm');
  const password = document.getElementById('newPassword');
  const confirmation = document.getElementById('confirmPassword');
  const message = document.getElementById('changePasswordMessage');
  const button = document.getElementById('changePasswordButton');
  const toggle = document.getElementById('togglePassword');

  function validate() {
    const checks = {
      ruleLength: Array.from(password.value).length >= 8,
      ruleSpecial: /[\p{P}\p{S}]/u.test(password.value),
      ruleUppercase: /[A-Z]/.test(password.value)
    };
    Object.entries(checks).forEach(([id, met]) => document.getElementById(id).classList.toggle('met', met));
    return Object.values(checks).every(Boolean);
  }

  password.addEventListener('input', () => { validate(); message.textContent = ''; });
  confirmation.addEventListener('input', () => { message.textContent = ''; });
  toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    confirmation.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(show));
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!validate()) { message.textContent = 'Meet all password requirements first.'; return; }
    if (password.value !== confirmation.value) { message.textContent = 'The new passwords do not match.'; return; }
    button.disabled = true;
    button.textContent = 'Changing password…';
    message.textContent = '';
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          password: password.value,
          confirm_password: confirmation.value,
          csrf_token: document.getElementById('csrfToken').value
        })
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Unable to change the password.');
      window.location.replace('index.php');
    } catch (error) {
      message.textContent = error instanceof TypeError ? 'Unable to connect. Please try again.' : error.message;
    } finally {
      button.disabled = false;
      button.textContent = 'Change password';
    }
  });
})();
