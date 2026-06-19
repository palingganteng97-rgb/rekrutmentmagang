<?php 
session_start(); 

// 1. PENGATURAN UTAMA WAKTU
date_default_timezone_set('Asia/Jakarta'); 

// 2. KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// Mengambil session data pelamar yang login
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

// Inisialisasi awal variabel array agar halaman tidak error
$lowongan_dilamar = [];
$list_pendidikan  = [];
$list_berkas      = [];
$list_str         = [];
$list_pengalaman  = [];
$data             = null;

if ($pelamar_id) {
    // Ambil Biodata Utama Pelamar
    $query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
    if ($query_user) { $data = mysqli_fetch_assoc($query_user); }

    // Ambil Riwayat Pendidikan Pelamar
    $query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
    if ($query_pend) { while ($row = mysqli_fetch_assoc($query_pend)) { $list_pendidikan[] = $row; } }
    
    // Ambil Lampiran Berkas Dokumen Upload
    $query_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
    if ($query_bk) { while ($row_bk = mysqli_fetch_assoc($query_bk)) { $list_berkas[] = $row_bk; } }

    // Ambil Data Surat Tanda Registrasi (STR)
    $query_s = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
    if ($query_s) { while ($row_s = mysqli_fetch_assoc($query_s)) { $list_str[] = $row_s; } }

    // Ambil Riwayat Pengalaman Kerja Pelamar
    $query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
    if ($query_exp) { while ($row_exp = mysqli_fetch_assoc($query_exp)) { $list_pengalaman[] = $row_exp; } }

    // Kumpulkan ID Lowongan yang Sudah Pernah Dilamar User Ini
    $query_l_dilamar = mysqli_query($koneksi, "SELECT lowongan_id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id");
    if ($query_l_dilamar) { while ($row_ld = mysqli_fetch_assoc($query_l_dilamar)) { $lowongan_dilamar[] = $row_ld['lowongan_id']; } }
}

// PROSES INSERT LAMARAN KE DATABASE (SAAT MODAL DI-SUBMIT)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kirim_lamaran_final'])) {
    $tanggal_masuk = date('Y-m-d H:i:s'); 
    $status_awal   = 'Proses'; 
    $lowongan_id   = isset($_POST['lowongan_id']) ? intval($_POST['lowongan_id']) : 0;

    $query_kirim = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, tanggal_lamaran, current_tahapan_id, status, created_at, updated_at) 
                    VALUES ($pelamar_id, $lowongan_id, '$tanggal_masuk', 1, '$status_awal', '$tanggal_masuk', '$tanggal_masuk')";

    if (mysqli_query($koneksi, $query_kirim)) {
        echo "<script>alert('✓ Sukses! Lamaran Anda berhasil dikirim.'); window.location.href='rekrutmen_lamaran.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Portal Lowongan Kerja</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 40px auto; }
        
        .grid-lowongan { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 350px)); gap: 25px; justify-content: start; margin-top: 20px; }
        .card-lowongan { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; width: 100%; }
        
        .btn-action { flex: 1; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; border: none; text-align: center; text-decoration: none; cursor: pointer; transition: 0.2s; }
        .btn-lamar { background: #4338ca; color: white; }
        .btn-lamar:hover { background: #3730a3; }
        .btn-detail { background: #2563eb; color: white; }
        .btn-detail:hover { background: #1d4ed8; }
        .btn-disabled { background: #e2e8f0; color: #64748b; cursor: not-allowed; }
        
        .link-user-profil { color: #2563eb; text-decoration: none; font-weight: bold; }
        .modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index: 1000; }
        .modal-content { background:white; padding:30px; border-radius:8px; width:550px; text-align:left; max-height: 85vh; overflow-y: auto; }
        .btn-konfirmasi { background:#198754; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; }
        .btn-batal { background:#6c757d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; margin-right:10px; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <h2 style="margin:0; color:#1e293b;">PORTAL KARIR</h2>
        <div>
            <?php if ($pelamar_id) : ?>
                <span style="margin-right:15px; color:#475569;">
                    Halo, <a href="profil_pelamar.php" class="link-user-profil"><?= htmlspecialchars($pelamar_nama); ?></a>
                </span>
                <a href="rekrutmen_lamaran.php" style="background:#198754; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold; margin-right:10px;">Data Lamaran Saya</a>
                <a href="logout_pelamar.php" style="background:#dc2626; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Keluar</a>
            <?php else : ?>
                <a href="login_pelamar.php" style="background:#2563eb; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Masuk Akun</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CONTAINER CARD LOWONGAN -->
    <div class="container">
        <h3 style="color:#1e293b; margin-bottom:25px;">Lowongan Magang Tersedia</h3>
        <div class="grid-lowongan">
            <?php
            $query_lowongan = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan");
            if (mysqli_num_rows($query_lowongan) > 0) {
                while ($row = mysqli_fetch_assoc($query_lowongan)) {
                    $nama_tampil = isset($row['judul_lowongan']) ? $row['judul_lowongan'] : 'Lowongan Magang';
                    $deskripsi   = isset($row['deskripsi']) ? $row['deskripsi'] : '';
                    $id_lowongan = $row['id'];
                    $sudah_melamar = in_array($id_lowongan, $lowongan_dilamar);
            ?>
                    <div class="card-lowongan">
                        <div>
                            <h3 style="margin: 0; color: #1e293b; font-size: 20px; font-weight: 600;"><?= htmlspecialchars($nama_tampil); ?></h3>
                            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-top: 15px; margin-bottom: 5px;"><?= htmlspecialchars($deskripsi); ?></p>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-detail" onclick="bukaDetail('<?= $id_lowongan; ?>')">Detail</button>
                            <?php if ($sudah_melamar) : ?>
                                <button type="button" class="btn-action btn-disabled" disabled>✔ Sudah Dilamar</button>
                            <?php else : ?>
                                <button type="button" class="btn-action btn-lamar" onclick="bukaPreview('<?= addslashes(htmlspecialchars($nama_tampil)); ?>', '<?= $id_lowongan; ?>')">Lamar</button>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php
                } 
            } else {
                echo "<p style='color:#64748b; text-align: center; width: 100%;'>Belum ada lowongan magang yang tersedia saat ini.</p>";
            }
            ?>
        </div> 
    </div>

    <!-- WINDOW MODAL DETAIL LOWONGAN (POP-UP DETAIL) -->
    <div id="modalDetailLowongan" class="modal">
        <div class="modal-content">
            <h3 id="detailJudul" style="margin-top: 0; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 22px;">-</h3>
            <div style="font-size: 14px; line-height: 1.6; color: #334155;">
                <div style="margin-bottom: 15px;"><strong style="color: #4338ca;">Deskripsi Pekerjaan:</strong><p id="detailDeskripsi" style="margin: 0;">-</p></div>
                <div style="margin-bottom: 15px;"><strong style="color: #4338ca;">Kualifikasi:</strong><p id="detailKualifikasi" style="margin: 0; white-space: pre-line;">-</p></div>
                <div style="margin-bottom: 15px;"><strong style="color: #4338ca;">Persyaratan Dokumen:</strong><p id="detailPersyaratan" style="margin: 0; white-space: pre-line;">-</p></div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between;">
                    <div><span style="font-size: 11px; color:#64748b; display:block;">MULAI</span><span id="detailTglMulai" style="font-weight:600;">-</span></div>
                    <div><span style="font-size: 11px; color:#64748b; display:block;">SELESAI</span><span id="detailTglSelesai" style="font-weight:600; color:#b91c1c;">-</span></div>
                    <div><span style="font-size: 11px; color:#64748b; display:block;">KUOTA</span><span id="detailKuota" style="font-weight:600;">-</span></div>
                </div>
            </div>
            <!-- Tombol Aksi Penutup Pop-up Detail -->
            <div style="margin-top: 25px; text-align: right;">
                <button type="button" class="btn-batal" onclick="document.getElementById('modalDetailLowongan').style.display='none'" style="margin: 0;">Tutup</button>
            </div>
        </div>
    </div>

    <!-- ==================== WINDOW MODAL PREVIEW DATA (POP-UP LAMAR) ==================== -->
    <div id="modalPreview" class="modal">
        <div class="modal-content" style="width: 550px; max-width: 95%;">
            <h3 style="margin-top: 0; text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; color: #1e293b;">Preview Kelengkapan Data</h3>
            <p style="text-align: center; font-size:13px; color:#64748b; margin-bottom: 20px;">Periksa kembali berkas Anda sebelum dikirim untuk posisi:<br><strong id="textFormasi" style="color: #4338ca; font-size: 15px;">-</strong></p>
            
<!-- I. BIODATA & PENDIDIKAN -->
<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
    <strong style="color:#4338ca; display:block; margin-bottom:8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">I. Biodata & Pendidikan</strong>
    
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">Nama Lengkap</span><span style="flex: 1; color: #1e293b;">: <strong><?= htmlspecialchars($data['nama_lengkap'] ?? '-'); ?></strong></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">NIK Pelamar</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['nik'] ?? '-'); ?></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">TTL</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['tempat_lahir'] ?? '-'); ?>, <?= isset($data['tanggal_lahir']) ? date('d-m-Y', strtotime($data['tanggal_lahir'])) : '-'; ?></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">Jenis Kelamin</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['jenis_kelamin'] ?? '-'); ?></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">Agama / Status</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['agama'] ?? '-'); ?> / <?= htmlspecialchars($data['status_sosial'] ?? '-'); ?></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">No. Telp / WA</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['no_telepon'] ?? '-'); ?></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">Domisili</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['kota'] ?? '-'); ?>, <?= htmlspecialchars($data['provinsi'] ?? '-'); ?></span></div>
    <div style="display: flex;"><span style="width: 140px; font-weight: bold; color: #475569;">Alamat Rumah</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['alamat'] ?? '-'); ?></span></div>
    
    <div style="display: flex; margin-top: 5px; padding-top: 5px; border-top: 1px dashed #cbd5e1;"><span style="width: 140px; font-weight: bold; color: #475569;">Pendidikan</span><span style="flex: 1; color: #1e293b;">: <?php if(!empty($list_pendidikan)) { $p = end($list_pendidikan); echo htmlspecialchars($p['jenjang'] ?? '-') . " - " . htmlspecialchars($p['institusi'] ?? '-'); } else { echo "-"; } ?></span></div>
</div>


            <!-- II. BERKAS DOKUMEN -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
                <strong style="color:#198754; display:block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">II. Lampiran Berkas Dokumen</strong>
                <?php if(!empty($list_berkas)) { foreach($list_berkas as $b) { if(!empty($b['nama_file'])) echo "• " . htmlspecialchars($b['jenis_berkas']) . " (<span style='color:#198754;'>✔ Terunggah</span>)<br>"; } } else { echo "<span style='color:#64748b; font-style:italic;'>Tidak ada berkas.</span>"; } ?>
            </div>

<!-- III. DATA STR -->
<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.8;">
    <strong style="color:#d97706; display: block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">III. Data STR Aktif</strong>
    <?php if(!empty($list_str)) : ?>
        <?php foreach($list_str as $s) : ?>
            <div style="margin-bottom: 4px;">
                • No. STR: <strong><?= htmlspecialchars($s['nomor_str']); ?></strong> 
                <?php if(!empty($s['file_str']) && trim($s['file_str']) != '') : ?>
                    <!-- Indikator jika file STR sudah diunggah oleh pelamar -->
                    <span style="color:#198754; font-weight: 500;">(✔ Terunggah)</span>
                <?php else : ?>
                    <span style="color:#dc3545; font-weight: 500;">(❌ File Belum Diunggah)</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <span style="color:#64748b; font-style:italic;">Tidak ada data STR.</span>
    <?php endif; ?>
</div>


            <!-- FORM KIRIM FINAL -->
            <form action="" method="POST" style="text-align: right; border-top: 2px solid #f1f5f9; padding-top: 15px; margin: 0;">
                <input type="hidden" id="inputLowonganId" name="lowongan_id" value="">
                <button type="button" class="btn-batal" onclick="document.getElementById('modalPreview').style.display='none'">Batal</button>
                <button type="submit" name="kirim_lamaran_final" class="btn-konfirmasi">Kirim Lamaran Sekarang</button>
            </form>
        </div>
    </div>

    <!-- ==================== JAVASCRIPT LOGIC POP-UP MODAL ==================== -->
    <script>
    // Handler Klik Tombol Detail: Tarik data lowongan secara real-time lewat AJAX
    function bukaDetail(idLowongan) {
        fetch('get_detail_lowongan.php?id=' + idLowongan)
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    document.getElementById('detailJudul').innerText = res.data.judul_lowongan;
                    document.getElementById('detailDeskripsi').innerText = res.data.deskripsi || 'Tidak ada deskripsi.';
                    document.getElementById('detailKualifikasi').innerText = res.data.kualifikasi || '-';
                    document.getElementById('detailPersyaratan').innerText = res.data.persyaratan || '-';
                    document.getElementById('detailKuota').innerText = (res.data.jumlah_kebutuhan || '0') + ' Orang';
                    document.getElementById('detailTglMulai').innerText = res.data.tanggal_mulai || '-';
                    document.getElementById('detailTglSelesai').innerText = res.data.tanggal_selesai || '-';
                    
                    document.getElementById('modalDetailLowongan').style.display = 'flex';
                } else {
                    alert('Gagal mengambil data: ' + res.message);
                }
            }).catch(err => alert('Gagal memuat detail lowongan. Periksa file get_detail_lowongan.php'));
    }

    // Handler Klik Tombol Lamar: Membuka ringkasan preview data profil pelamar
    function bukaPreview(namaTampil, idLowongan) {
        document.getElementById('textFormasi').innerText = namaTampil;
        document.getElementById('inputLowonganId').value = idLowongan;
        document.getElementById('modalPreview').style.display = 'flex';
    }

    // Klik di luar kotak modal untuk menutup otomatis
    window.onclick = function(event) {
        const mDetail = document.getElementById('modalDetailLowongan');
        const mPreview = document.getElementById('modalPreview');
        if (event.target == mDetail) mDetail.style.display = 'none';
        if (event.target == mPreview) mPreview.style.display = 'none';
    }
    </script>
</body>
</html>
