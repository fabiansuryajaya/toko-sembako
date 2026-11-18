import { api } from './core/api.js';

const form = document.getElementById('login-form');
const feedback = form.querySelector('.form-feedback');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = form.querySelector('button');
  button.disabled = true;
  feedback.textContent = '';
  try {
    await api.post('auth/login', { username: form.username.value, password: form.password.value });
    window.location.href = 'app.php';
  } catch (err) {
    feedback.textContent = err.message;
    form.password.select();
  } finally {
    button.disabled = false;
  }
});
