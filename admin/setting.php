<div class="page settings-page">
    <h1>Pengaturan Sistem</h1>
    
    <div class="settings-container" style="max-width: 600px; margin: 20px 0;">
        <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
            <h3 style="margin-top: 0;">Nota Hutang</h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" id="show_signature_on_nota_hutang" style="margin-right: 10px; width: 20px; height: 20px;">
                    <span style="font-size: 16px;">Tampilkan kolom tanda tangan pada Nota Hutang</span>
                </label>
                <p style="margin: 5px 0 0 30px; color: #666; font-size: 14px;">
                    Jika diaktifkan, kolom tanda tangan akan muncul di atas pesan terima kasih pada nota hutang. Gunakan ini untuk pembeli yang membayar nanti.
                </p>
            </div>
            
            <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="margin-top: 0;">Gambar Tanda Tangan</h3>
                <p style="margin:6px 0 10px 0; color:#666;">Unggah gambar tanda tangan yang akan ditampilkan pada Nota Hutang.</p>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div>
                        <input type="file" id="signatureFile" accept="image/*">
                        <div style="margin-top:8px;">
                            <button id="uploadSignatureBtn" style="background:#007bff;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;">Unggah</button>
                            <button id="deleteSignatureBtn" style="background:#dc3545;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;margin-left:8px;">Hapus</button>
                        </div>
                    </div>
                    <div>
                        <div style="width:160px;height:80px;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;background:#fff;">
                            <img id="signaturePreview" src="" alt="Preview" style="max-width:100%;max-height:100%;display:none;" />
                            <span id="signaturePlaceholder" style="color:#999;">Belum ada</span>
                        </div>
                    </div>
                </div>
                <div id="signatureMessage" style="margin-top:8px; display:none;"></div>
            </div>
        </div>
        
        <button id="saveSetting" style="background-color:#28a745;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-size:16px;">
            Simpan Pengaturan
        </button>
        <div id="settingMessage" style="margin-top: 10px; display: none;"></div>
    </div>
</div>

<script>
// Load settings and signature
async function loadSettings() {
    try {
        const result = await callAPI({ url: '../api/settings.php', method: 'GET' });
        if (result.data) {
            result.data.forEach(setting => {
                if (setting.nama_setting === 'show_signature_on_nota_hutang') {
                    const checkbox = document.getElementById('show_signature_on_nota_hutang');
                    checkbox.checked = setting.nilai_setting === 1 || setting.nilai_setting === '1';
                }
                if (setting.nama_setting === 'signature_image') {
                    const preview = document.getElementById('signaturePreview');
                    const placeholder = document.getElementById('signaturePlaceholder');
                    if (setting.nilai_setting) {
                        preview.src = '../' + setting.nilai_setting;
                        preview.style.display = 'block';
                        placeholder.style.display = 'none';
                    } else {
                        preview.style.display = 'none';
                        placeholder.style.display = 'block';
                    }
                }
            });
        }
    } catch (error) {
        console.error('Gagal memuat settings:', error);
    }
}

// Save simple boolean settings
document.getElementById('saveSetting').addEventListener('click', async function() {
    try {
        const showSignature = document.getElementById('show_signature_on_nota_hutang').checked ? 1 : 0;

        await callAPI({
            url: '../api/settings.php',
            method: 'PUT',
            body: {
                nama_setting: 'show_signature_on_nota_hutang',
                nilai_setting: showSignature
            }
        });

        const messageDiv = document.getElementById('settingMessage');
        messageDiv.textContent = '✓ Pengaturan berhasil disimpan';
        messageDiv.style.color = '#28a745';
        messageDiv.style.display = 'block';

        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 3000);
    } catch (error) {
        console.error('Gagal menyimpan settings:', error);
        const messageDiv = document.getElementById('settingMessage');
        messageDiv.textContent = '✗ Gagal menyimpan pengaturan';
        messageDiv.style.color = '#dc3545';
        messageDiv.style.display = 'block';
    }
});

// Signature upload and preview handling
const signatureFile = document.getElementById('signatureFile');
const signaturePreview = document.getElementById('signaturePreview');
const signaturePlaceholder = document.getElementById('signaturePlaceholder');
const signatureMessage = document.getElementById('signatureMessage');

signatureFile.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            signaturePreview.src = e.target.result;
            signaturePreview.style.display = 'block';
            signaturePlaceholder.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
});

document.getElementById('uploadSignatureBtn').addEventListener('click', async function() {
    const file = signatureFile.files[0];
    if (!file) {
        signatureMessage.textContent = 'Pilih file gambar terlebih dahulu.';
        signatureMessage.style.color = '#dc3545';
        signatureMessage.style.display = 'block';
        return;
    }

    const formData = new FormData();
    formData.append('signature', file);

    try {
        const res = await fetch('../api/settings.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            signatureMessage.textContent = '✓ Gambar tanda tangan berhasil diunggah';
            signatureMessage.style.color = '#28a745';
            signatureMessage.style.display = 'block';
            // reload settings to show saved path
            await loadSettings();
        } else {
            signatureMessage.textContent = '✗ Gagal mengunggah: ' + (data.error || 'Unknown');
            signatureMessage.style.color = '#dc3545';
            signatureMessage.style.display = 'block';
        }
    } catch (err) {
        console.error(err);
        signatureMessage.textContent = '✗ Terjadi kesalahan saat mengunggah.';
        signatureMessage.style.color = '#dc3545';
        signatureMessage.style.display = 'block';
    }
});

document.getElementById('deleteSignatureBtn').addEventListener('click', async function() {
    if (!confirm('Hapus gambar tanda tangan?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_signature');

    try {
        const res = await fetch('../api/settings.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            signatureMessage.textContent = '✓ Gambar tanda tangan dihapus';
            signatureMessage.style.color = '#28a745';
            signatureMessage.style.display = 'block';
            signaturePreview.style.display = 'none';
            signaturePlaceholder.style.display = 'block';
            signatureFile.value = null;
        } else {
            signatureMessage.textContent = '✗ Gagal menghapus: ' + (data.error || 'Unknown');
            signatureMessage.style.color = '#dc3545';
            signatureMessage.style.display = 'block';
        }
    } catch (err) {
        console.error(err);
        signatureMessage.textContent = '✗ Terjadi kesalahan saat menghapus.';
        signatureMessage.style.color = '#dc3545';
        signatureMessage.style.display = 'block';
    }
});

// Load settings when page loads
loadSettings();
</script>
