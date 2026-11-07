// assets/password-toggle.js
// Every show/hide eye button on the page. Includes .password-wrap.
document.querySelectorAll('.password-wrap').forEach(function (wrap) {
  var input = wrap.querySelector('input');
  var eye   = wrap.querySelector('.toggle-password');
  if (!input || !eye) return;

  eye.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    eye.setAttribute('aria-pressed', show ? 'true' : 'false');
    eye.querySelectorAll('.eye-open').forEach(function (el) { el.style.display = show ? 'none' : ''; });
    eye.querySelector('.eye-off').style.display = show ? '' : 'none';
  });
});