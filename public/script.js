// public/script.js

document.getElementById('qrForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // 1. UI Loading State (Ubah tampilan saat loading)
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    btn.innerText = 'Processing...';
    btn.disabled = true;
    
    const loading = document.getElementById('loading');
    const qrImage = document.getElementById('qrImage');
    const placeholder = document.getElementById('placeholder');
    const actionButtons = document.getElementById('actionButtons');
    const downloadBtn = document.getElementById('downloadBtn');
    
    loading.classList.remove('hidden');
    qrImage.classList.add('hidden');
    placeholder.classList.add('hidden');
    actionButtons.classList.add('hidden');

    // 2. Ambil Data Form
    // Pastikan elemen ID 'format' ada di HTML Anda. Jika belum ada, default ke 'png'
    const formatEl = document.getElementById('format');
    const selectedFormat = formatEl ? formatEl.value : 'png';

    const data = {
        text: document.getElementById('text').value,
        size: document.getElementById('size').value,
        margin: document.getElementById('margin').value,
        fgColor: document.getElementById('fgColor').value,
        bgColor: document.getElementById('bgColor').value,
        transparent: document.getElementById('transparent').checked,
        logoUrl: document.getElementById('logoUrl').value,
        format: selectedFormat // Kirim format pilihan user ke backend
    };

    try {
        // 3. Kirim Request ke Backend
        const response = await fetch('/api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.status === 'success') {
            // 4. Tampilkan Gambar Hasil
            qrImage.src = result.image; 
            qrImage.classList.remove('hidden');
            loading.classList.add('hidden');
            actionButtons.classList.remove('hidden');
            
            // 5. Update Teks Tombol Download sesuai format yang dipilih
            // Mengambil format dari respon backend (jika ada), atau dari input user
            const finalFormat = result.format ? result.format.toUpperCase() : selectedFormat.toUpperCase();
            
            downloadBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Download ${finalFormat}
            `;

        } else {
            alert('Error: ' + result.message);
            loading.classList.add('hidden');
        }

    } catch (error) {
        console.error(error);
        alert('Gagal menghubungi server. Pastikan php -S jalan.');
        loading.classList.add('hidden');
    } finally {
        // Reset tombol submit
        btn.innerText = originalText;
        btn.disabled = false;
    }
});

// 6. Logic Download Otomatis yang Cerdas
document.getElementById('downloadBtn').addEventListener('click', function() {
    const img = document.getElementById('qrImage');
    const imgSource = img.src;
    
    if (!imgSource) return;

    // Deteksi Format Asli langsung dari Data URI gambar
    // Contoh: "data:image/svg+xml;base64,..." -> SVG
    // Contoh: "data:image/jpeg;base64,..."   -> JPG
    
    let extension = 'png'; // Default fallback
    
    if (imgSource.includes('image/svg+xml')) {
        extension = 'svg';
    } else if (imgSource.includes('image/jpeg')) {
        extension = 'jpg';
    } else if (imgSource.includes('image/png')) {
        extension = 'png';
    }

    // Buat link download sementara
    const link = document.createElement('a');
    link.href = imgSource;
    link.download = `qrcode.${extension}`; // Nama file otomatis mengikuti ekstensi yang benar
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});