document.addEventListener('submit', function(e) {
    var form = e.target;
    if (form.method && form.method.toLowerCase() !== 'post') return;

    var btn = form.querySelector('[type="submit"], button:not([data-bs-dismiss])');
    if (!btn || btn.dataset.loading) return;

    btn.dataset.loading = '1';
    btn.dataset.origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Carregando...';
});
