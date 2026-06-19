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

    <!-- I. BIODATA -->
    <div class="section-box">
        <span class="section-title">I. Informasi Biodata Pelamar</span>
        <div><strong>Nama Lengkap:</strong> <?= htmlspecialchars($data_user['nama_lengkap'] ?? '-'); ?></div>
        <div><strong>NIK:</strong> <?= htmlspecialchars($data_user['nik'] ?? '-'); ?></div>
        <div><strong>Alamat Lengkap:</strong> <?= htmlspecialchars($data_user['alamat'] ?? '-'); ?></div>
        <div><strong>Pendidikan Terakhir:</strong> 
            <?php 
            if (!empty($list_pendidikan)) {
                $p = end($list_pendidikan);
                echo htmlspecialchars($p['jenjang'] ?? $p['tingkat'] ?? '-') . " - " . htmlspecialchars($p['nama_sekolah'] ?? $p['institusi'] ?? '-');
            } else { echo "-"; }
            ?>
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

    <!-- III. DATA STR -->
    <div class="section-box">
        <span class="section-title" style="color: #d97706;">III. Registrasi STR</span>
        <?php if (!empty($list_str)) : ?>
            <?php foreach ($list_str as $str) : ?>
                <div>• No. STR: <strong><?= htmlspecialchars($str['nomor_str']); ?></strong> <small style="color:#64748b;">(Berlaku s/d: <?= date('d/m/Y', strtotime($str['tanggal_expired'])); ?>)</small></div>
            <?php endforeach; ?>
        <?php else : ?>
            <em style="color:#64748b;">Tidak menyertakan data STR.</em>
        <?php endif; ?>
    </div>

        <!-- IV. PENGALAMAN -->
    <div class="section-box">
        <span class="section-title">IV. Pengalaman Kerja</span>
        <?php if (!empty($list_pengalaman)) : ?>
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($list_pengalaman as $exp) : ?>
                    <?php 
                    // LOGIKA OTOMATIS: Mendeteksi nama kolom tahun agar bebas dari error Undefined Key
                    $masuk  = $exp['tahun_masuk'] ?? $exp['mulai'] ?? $exp['tahun'] ?? $exp['tgl_masuk'] ?? '';
                    $keluar = $exp['tahun_keluar'] ?? $exp['selesai'] ?? $exp['tgl_keluar'] ?? 'Sekarang';
                    
                    // Format tampilan tahun jika data ditemukan
                    $durasi = (!empty($masuk)) ? " ($masuk - $keluar)" : "";
                    ?>
                    <li>
                        <strong><?= htmlspecialchars($exp['perusahaan'] ?? $exp['nama_perusahaan'] ?? '-'); ?></strong> 
                        sebagai <em><?= htmlspecialchars($exp['jabatan'] ?? $exp['posisi'] ?? '-'); ?></em><?= $durasi; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <em style="color:#64748b;">Fresh Graduate (Belum memiliki riwayat kerja).</em>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
