document.getElementById('qrForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // UI Loading
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    btn.innerText = 'Processing...';
    btn.disabled = true;
    
    document.getElementById('loading').classList.remove('hidden');
    document.getElementById('qrImage').classList.add('hidden');
    document.getElementById('actionButtons').classList.add('hidden'); 
    document.getElementById('placeholder').classList.add('hidden');

    // Ambil Data
    const data = {
        text: document.getElementById('text').value,
        size: document.getElementById('size').value,
        margin: document.getElementById('margin').value,
        fgColor: document.getElementById('fgColor').value,
        bgColor: document.getElementById('bgColor').value,
        transparent: document.getElementById('transparent').checked,
        logoUrl: document.getElementById('logoUrl').value,
        format: document.getElementById('format').value
    };

    try {
        const response = await fetch('/api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.status === 'success') {
            const img = document.getElementById('qrImage');
            img.src = result.image; 
            img.classList.remove('hidden');
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('actionButtons').classList.remove('hidden');
            
            // Update Text Tombol
            const ext = result.format.toUpperCase();
            document.getElementById('downloadBtn').innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                DOWNLOAD ${ext}
            `;

        } else {
            alert('Error: ' + result.message);
            document.getElementById('loading').classList.add('hidden');
        }

    } catch (error) {
        console.error(error);
        alert('Gagal generate. Pastikan server lokal jalan (php -S localhost:8000).');
        document.getElementById('loading').classList.add('hidden');
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
});

// Download Logic
document.getElementById('downloadBtn').addEventListener('click', function() {
    const imgSource = document.getElementById('qrImage').src;
    if (!imgSource) return;

    let extension = 'png';
    if (imgSource.includes('image/jpeg')) extension = 'jpg';
    if (imgSource.includes('image/svg+xml')) extension = 'svg';

    const link = document.createElement('a');
    link.href = imgSource;
    link.download = `qrcode.${extension}`; 
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});