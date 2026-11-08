(function () {
  const form = document.getElementById('registerForm');
  const password = document.getElementById('password');
  const error = document.getElementById('registerError');
  const button = document.getElementById('registerButton');
  const toggle = document.getElementById('togglePassword');
  const confirm = document.getElementById('confirmPassword');
  const success = document.getElementById('registerSuccess');
  const email = document.getElementById('email');
  const emailStatus = document.getElementById('emailStatus');
  const confirmToggle = document.getElementById('toggleConfirmPassword');
  const passwordMatch = document.getElementById('passwordMatch');
  let confirmTouched = false;
  let emailCheckVersion = 0;
  function validateEmailFormat() {
    email.setCustomValidity('');
    const value = email.value.trim();
    const valid = value !== '' && !email.validity.typeMismatch
      && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    const message = valid ? '' : 'Enter a valid email address, such as you@example.com.';
    email.setCustomValidity(message);
    email.setAttribute('aria-invalid', String(!valid));
    emailStatus.classList.toggle('error', !valid);
    emailStatus.textContent = message;
    return valid;
  }
  email.addEventListener('invalid', () => {
    if (!email.validity.customError) validateEmailFormat();
  });
  email.addEventListener('input', () => {
    emailCheckVersion++;
    email.setCustomValidity('');
    email.removeAttribute('aria-invalid');
    emailStatus.textContent = '';
    emailStatus.classList.remove('error');
    if (email.value) validateEmailFormat();
    error.textContent = '';
  });
  email.addEventListener('blur', async () => {
    email.value = email.value.trim();
    email.setCustomValidity('');
    const version = ++emailCheckVersion;
    if (!validateEmailFormat()) return;
    emailStatus.classList.remove('error');
    emailStatus.textContent = 'Checking email…';
    try {
      const response = await fetch('api/check_email.php', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email.value })
      });
      const data = await response.json();
      if (version !== emailCheckVersion) return;
      if (!response.ok || !data.ok) throw new Error('Email check failed');
      const message = data.registered ? 'This email is already registered. Please log in.' : '';
      email.setCustomValidity(message);
      email.setAttribute('aria-invalid', String(data.registered));
      emailStatus.classList.toggle('error', data.registered);
      emailStatus.textContent = message || 'This email is available.';
    } catch (err) {
      if (version !== emailCheckVersion) return;
      emailStatus.textContent = 'Could not check email now. We will check again when you create your account.';
    }
  });
  confirmToggle.addEventListener('click', () => {
    const show = confirm.type === 'password';
    confirm.type = show ? 'text' : 'password';
    confirmToggle.textContent = show ? 'Hide' : 'Show';
    confirmToggle.setAttribute('aria-label', show ? 'Hide confirm password' : 'Show confirm password');
    confirmToggle.setAttribute('aria-pressed', String(show));
  });
  function validate() {
    const checks = {
      ruleLength: Array.from(password.value).length >= 8,
      ruleSpecial: /[\p{P}\p{S}]/u.test(password.value),
      ruleUppercase: /[A-Z]/.test(password.value)
    };
    Object.entries(checks).forEach(([id, met]) => document.getElementById(id).classList.toggle('met', met));
    const valid = Object.values(checks).every(Boolean);
    password.setCustomValidity(valid ? '' : 'Use at least 8 characters, 1 special character and 1 uppercase letter (A–Z).');
    if (new TextEncoder().encode(password.value).length > 72) {
      password.setCustomValidity('Use a password of at most 72 UTF-8 bytes.');
    }
    const matches = confirm.value === password.value;
    const showMismatch = (confirmTouched || confirm.value !== '') && !matches;
    confirm.setCustomValidity(matches ? '' : 'Passwords do not match.');
    confirm.setAttribute('aria-invalid', String(showMismatch));
    passwordMatch.classList.toggle('error', showMismatch);
    passwordMatch.textContent = showMismatch
      ? 'Passwords do not match. Please enter the same password in both fields.'
      : (confirm.value ? 'Passwords match.' : '');
    return valid && password.validity.valid && confirm.validity.valid;
  }
  password.addEventListener('input', () => { validate(); error.textContent = ''; });
  confirm.addEventListener('input', () => { confirmTouched = true; validate(); });
  confirm.addEventListener('blur', () => { confirmTouched = true; validate(); });
  confirm.addEventListener('invalid', () => { confirmTouched = true; validate(); });
  toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(show));
  });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    validate();
    if (!form.reportValidity()) return;
    error.textContent = '';
    button.disabled = true;
    button.textContent = 'Creating account…';
    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: document.getElementById('name').value.trim(), confirmPassword: confirm.value, email: document.getElementById('email').value.trim(), password: password.value })
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Unable to create an account. Please try again.');
      emailCheckVersion++;
      emailStatus.textContent = '';
      email.setCustomValidity('');
      confirmTouched = false;
      form.reset();
      validate();
      success.textContent = 'Account created! You can now log in using the link below.';
      button.hidden = true;
      form.querySelectorAll('input, button').forEach(element => { element.disabled = true; });
    } catch (err) {
      error.textContent = err instanceof SyntaxError || err instanceof TypeError ? 'Unable to connect. Please try again.' : err.message;
    } finally {
      button.disabled = false;
      button.textContent = 'Create account';
    }
  });
  validate();
})();
