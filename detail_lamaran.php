<?php 
session_start(); 

// 1. KONEKSI DATABASE
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$pelamar_id = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$lamaran_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$pelamar_id || !$lamaran_id) {
    echo "<script>alert('Akses tidak valid!'); window.location.href='rekrutmen_lamaran.php';</script>";
    exit;
}

// 2. AMBIL DATA DETAIL LAMARAN & LOWONGAN
$query_lamaran = mysqli_query($koneksi, "SELECT l.*, lw.* FROM rekrutmen_lamaran l JOIN rekrutmen_lowongan lw ON l.lowongan_id = lw.id WHERE l.id = $lamaran_id AND l.pelamar_id = $pelamar_id");
$data_lamaran  = mysqli_fetch_assoc($query_lamaran);

if (!$data_lamaran) {
    echo "<script>alert('Data lamaran tidak ditemukan!'); window.location.href='rekrutmen_lamaran.php';</script>";
    exit;
}

// Otomatis deteksi kolom nama lowongan
$nama_lowongan_tampil = $data_lamaran['nama_lowongan'] ?? $data_lamaran['nama'] ?? 'Lowongan Magang';

// 3. AMBIL DATA BIODATA, PENDIDIKAN, BERKAS, STR, PENGALAMAN SAAT INI
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data_user  = mysqli_fetch_assoc($query_user);

$list_pendidikan = [];
$query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
if ($query_pend) { while ($r = mysqli_fetch_assoc($query_pend)) { $list_pendidikan[] = $r; } }

$list_berkas = [];
$query_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
if ($query_bk) { while ($r = mysqli_fetch_assoc($query_bk)) { $list_berkas[] = $r; } }

$list_str = [];
$query_s = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
if ($query_s) { while ($r = mysqli_fetch_assoc($query_s)) { $list_str[] = $r; } }

$list_pengalaman = [];
$query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id");
if ($query_exp) { while ($r = mysqli_fetch_assoc($query_exp)) { $list_pengalaman[] = $r; } }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Lamaran Saya</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header-page { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; }
        .btn-kembali { background: #6c757d; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: bold; }
        .section-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.8; }
        .section-title { font-weight: bold; color: #4338ca; border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-page">
        <h2 style="margin:0; color:#1e293b;">Rincian Berkas Lamaran</h2>
        <a href="rekrutmen_lamaran.php" class="btn-kembali">← Kembali</a>
    </div>

    <div style="margin-bottom: 25px; font-size: 15px;">
        Posisi Dilamar: <strong style="color: #4338ca;"><?= htmlspecialchars($nama_lowongan_tampil); ?></strong><br>
        Status Aplikasi: <strong style="color: #d97706;"><?= htmlspecialchars($data_lamaran['status']); ?></strong>
    </div>

<!-- I. Informasi Biodata Pelamar (Menggunakan Variabel $data_user Sesuai Backend PHP Anda) -->
<div class="card-detail" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px;">
    <h3 style="margin-top: 0; color: #4338ca; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 16px;">I. Informasi Biodata Pelamar</h3>
    
    <div style="font-size: 14px; line-height: 2.0; color: #334155; margin-top: 15px;">
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Nama Lengkap</span><span style="flex: 1; color: #1e293b;">: <strong><?= htmlspecialchars($data_user['nama_lengkap'] ?? '-'); ?></strong></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">NIK</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['nik'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Tempat, Tgl Lahir</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['tempat_lahir'] ?? '-'); ?>, <?= isset($data_user['tanggal_lahir']) ? date('d-m-Y', strtotime($data_user['tanggal_lahir'])) : '-'; ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Jenis Kelamin</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['jenis_kelamin'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Agama</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['agama'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Status Pernikahan</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['status_sosial'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">No. Telepon / WA</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['no_telepon'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Email</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['email'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Kota / Provinsi</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['kota'] ?? '-'); ?>, <?= htmlspecialchars($data_user['provinsi'] ?? '-'); ?></span></div>
        <div style="display: flex;"><span style="width: 160px; font-weight: bold; color: #475569;">Alamat Lengkap</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data_user['alamat'] ?? '-'); ?></span></div>
        <div style="display: flex; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1;">
            <span style="width: 160px; font-weight: bold; color: #475569;">Pendidikan Terakhir</span>
            <span style="flex: 1; color: #1e293b;">: 
                <?php 
                if (!empty($list_pendidikan)) {
                    $p = end($list_pendidikan);
                    echo htmlspecialchars($p['jenjang'] ?? '-') . " - " . htmlspecialchars($p['institusi'] ?? '-');
                } else { echo "-"; }
                ?>
            </span>
        </div>
    </div>
</div>


    <!-- II. BERKAS DOKUMEN -->
    <div class="section-box">
        <span class="section-title" style="color: #198754;">II. Lampiran Berkas Fisik</span>
        <?php if (!empty($list_berkas)) : ?>
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($list_berkas as $bk) : ?>
                    <?php if(!empty($bk['nama_file'])) : ?>
                        <li>
                            <?= htmlspecialchars($bk['jenis_berkas']); ?> 
                            <a href="uploads/<?= $bk['nama_file']; ?>" target="_blank" style="color:#0284c7; text-decoration:none; margin-left:10px;">[👁 Lihat File]</a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <em style="color:#64748b;">Tidak ada lampiran dokumen berkas.</em>
        <?php endif; ?>
    </div>

<!-- III. Registrasi STR (Perbaikan Fitur Lihat Berkas Dokumen STR) -->
<div class="card-detail" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px;">
    <h3 style="margin-top: 0; color: #d97706; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 16px;">III. Registrasi STR</h3>
    
    <div style="font-size: 14px; color: #334155; margin-top: 15px;">
        <?php if (!empty($list_str)) : ?>
            <?php foreach ($list_str as $str) : ?>
                <div style="margin-bottom: 10px; line-height: 1.8;">
                    • No. STR: <strong><?= htmlspecialchars($str['nomor_str']); ?></strong> 
                    <?php if (!empty($str['tanggal_expired'])) : ?>
                        <span style="color: #64748b; font-size: 12px;">(Berlaku s/d: <?= date('d/m/Y', strtotime($str['tanggal_expired'])); ?>)</span>
                    <?php endif; ?>
                    
                    <!-- LOGIKA UNTUK MENAMPILKAN LINK LIHAT FILE STR -->
                    <?php if (!empty($str['file_str']) && trim($str['file_str']) != '') : ?>
                        <span style="margin-left: 10px;">
                            [ <a href="uploads/<?= htmlspecialchars($str['file_str']); ?>" target="_blank" style="color: #0d6efd; text-decoration: none; font-weight: bold;">👁 Lihat Berkas STR</a> ]
                        </span>
                    <?php else : ?>
                        <span style="color: #94a3b8; font-size: 12px; font-style: italic; margin-left: 10px;">(File tidak diunggah)</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <span style="color: #64748b; font-style: italic;">Tidak ada data STR pendaftaran yang terlampir.</span>
        <?php endif; ?>
    </div>
</div>

<!-- IV. Pengalaman Kerja (Perbaikan Kolom Alasan Keluar) -->
<div class="card-detail" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px;">
    <h3 style="margin-top: 0; color: #4338ca; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 16px;">IV. Pengalaman Kerja</h3>
    
    <div style="font-size: 14px; color: #334155; margin-top: 15px;">
        <?php if (!empty($list_pengalaman)) : ?>
            <ul style="margin: 0; padding-left: 20px; line-height: 2.0;">
                <?php foreach ($list_pengalaman as $exp) : ?>
                    <li style="margin-bottom: 15px; list-style-type: square;">
                        <strong><?= htmlspecialchars($exp['jabatan'] ?? '-'); ?></strong> di <strong><?= htmlspecialchars($exp['perusahaan'] ?? '-'); ?></strong>
                        <div style="font-size: 13px; color: #64748b; padding-left: 5px; line-height: 1.6;">
                            <div>• Periode Kerja: <?= !empty($exp['mulai_kerja']) ? date('d/m/Y', strtotime($exp['mulai_kerja'])) : '-'; ?> s/d <?= !empty($exp['selesai_kerja']) ? date('d/m/Y', strtotime($exp['selesai_kerja'])) : 'Sekarang'; ?></div>
                            <!-- PERBAIKAN UTAMA: Memastikan pemanggilan menggunakan key 'alasan_keluar' sesuai database -->
                            <div>• Alasan Keluar: <span style="color: #1e293b; font-style: italic; font-weight: 500;"><?= !empty($exp['alasan_keluar']) ? htmlspecialchars($exp['alasan_keluar']) : '-'; ?></span></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <span style="color: #64748b; font-style: italic;">Tidak ada riwayat pengalaman kerja yang terlampir.</span>
        <?php endif; ?>
    </div>
</div>

</div>

</body>
</html>
