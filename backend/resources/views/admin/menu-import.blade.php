<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Menu Items — Swaad E Punjab Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 560px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: #6b7280;
            font-size: 0.875rem;
            text-decoration: none;
            margin-bottom: 1.5rem;
        }
        .back-link:hover { color: #111; }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        p.subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .alert-success {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            border-radius: 8px;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            border-radius: 8px;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .drop-zone {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .drop-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }
        .drop-text {
            color: #374151;
            font-weight: 600;
            font-size: 1rem;
        }
        .drop-hint {
            color: #9ca3af;
            font-size: 0.8rem;
            margin-top: 0.4rem;
        }
        .file-info {
            display: none;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
        }
        .file-info.visible { display: flex; }
        .file-info .file-icon { font-size: 1.5rem; }
        .file-info .file-name { font-weight: 600; color: #111; font-size: 0.9rem; }
        .file-info .file-size { color: #6b7280; font-size: 0.8rem; }
        .btn-import {
            display: block;
            width: 100%;
            padding: 0.9rem;
            background: #f59e0b;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
        }
        .btn-import:hover { background: #d97706; }
        .btn-import:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }
        .progress-wrap {
            display: none;
            margin-bottom: 1.5rem;
        }
        .progress-wrap.visible { display: block; }
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 0.4rem;
        }
        .progress-bar-bg {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: #f59e0b;
            border-radius: 999px;
            width: 0%;
            transition: width 0.3s;
        }
        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #6b7280;
            font-size: 0.875rem;
            text-decoration: none;
        }
        .cancel-link:hover { color: #111; }
    </style>
</head>
<body>
<div class="card">
    <a href="/admin/menu-items" class="back-link">← Back to Menu Items</a>
    <h1>Import Menu Items</h1>
    <p class="subtitle">Upload your menu PDF, Excel (.xlsx, .xls) or CSV file — up to 500 MB</p>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form id="importForm" method="POST" action="/admin/menu-import/upload" enctype="multipart/form-data">
        @csrf
        <div class="drop-zone" id="dropZone">
            <input type="file" name="menu_file" id="fileInput" accept=".pdf,.xlsx,.xls,.csv" required>
            <div class="drop-icon">📁</div>
            <div class="drop-text">Click to select or drag & drop file</div>
            <div class="drop-hint">PDF, Excel (.xlsx, .xls), or CSV — max 500 MB</div>
        </div>

        <div class="file-info" id="fileInfo">
            <span class="file-icon">📄</span>
            <div>
                <div class="file-name" id="fileName">—</div>
                <div class="file-size" id="fileSize">—</div>
            </div>
        </div>

        <div class="progress-wrap" id="progressWrap">
            <div class="progress-label">
                <span>Uploading & processing...</span>
                <span id="progressPct">0%</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" id="progressBar"></div>
            </div>
        </div>

        <button type="submit" class="btn-import" id="submitBtn">Import Menu Items</button>
    </form>

    <a href="/admin/menu-items" class="cancel-link">Cancel</a>
</div>

<script>
const fileInput = document.getElementById('fileInput');
const fileInfo  = document.getElementById('fileInfo');
const fileName  = document.getElementById('fileName');
const fileSize  = document.getElementById('fileSize');
const dropZone  = document.getElementById('dropZone');
const submitBtn = document.getElementById('submitBtn');
const form      = document.getElementById('importForm');
const progressWrap = document.getElementById('progressWrap');
const progressBar  = document.getElementById('progressBar');
const progressPct  = document.getElementById('progressPct');

function formatBytes(b) {
    if (b >= 1024*1024) return (b/1024/1024).toFixed(1) + ' MB';
    if (b >= 1024)      return (b/1024).toFixed(1) + ' KB';
    return b + ' B';
}

fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    if (f) {
        fileName.textContent = f.name;
        fileSize.textContent = formatBytes(f.size);
        fileInfo.classList.add('visible');
    }
});

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f) {
        // Assign to input via DataTransfer
        const dt = new DataTransfer();
        dt.items.add(f);
        fileInput.files = dt.files;
        fileName.textContent = f.name;
        fileSize.textContent = formatBytes(f.size);
        fileInfo.classList.add('visible');
    }
});

form.addEventListener('submit', function(e) {
    e.preventDefault();
    const file = fileInput.files[0];
    if (!file) { alert('Please select a file first.'); return; }

    submitBtn.disabled = true;
    progressWrap.classList.add('visible');

    const xhr = new XMLHttpRequest();
    const fd  = new FormData(form);

    xhr.upload.addEventListener('progress', function(ev) {
        if (ev.lengthComputable) {
            const pct = Math.round((ev.loaded / ev.total) * 100);
            progressBar.style.width = pct + '%';
            progressPct.textContent = pct + '%';
        }
    });

    xhr.addEventListener('load', function() {
        // Server responded — redirect to the response URL
        const tempDoc = document.createElement('html');
        tempDoc.innerHTML = xhr.responseText;

        // Check for redirect
        if (xhr.responseURL && xhr.responseURL !== window.location.href) {
            window.location.href = xhr.responseURL;
            return;
        }

        // Replace current page content
        document.open();
        document.write(xhr.responseText);
        document.close();
        window.history.replaceState({}, '', xhr.responseURL || window.location.href);
    });

    xhr.addEventListener('error', function() {
        alert('Upload failed. Please try again.');
        submitBtn.disabled = false;
        progressWrap.classList.remove('visible');
    });

    xhr.open('POST', form.action, true);
    xhr.send(fd);
});
</script>
</body>
</html>
