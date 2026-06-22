<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. KONEKSI DATABASE SERVER PUSAT
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$lamaran_tahapan_id = $_GET['id'] ?? 0;
if (!$lamaran_tahapan_id) {
    die("Akses ilegal: Parameter ID Pelamar tidak ditemukan.");
}

// Sinkronisasi penilai_id otomatis
$cek_penilai_db = mysqli_query($conn, "SELECT id FROM penilai LIMIT 1");
if (mysqli_num_rows($cek_penilai_db) > 0) {
    $row_p = mysqli_fetch_assoc($cek_penilai_db);
    $penilai_id = $_SESSION['user_id'] ?? $row_p['id'];
} else {
    mysqli_query($conn, "INSERT INTO penilai (id, nama) VALUES (1, 'Tim Penilai Pusat')");
    $penilai_id = 1;
}

// Ambil info nama pelamar
$query_pelamar = mysqli_query($conn, "SELECT lt.id, p.nama_lengkap AS nama_pendaftar 
                                      FROM lamaran_tahapan lt
                                      JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id
                                      JOIN pelamar p ON rl.pelamar_id = p.id
                                      WHERE lt.id = '$lamaran_tahapan_id'");
$data_pelamar = mysqli_fetch_assoc($query_pelamar);

// Ambil daftar master tahapan seleksi untuk tab Chrome
$query_master_tahapan = mysqli_query($conn, "SELECT id, nama_tahapan FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1 ORDER BY id ASC");
$list_tabs = [];
while ($t = mysqli_fetch_assoc($query_master_tahapan)) {
    $list_tabs[] = $t;
}

// Set tab default pertama aktif saat halaman dimuat awal
$tab_default_aktif = $list_tabs[0]['id'] ?? 0;

$success_msg = "";
$error_msg   = "";

// =========================================================================
// 2. LOGIKA PROSES SIMPAN / UPDATE INDIVIDU TAHAPAN (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nilai          = number_format($_POST['nilai'], 2, '.', '');
    $catatan        = mysqli_real_escape_string($conn, $_POST['catatan']);
    $mst_tahapan_id = $_POST['mst_tahapan_id'] ?? $tab_default_aktif; 
    $tanggal        = date('Y-m-d H:i:s');

    // KUNCI UTAMA: Cek kecocokan berdasarkan ID Pelamar DAN ID tipe tesnya
    $cek_existing = mysqli_query($conn, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'");

    if (mysqli_num_rows($cek_existing) > 0) {
        $query_save = "UPDATE penilaian_tahapan SET 
                        penilai_id = '$penilai_id',
                        nilai = '$nilai', 
                        catatan = '$catatan', 
                        tanggal = '$tanggal',
                        updated_at = NOW()
                       WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'";
    } else {
        $query_save = "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, catatan, tanggal, created_at) 
                       VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', '$nilai', '$catatan', '$tanggal', NOW())";
    }

    if (mysqli_query($conn, $query_save)) {
        $success_msg = "Data penilaian tahapan berhasil disimpan!";
        $tab_default_aktif = $mst_tahapan_id; // Kunci posisi tab setelah save
    } else {
        $error_msg = "Gagal menyimpan penilaian: " . mysqli_error($conn);
    }
}

// Ambil nilai lama untuk tab yang sedang aktif
$query_nilai = mysqli_query($conn, "SELECT * FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$tab_default_aktif'");
$data_nilai  = mysqli_fetch_assoc($query_nilai);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Penilaian Tahapan</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8fafc; padding: 40px; }
        .container { max-width: 650px; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin: 0 auto; }
        h1 { font-size: 22px; margin-bottom: 5px; color: #0f172a; }
        .sub-title { color: #64748b; font-size: 14px; margin-bottom: 25px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        
        .chrome-tabs-container { display: flex; background-color: #e2e8f0; padding: 6px 6px 0 6px; border-radius: 12px 12px 0 0; gap: 4px; overflow-x: auto; margin-bottom: 20px; border-bottom: 1px solid #cbd5e1; }
        .chrome-tab { padding: 10px 16px; background-color: #f1f5f9; color: #475569; border-radius: 10px 10px 0 0; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
        .chrome-tab.active { background-color: #ffffff; color: #4f46e5; border: 1px solid #cbd5e1; border-bottom: none; position: relative; margin-bottom: -1px; padding-bottom: 11px; }

        .btn-submit { background-color: #4f46e5; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 10px; font-size: 15px; }
        .btn-submit:hover { background-color: #4338ca; }
        .btn-back { display: inline-block; text-decoration: none; color: #64748b; font-size: 14px; margin-bottom: 20px; }
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="container">
    <a href="lamaran_tahapan.php" class="btn-back">&larr; Kembali ke Daftar Progress</a>
    
    <h1>Input Penilaian Tahapan</h1>
    <div class="sub-title">Nama Pelamar: <strong style="color: #4f46e5;"><?= htmlspecialchars($data_pelamar['nama_pendaftar'] ?? 'Kandidat Magang'); ?></strong></div>

    <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?= $success_msg; ?></div><?php endif; ?>
    <?php if (!empty($error_msg)): ?><div class="alert alert-error"><?= $error_msg; ?></div><?php endif; ?>

    <form action="" method="POST">
        
        <div class="form-group">
            <label>Pilih Tahapan Seleksi</label>
            <div class="chrome-tabs-container">
                <?php foreach ($list_tabs as $index => $tab) : ?>
                    <div class="chrome-tab <?= $tab['id'] == $tab_default_aktif ? 'active' : ''; ?>" 
                         id="tab-control-<?= $tab['id']; ?>"
                         onclick="pilihTabChrome(this, '<?= $tab['id']; ?>')">
                        <?= htmlspecialchars($tab['nama_tahapan']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="mst_tahapan_id" id="input_mst_tahapan_id" value="<?= $tab_default_aktif; ?>">
        </div>

        <div class="form-group">
            <label for="nilai">Nilai Kompetensi (0.00 - 100.00)</label>
            <input type="number" name="nilai" id="nilai" step="0.01" min="0" max="100" class="form-control" placeholder="Contoh: 85.50" required value="<?= $data_nilai['nilai'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="catatan">Catatan / Rekomendasi Penilai</label>
            <textarea name="catatan" id="catatan" class="form-control" rows="5" placeholder="Tuliskan feedback hasil evaluasi teknis kandidat..." required><?= htmlspecialchars($data_nilai['catatan'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">Simpan Hasil Penilaian</button>
    </form>
</div>

<script>
function pilihTabChrome(elemenTab, idMasterTahapan) {
    var semuaTab = document.getElementsByClassName('chrome-tab');
    for (var i = 0; i < semuaTab.length; i++) {
        semuaTab[i].classList.remove('active');
    }
    elemenTab.classList.add('active');
    document.getElementById('input_mst_tahapan_id').value = idMasterTahapan;
    
    // Panggil fungsi AJAX bawaan Anda untuk mengganti isi nilai dan catatan secara real-time
    var urlParams = new URLSearchParams(window.location.search);
    var idLamaran = urlParams.get('id');

    document.getElementById('nilai').value = "";
    document.getElementById('catatan').value = "Memuat data...";

    fetch('get_nilai_tahapan.php?id_lamaran=' + idLamaran + '&id_mst_tahapan=' + idMasterTahapan)
        .then(response => response.json())
        .then(data => {
            document.getElementById('nilai').value = data.nilai;
            document.getElementById('catatan').value = data.catatan;
        })
        .catch(error => {
            document.getElementById('catatan').value = "";
        });
}
</script>

</body>
</html>
