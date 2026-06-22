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

// Tangkap ID parameter dari URL browser (?id=52)
$lamaran_tahapan_id = $_GET['id'] ?? 0;
if (!$lamaran_tahapan_id) {
    die("Akses ilegal: Parameter ID tidak ditemukan.");
}

// Proteksi ID Penilai otomatis (Anti Error Foreign Key)
$ambil_penilai = mysqli_query($conn, "SELECT id FROM penilai LIMIT 1");
if ($row_p = mysqli_fetch_assoc($ambil_penilai)) {
    $penilai_id = $_SESSION['user_id'] ?? $row_p['id'];
} else {
    mysqli_query($conn, "INSERT INTO penilai (id, nama) VALUES (1, 'Tim Penilai Pusat')");
    $penilai_id = 1;
}

// Ambil Informasi Detail Pelamar dan ID Lowongan Kerja-nya
$query_pelamar = mysqli_query($conn, "
    SELECT lt.id AS id_tahapan, rl.id AS id_lamaran, rl.lowongan_id, p.nama_lengkap AS nama_pendaftar 
    FROM lamaran_tahapan lt
    JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id
    JOIN pelamar p ON rl.pelamar_id = p.id
    WHERE lt.id = '$lamaran_tahapan_id'
    LIMIT 1
");
$data_pelamar = mysqli_fetch_assoc($query_pelamar);

$id_lamaran_asli = $data_pelamar['id_lamaran'] ?? 0;
$id_lowongan     = $data_pelamar['lowongan_id'] ?? 0;

// Ambil daftar master tahapan seleksi untuk Tab Chrome
$query_master_tahapan = mysqli_query($conn, "SELECT id, nama_tahapan FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1 ORDER BY id ASC");
$list_tabs = [];
while ($t = mysqli_fetch_assoc($query_master_tahapan)) {
    $list_tabs[] = $t;
}

// Set tab default pertama aktif saat halaman dimuat awal
$tab_default_aktif = $_POST['mst_tahapan_id'] ?? ($list_tabs[0]['id'] ?? 0);

$success_msg = "";
$error_msg   = "";

// =========================================================================
// 2. LOGIKA PROSES SIMPAN / UPDATE INDIVIDU TAHAPAN & AUTO STATUS (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Penanganan Aksi Tombol Lewati Nama Ini
    if (isset($_POST['action_lewati'])) {
        $query_skip = "UPDATE lamaran_tahapan SET status = 'Skip', tanggal_mulai = NOW() WHERE id = '$lamaran_tahapan_id'";
        if (mysqli_query($conn, $query_skip)) {
            $success_msg = "Tahapan seleksi berhasil dilewati (Skip)!";
            if ($id_lamaran_asli > 0) {
                try {
                    @mysqli_query($conn, "UPDATE rekrutmen_lamaran SET status = 'Skip' WHERE id = '$id_lamaran_asli'");
                } catch (mysqli_sql_exception $e) {
                    @mysqli_query($conn, "UPDATE rekrutmen_lamaran SET status = 'Proses' WHERE id = '$id_lamaran_asli'");
                }
            }
        } else {
            $error_msg = "Gagal melewati tahapan: " . mysqli_error($conn);
        }
    } else {
        // Penanganan Simpan / Update Nilai Biasa atau dari Auto-Save
        $nilai          = isset($_POST['nilai']) && $_POST['nilai'] !== '' ? number_format($_POST['nilai'], 2, '.', '') : null;
        $catatan        = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');
        $mst_tahapan_id = $_POST['mst_tahapan_id'] ?? $tab_default_aktif; 
        $tanggal        = date('Y-m-d H:i:s');

        if ($nilai !== null) {
            $q_get_low = mysqli_query($conn, "SELECT rl.lowongan_id FROM lamaran_tahapan lt JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id WHERE lt.id = '$lamaran_tahapan_id' LIMIT 1");
            $d_get_low = mysqli_fetch_assoc($q_get_low);
            $id_lowongan_asli_form = $d_get_low['lowongan_id'] ?? 0;

            $cek_existing = mysqli_query($conn, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'");

            if (mysqli_num_rows($cek_existing) > 0) {
                $query_save = "UPDATE penilaian_tahapan SET penilai_id = '$penilai_id', nilai = '$nilai', catatan = '$catatan', tanggal = '$tanggal', updated_at = NOW() WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'";
            } else {
                $query_save = "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, catatan, tanggal, created_at) VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', '$nilai', '$catatan', '$tanggal', NOW())";
            }

            if (mysqli_query($conn, $query_save)) {
                $success_msg = "Data penilaian tahapan berhasil disimpan!";
                $tab_default_aktif = $mst_tahapan_id;

                // Engine hitung status otomatis
                $q_total_alur = mysqli_query($conn, "SELECT COUNT(*) AS total FROM lowongan_tahapan WHERE lowongan_id = '$id_lowongan_asli_form'");
                $d_total_alur = mysqli_fetch_assoc($q_total_alur);
                $total_alur_wajib = $d_total_alur['total'] ?? count($list_tabs);

                $q_total_isi = mysqli_query($conn, "SELECT COUNT(*) AS total FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id'");
                $d_total_isi = mysqli_fetch_assoc($q_total_isi);
                $total_sudah_dinilai = $d_total_isi['total'] ?? 0;

                $q_cek_kriteria = mysqli_query($conn, "SELECT pt.nilai, lt.minimal_nilai FROM penilaian_tahapan pt JOIN lowongan_tahapan lt ON lt.tahapan_id = pt.mst_tahapan_id WHERE pt.lamaran_tahapan_id = '$lamaran_tahapan_id' AND lt.lowongan_id = '$id_lowongan_asli_form'");

                $ada_yang_gagal = false;
                while ($koreksi = mysqli_fetch_assoc($q_cek_kriteria)) {
                    if (floatval($koreksi['nilai']) < floatval($koreksi['minimal_nilai'])) {
                        $ada_yang_gagal = true;
                        break;
                    }
                }

                $status_final = "Pending"; 
                if ($ada_yang_gagal) {
                    $status_final = "Tolak"; 
                } else {
                    if ($total_sudah_dinilai < $total_alur_wajib) {
                        $status_final = "Pending";
                    } else {
                        $status_final = "Lulus";
                    }
                }

                $status_alt_tahapan = ($status_final == 'Tolak') ? 'Tidak Lulus' : (($status_final == 'Pending') ? 'Proses' : $status_final);
                
                try {
                    @mysqli_query($conn, "UPDATE lamaran_tahapan SET status = '$status_final', tanggal_mulai = NOW() WHERE id = '$lamaran_tahapan_id'");
                } catch (Exception $e) {
                    @mysqli_query($conn, "UPDATE lamaran_tahapan SET status = '$status_alt_tahapan', tanggal_mulai = NOW() WHERE id = '$lamaran_tahapan_id'");
                }
                
                if ($id_lamaran_asli > 0) {
                    try {
                        $status_master_induk = ($status_final == 'Pending') ? 'Proses' : (($status_final == 'Tolak') ? 'Tidak Lulus' : $status_final);
                        @mysqli_query($conn, "UPDATE rekrutmen_lamaran SET status = '$status_master_induk' WHERE id = '$id_lamaran_asli'");
                    } catch (mysqli_sql_exception $e) {
                        @mysqli_query($conn, "UPDATE rekrutmen_lamaran SET status = 'Proses' WHERE id = '$id_lamaran_asli'");
                    }
                }
            } else {
                $error_msg = "Gagal menyimpan penilaian: " . mysqli_error($conn);
            }
        }
    }
}

// AMBIL DATA NILAI YANG AKAN DITAMPILKAN KE FORM SAAT REFRESH
$query_nilai = mysqli_query($conn, "SELECT * FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$tab_default_aktif'");
$data_nilai  = mysqli_fetch_assoc($query_nilai);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Penilaian Tahapan</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8fafc; padding: 40px; margin: 0; }
        .container { max-width: 650px; background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); margin: 0 auto; display: block; }
        
        h1 { font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; letter-spacing: -0.5px; }
        
        /* DIOPTIMALKAN: Mengurangi margin bawah sub-title agar elemen di bawahnya merapat naik */
        .sub-title { color: #64748b; font-size: 14px; margin-bottom: 12px; }
        
        form { display: flex; flex-direction: column; width: 100%; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; width: 100%; box-sizing: border-box; }
        
        label { display: block; font-weight: 700; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; box-sizing: border-box; background-color: #f8fafc; outline: none; font-weight: 600; color: #1e293b; }
        .form-control:focus { border-color: #4f46e5; background-color: #fff; }
        
        .chrome-tabs-container { display: flex; background-color: #e2e8f0; padding: 6px 6px 0 6px; border-radius: 12px 12px 0 0; gap: 4px; overflow-x: auto; margin-bottom: 15px; border-bottom: 1px solid #cbd5e1; width: 100%; box-sizing: border-box; scrollbar-width: none; -ms-overflow-style: none; }
        .chrome-tabs-container::-webkit-scrollbar { display: none; width: 0; height: 0; }
        
        .chrome-tab { padding: 10px 18px; background-color: #f1f5f9; color: #475569; border-radius: 10px 10px 0 0; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.15s; border: 1px solid transparent; border-bottom: none; }
        .chrome-tab:hover { background-color: #f8fafc; color: #1e293b; }
        .chrome-tab.active { background-color: #ffffff; color: #4f46e5; border-color: #cbd5e1; position: relative; margin-bottom: -1px; padding-bottom: 11px; }

        .btn-submit { background-color: #4f46e5; color: white; padding: 14px; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; width: 100%; margin-top: 10px; font-size: 14px; transition: background 0.2s; }
        .btn-submit:hover { background-color: #4338ca; }
        .btn-back { display: inline-block; text-decoration: none; color: #64748b; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        
        .alert { padding: 12px 16px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        .link-text-skip {
            background: none !important;
            background-color: transparent !important;
            border: none !important;
            border-width: 0 !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 0 -2px 0 !important;
            color: #4f46e5; 
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            display: inline-block;
            transition: color 0.15s ease-in-out;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .link-text-skip:hover {
            color: #4338ca !important;
            text-decoration: underline !important;
        }
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
        <!-- OPSI KLIK TEKS LEWATI MURNI -->
        <div style="text-align: left; margin-bottom: 0;">
            <button type="submit" name="action_lewati" value="1" onclick="return confirm('Apakah Anda yakin ingin melewati tahapan seleksi pelamar ini?')" class="link-text-skip">
                Lewati Nama Ini &rarr;
            </button>
        </div>

        <div class="form-group">
            <label>Pilih Tahapan Seleksi</label>
            <div class="chrome-tabs-container">
                <?php foreach ($list_tabs as $tab) : ?>
                    <div class="chrome-tab <?= $tab['id'] == $tab_default_aktif ? 'active' : ''; ?>" 
                         onclick="pilihTabChrome(this, '<?= $tab['id']; ?>')">
                        <?= htmlspecialchars($tab['nama_tahapan']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="mst_tahapan_id" id="input_mst_tahapan_id" value="<?= $tab_default_aktif; ?>">
        </div>

        <div class="form-group">
            <label for="nilai">Nilai Kompetensi (0.00 - 100.00)</label>
            <input type="number" name="nilai" id="nilai" step="0.01" min="0" max="100" class="form-control" placeholder="Contoh: 85.50" required value="<?= htmlspecialchars($data_nilai['nilai'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="catatan">Catatan / Rekomendasi Penilai</label>
            <textarea name="catatan" id="catatan" class="form-control" rows="5" placeholder="Tuliskan feedback hasil evaluasi teknis..." required><?= htmlspecialchars($data_nilai['catatan'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">Simpan Hasil Penilaian</button>
    </form>
</div>

<script>
function pilihTabChrome(elemenTab, idMasterTahapan) {
    var idMasterTahapanLama = document.getElementById('input_mst_tahapan_id').value;
    var nilaiLama           = document.getElementById('nilai').value;
    var catatanLama         = document.getElementById('catatan').value;
    
    if (nilaiLama !== "") {
        var formData = new FormData();
        formData.append('nilai', nilaiLama);
        formData.append('catatan', catatanLama);
        formData.append('mst_tahapan_id', idMasterTahapanLama);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).catch(error => console.error('Gagal Auto-Save:', error));
    }

    var semuaTab = document.getElementsByClassName('chrome-tab');
    for (var i = 0; i < semuaTab.length; i++) {
        semuaTab[i].classList.remove('active');
    }
    elemenTab.classList.add('active');
    document.getElementById('input_mst_tahapan_id').value = idMasterTahapan;
    
    document.getElementById('nilai').value = "";
    document.getElementById('catatan').value = "Memuat data...";

    fetch('get_nilai_tahapan.php?id_lamaran=<?= $lamaran_tahapan_id; ?>&id_mst_tahapan=' + idMasterTahapan)
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
