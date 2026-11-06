// Ask before deleting a note, because a deleted note cannot be restored.
// (This is only for the user's convenience. The server still checks the CSRF token and the owner.)
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});
