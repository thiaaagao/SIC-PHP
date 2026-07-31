document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
    if (e.ctrlKey || e.altKey || e.metaKey) return;

    switch(e.key) {
        case 'n':
        case 'N':
            e.preventDefault();
            window.location.href = 'open_ticket.php';
            break;
        case '/':
            e.preventDefault();
            var search = document.querySelector('input[name="search"]');
            if (search) { search.focus(); search.select(); }
            break;
        case '1':
            if (window.location.pathname.includes('support.php')) {
                e.preventDefault();
                window.location.href = 'support.php?status=open';
            }
            break;
        case '2':
            if (window.location.pathname.includes('support.php')) {
                e.preventDefault();
                window.location.href = 'support.php?status=in_progress';
            }
            break;
        case '3':
            if (window.location.pathname.includes('support.php')) {
                e.preventDefault();
                window.location.href = 'support.php?status=resolved';
            }
            break;
        case '4':
            if (window.location.pathname.includes('support.php')) {
                e.preventDefault();
                window.location.href = 'support.php';
            }
            break;
    }
});
