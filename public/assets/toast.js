var PS = PS || {};

PS.toast = function(msg, type, duration) {
    type = type || 'success';
    duration = duration || (type === 'error' || type === 'warning' ? 8000 : 4000);

    var icons = {
        success: '\u2714',
        error:   '\u2718',
        warning: '\u26A0',
        info:    '\u2139'
    };

    var container = document.getElementById('psToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'psToastContainer';
        container.className = 'ps-toast-container';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'ps-toast ps-toast-' + type;
    toast.innerHTML =
        '<span class="ps-toast-icon">' + (icons[type] || '') + '</span>' +
        '<span class="ps-toast-msg">' + msg + '</span>' +
        '<button class="ps-toast-close" onclick="PS.toastRemove(this.parentElement)">&times;</button>';

    container.appendChild(toast);

    setTimeout(function() { PS.toastRemove(toast); }, duration);
};

PS.toastRemove = function(el) {
    if (!el || el.classList.contains('removing')) return;
    el.classList.add('removing');
    setTimeout(function() { if (el.parentElement) el.parentElement.removeChild(el); }, 300);
};
