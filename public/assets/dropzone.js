document.addEventListener('DOMContentLoaded', function() {
    var dz = document.getElementById('dropZone');
    if (!dz) return;

    var input = document.getElementById('fileInput');
    var preview = document.getElementById('dropPreview');
    var allowedExts = ['jpg','jpeg','png','gif','pdf','txt'];
    var maxSize = 5 * 1024 * 1024;

    dz.addEventListener('click', function(e) {
        if (e.target.tagName !== 'BUTTON') input.click();
    });

    ['dragenter','dragover'].forEach(function(ev) {
        dz.addEventListener(ev, function(e) { e.preventDefault(); dz.classList.add('dragover'); });
    });

    ['dragleave','drop'].forEach(function(ev) {
        dz.addEventListener(ev, function(e) { e.preventDefault(); dz.classList.remove('dragover'); });
    });

    dz.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length && input) {
            input.files = files;
            validateAndPreview(files[0]);
        }
    });

    if (input) {
        input.addEventListener('change', function() {
            if (input.files.length) validateAndPreview(input.files[0]);
        });
    }

    function validateAndPreview(file) {
        var ext = file.name.split('.').pop().toLowerCase();
        if (allowedExts.indexOf(ext) === -1) {
            if (typeof PS !== 'undefined') PS.toast('Formato nao permitido: .' + ext, 'error');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        if (file.size > maxSize) {
            if (typeof PS !== 'undefined') PS.toast('Arquivo muito grande. Maximo 5MB.', 'error');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        showPreview(file);
    }

    function showPreview(file) {
        if (!preview) return;
        if (file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" style="max-height:80px;border-radius:4px">';
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '<small class="text-muted">' + file.name + ' (' + Math.round(file.size/1024) + 'KB)</small>';
            preview.style.display = 'block';
        }
    }
});
