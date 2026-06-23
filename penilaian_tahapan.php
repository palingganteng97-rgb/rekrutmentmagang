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

// Tangkap parameter ID dari URL (?id=...)
$lamaran_tahapan_id = $_GET['id'] ?? 0;
if (!$lamaran_tahapan_id) {
    die("Akses ilegal: Parameter ID Pelamar tidak ditemukan.");
}

// Inisialisasi data login penilai/admin secara aman
$cek_penilai_db = mysqli_query($conn, "SELECT id FROM penilai LIMIT 1");
if (mysqli_num_rows($cek_penilai_db) > 0) {
    $row_p = mysqli_fetch_assoc($cek_penilai_db);
    $penilai_id = $_SESSION['user_id'] ?? $row_p['id'];
} else {
    mysqli_query($conn, "INSERT INTO penilai (id, nama) VALUES (1, 'Tim Penilai Pusat')");
    $penilai_id = 1;
}

// =========================================================================
// 2. QUERY UTAMA: AMBIL DATA BIODATA LENGKAP PELAMAR
// =========================================================================
$query_pelamar = mysqli_query($conn, "SELECT lt.id AS id_tahapan, 
                                             p.*, 
                                             p.id AS id_pelamar_murni,
                                             p.nama_lengkap AS nama_pendaftar,
                                             rl.id AS id_lamaran_asli
                                      FROM lamaran_tahapan lt
                                      JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id
                                      JOIN pelamar p ON rl.pelamar_id = p.id
                                      WHERE lt.id = '$lamaran_tahapan_id' LIMIT 1");
$data_pelamar = mysqli_fetch_assoc($query_pelamar);

// -------------------------------------------------------------------------
// LOGIKA BARU: INTERSEPTOR PENUTUPAN TAB / NAVIGATOR BEACON
// -------------------------------------------------------------------------
if (isset($_POST['action_meninggalkan_halaman'])) {
    $id_target_tahap = intval($_POST['lamaran_tahapan_id']);
    
    // 1. Cek berapa jumlah opsi tab master seleksi yang ada
    $query_master_tahapan = mysqli_query($conn, "SELECT id FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1");
    $total_tahapan_wajib = mysqli_num_rows($query_master_tahapan);

    // 2. Cek berapa banyak data yang SUDAH TERSIMPAN BENAR-BENAR di database saat ini
    $query_hitung_isi = mysqli_query($conn, "SELECT status_tahap FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$id_target_tahap'");
    $total_terisi = mysqli_num_rows($query_hitung_isi);

    $ada_tidak_lulus = false;
    $ada_skip        = false;

    while ($cek_skor = mysqli_fetch_assoc($query_hitung_isi)) {
        if ($cek_skor['status_tahap'] == 'Tidak Lulus') { $ada_tidak_lulus = true; }
        if ($cek_skor['status_tahap'] == 'Dilewati') { $ada_skip = true; }
    }

    // 3. Ambil keputusan status: Jika belum dinilai penuh semua opsi tab, kembalikan ke PENDING
    if ($total_terisi < $total_tahapan_wajib) {
        $status_pulang = "Pending";
    } else {
        if ($ada_tidak_lulus) { $status_pulang = "Tidak Lulus"; }
        elseif ($ada_skip) { $status_pulang = "Skip"; }
        else { $status_pulang = "Lulus"; }
    }

    // 4. Update status ke database dan langsung matikan proses (Bypass render HTML)
    mysqli_query($conn, "UPDATE lamaran_tahapan SET status = '$status_pulang' WHERE id = '$id_target_tahap'");
    exit; // Berhenti di sini karena ini request latar belakang dari Beacon API
}

// JALUR NORMAL: Jika halaman dibuka biasa oleh penilai, ubah status ke 'Proses'
if ($data_pelamar) {
    mysqli_query($conn, "UPDATE lamaran_tahapan SET status = 'Proses' WHERE id = '$lamaran_tahapan_id'");
}

// =========================================================================
// 3. AMBIL DATA DETAIL LAMPIRAN BERKAS & RIWAYAT PENGALAMAN KERJA
// =========================================================================
$list_berkas     = ['ijazah' => '', 'str' => '']; 
$list_pengalaman = []; 

if ($data_pelamar && !empty($data_pelamar['id_pelamar_murni'])) {
    $id_pelamar_target = $data_pelamar['id_pelamar_murni'];

    // Tarik File IJAZAH/STR dari tabel pelamar_berkas
    $query_berkas = mysqli_query($conn, "SELECT * FROM pelamar_berkas WHERE pelamar_id = '$id_pelamar_target'");
    if ($query_berkas && mysqli_num_rows($query_berkas) > 0) {
        while ($row_bk = mysqli_fetch_assoc($query_berkas)) {
            $jenis_clean = strtolower(trim($row_bk['nama_berkas'] ?? $row_bk['jenis_berkas'] ?? ''));
            $file_asli   = $row_bk['file_berkas'] ?? $row_bk['nama_file'] ?? $row_bk['file'] ?? '';

            if (stripos($jenis_clean, 'ijazah') !== false) {
                $list_berkas['ijazah'] = $file_asli;
            }
            if (stripos($jenis_clean, 'str') !== false) {
                $list_berkas['str'] = $file_asli;
            }
        }
    }

    // Tarik File STR Cadangan dari tabel khusus pelamar_str
    if (empty($list_berkas['str'])) {
        $query_tabel_str = mysqli_query($conn, "SELECT * FROM pelamar_str WHERE pelamar_id = '$id_pelamar_target' LIMIT 1");
        if ($query_tabel_str && mysqli_num_rows($query_tabel_str) > 0) {
            $row_str = mysqli_fetch_assoc($query_tabel_str);
            foreach ($row_str as $nama_kolom => $isi_kolom) {
                if (is_string($isi_kolom) && preg_match('/\.(pdf|jpg|jpeg|png)$/i', trim($isi_kolom))) {
                    $list_berkas['str'] = trim($isi_kolom);
                    break; 
                }
            }
        }
    }

    // Tarik Riwayat Kerja dari tabel pelamar_pengalaman
    $query_kerja = mysqli_query($conn, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = '$id_pelamar_target' ORDER BY id DESC");
    if ($query_kerja && mysqli_num_rows($query_kerja) > 0) {
        while ($row_exp = mysqli_fetch_assoc($query_kerja)) {
            $row_exp['jabatan']    = $row_exp['jabatan'] ?? $row_exp['posisi'] ?? '-';
            $row_exp['perusahaan'] = $row_exp['perusahaan'] ?? $row_exp['nama_perusahaan'] ?? $row_exp['nama_instansi'] ?? '-';
            $list_pengalaman[]     = $row_exp;
        }
    }
}

// =========================================================================
// 4. MASTER KONTROL TAB SELEKSI & LOGIKA BERPINDAH OPSI (SINKRONISASI GET)
// =========================================================================
$query_master_tahapan = mysqli_query($conn, "SELECT id, nama_tahapan FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1 ORDER BY id ASC");
$list_tabs = [];
while ($t = mysqli_fetch_assoc($query_master_tahapan)) {
    $list_tabs[] = $t;
}

$tab_default_aktif = $list_tabs[0]['id'] ?? 0;

// PERBAIKAN UTAMA: Mengutamakan GET dari URL agar tab visual ikut beralih aktif saat auto-save dijalankan
$mst_tahapan_id_aktif = $_GET['tahapan_id'] ?? $_POST['mst_tahapan_id'] ?? $tab_default_aktif;
$tab_default_aktif    = $mst_tahapan_id_aktif;

$success_msg = "";
$error_msg   = "";

// =========================================================================
// 5. LOGIKA PROSES SIMPAN / UPDATE EVALUASI TAHAPAN (POST HANDLER)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mst_tahapan_id = $_POST['mst_tahapan_id'] ?? $tab_default_aktif; 
    $tanggal        = date('Y-m-d H:i:s');

    // -------------------------------------------------------------------------
    // PERBAIKAN UTAMA: JIKA AKSI LEWATI DIKLIK -> LANGSUNG UPDATE SKIP & PULANG
    // -------------------------------------------------------------------------
    if (isset($_POST['aksi_lewati'])) {
        $status_individu = "Dilewati";
        $catatan = !empty($_POST['catatan']) ? "'" . mysqli_real_escape_string($conn, $_POST['catatan']) . "'" : "'Tahapan dilewati oleh penilai.'";

        // 1. Simpan history dilewati ke tabel detail penilaian_tahapan (agar terekam)
        $cek_existing = mysqli_query($conn, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'");
        if (mysqli_num_rows($cek_existing) > 0) {
            mysqli_query($conn, "UPDATE penilaian_tahapan SET penilai_id = '$penilai_id', nilai = NULL, status_tahap = '$status_individu', catatan = $catatan, tanggal = '$tanggal', updated_at = NOW() WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'");
        } else {
            mysqli_query($conn, "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, status_tahap, catatan, tanggal, created_at) VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', NULL, '$status_individu', $catatan, '$tanggal', NOW())");
        }

        // 2. TEMBAK LANGSUNG: Paksa status di tabel induk lamaran_tahapan menjadi 'Skip'
        mysqli_query($conn, "UPDATE lamaran_tahapan SET status = 'Skip' WHERE id = '$lamaran_tahapan_id'");

        // 3. ALIKHAN HALAMAN: Langsung pulangkan penilai ke halaman daftar progress utama
        echo "<script>alert('⏭️ Tahapan berhasil dilewati! Status progress pelamar diubah menjadi SKIP.'); window.location.href='lamaran_tahapan.php';</script>";
        exit;
    } 
    
    // --- JALUR NORMAL: JIKA TOMBOL SIMPAN HASIL PENILAIAN DIKLIK ---
    else {
        $nilai_input = floatval($_POST['nilai'] ?? 0);
        $nilai_db = "'" . number_format($nilai_input, 2, '.', '') . "'";
        $catatan = "'" . mysqli_real_escape_string($conn, $_POST['catatan'] ?? '') . "'";
        $status_individu = ($nilai_input >= 75.00) ? "Lulus" : "Tidak Lulus";

        $cek_existing = mysqli_query($conn, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'");

        if (mysqli_num_rows($cek_existing) > 0) {
            $query_save = "UPDATE penilaian_tahapan SET penilai_id = '$penilai_id', nilai = $nilai_db, status_tahap = '$status_individu', catatan = $catatan, tanggal = '$tanggal', updated_at = NOW() WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'";
        } else {
            $query_save = "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, status_tahap, catatan, tanggal, created_at) VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', $nilai_db, '$status_individu', $catatan, '$tanggal', NOW())";
        }

        if (mysqli_query($conn, $query_save)) {
            $success_msg = "Data penilaian tahapan berhasil disimpan dengan status: " . strtoupper($status_individu);

            // Hitung ulang status otomatis untuk halaman depan (Pending / Lulus / Tidak Lulus)
            $total_tahapan_wajib = count($list_tabs);
            $query_hitung_isi = mysqli_query($conn, "SELECT status_tahap FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id'");
            $total_terisi = mysqli_num_rows($query_hitung_isi);

            $ada_tidak_lulus = false;
            $ada_skip        = false;

            while ($cek_skor = mysqli_fetch_assoc($query_hitung_isi)) {
                if ($cek_skor['status_tahap'] == 'Tidak Lulus') { $ada_tidak_lulus = true; }
                if ($cek_skor['status_tahap'] == 'Dilewati') { $ada_skip = true; }
            }

            if ($total_terisi < $total_tahapan_wajib) {
                $status_final_induk = "Pending";
            } else {
                if ($ada_tidak_lulus) { $status_final_induk = "Tidak Lulus"; }
                elseif ($ada_skip) { $status_final_induk = "Skip"; }
                else { $status_final_induk = "Lulus"; }
            }

            mysqli_query($conn, "UPDATE lamaran_tahapan SET status = '$status_final_induk' WHERE id = '$lamaran_tahapan_id'");
        } else {
            $error_msg = "Gagal menyimpan data penilaian: " . mysqli_error($conn);
        }
    }
}

// =========================================================================
// 6. FIX TOTAL: AMBIL DATA NILAI INDIVIDU BERDASARKAN TAB SELEKSI AKTIF
// =========================================================================
$data_nilai = [
    'nilai' => '',
    'status_tahap' => '',
    'catatan' => ''
];

if (!empty($lamaran_tahapan_id) && !empty($mst_tahapan_id_aktif)) {
    // Ambil data nilai yang BENAR-BENAR COCOK dengan ID tahapan yang sedang dibuka
    $query_baca_nilai = mysqli_query($conn, "SELECT * FROM penilaian_tahapan 
                                             WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' 
                                             AND mst_tahapan_id = '$mst_tahapan_id_aktif' LIMIT 1");
                                             
    if ($query_baca_nilai && mysqli_num_rows($query_baca_nilai) > 0) {
        $data_nilai = mysqli_fetch_assoc($query_baca_nilai);
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Penilaian Tahapan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8fafc; padding: 40px; color: #334155; }
        
        .btn-back { display: inline-block; text-decoration: none; color: #64748b; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .btn-back:hover { color: #4f46e5; }

        /* CONTAINER UTAMA */
        .main-wrapper { 
            max-width: 1250px; 
            margin: 0 auto; 
            display: flex; 
            gap: 30px; 
            align-items: stretch; /* SEBELUMNYA flex-start, UBAH MENJADI stretch AGAR TINGGI KANAN KIRI SELALU SAMA */
        }

        /* PANEL KIRI: DETAIL PROFIL */
        .profile-panel { 
            flex: 0 0 400px; 
            background: white; 
            padding: 24px; 
            border-radius: 16px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            border: 1px solid #e2e8f0; 
            display: flex;         /* Ditambahkan agar layout di dalam panel kiri mengikuti flex */
            flex-direction: column;
            height: 100%;          /* Memaksa mengambil tinggi maksimal wrapper */
        }

        .profile-header-box { text-align: center; margin-bottom: 25px; }
        .profile-avatar-box { width: 100px; height: 100px; background: #f1f5f9; border-radius: 50%; margin: 0 auto 14px auto; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #94a3b8; font-weight: bold; border: 3px solid #e2e8f0; overflow: hidden; }
        .profile-avatar-box img { width: 100%; height: 100%; object-fit: cover; }
        .profile-main-name { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .profile-main-sub { font-size: 13px; color: #64748b; }

        .info-section { margin-bottom: 24px; }
        .section-divider { font-size: 11px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px; margin-bottom: 12px; text-align: left; }
        .data-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; font-size: 13px; border-bottom: 1px solid #f8fafc; }
        .data-label { color: #94a3b8; font-weight: 500; text-align: left; flex: 0 0 130px; }
        .data-value { color: #334155; font-weight: 600; text-align: right; flex: 1; }
        .no-data-text { font-size: 13px; color: #94a3b8; font-style: italic; text-align: left; padding: 4px 0; }

        /* BUTTONS DOKUMEN */
        .btn-view-doc { display: block; width: 100%; text-align: center; padding: 10px; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; transition: background 0.2s; }
        .blue-btn { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .blue-btn:hover { background: #bae6fd; }
        .berkas-item-row { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 8px; }
        .berkas-name { font-size: 13px; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 230px; }
        .btn-view-doc-small { background: #4f46e5; color: white; text-decoration: none; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 6px; transition: background 0.2s; }
        .btn-view-doc-small:hover { background: #4338ca; }

/* CONTAINER UTAMA */
.main-wrapper { 
    max-width: 1250px; 
    margin: 0 auto; 
    display: flex; 
    gap: 30px; 
    align-items: flex-start; /* Dikembalikan ke flex-start agar stabil */
}

/* PANEL KIRI: DETAIL PROFIL (DIKUNCI TINGGINYA) */
.profile-panel { 
    flex: 0 0 400px; 
    background: white; 
    padding: 24px; 
    border-radius: 16px; 
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
    border: 1px solid #e2e8f0; 
    
    /* KUNCI TINGGI DI SINI AGAR SAMA RATA DENGAN PANEL KANAN */
    height: 620px;            
    overflow-y: auto;          /* Menampilkan scrollbar vertikal internal yang rapi */
    box-sizing: border-box;
}

/* PANEL KANAN: FORM PENILAIAN (DIKUNCI TINGGINYA) */
.form-panel { 
    flex: 1; 
    background: white; 
    padding: 30px; 
    border-radius: 16px; 
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
    border: 1px solid #e2e8f0; 
    
    /* KUNCI TINGGI YANG SAMA DENGAN PANEL KIRI */
    height: 620px;            
    display: flex;
    flex-direction: column;
    justify-content: space-between; /* Memaksa tombol simpan selalu presisi di batas bawah */
    box-sizing: border-box;
}


        h1 { font-size: 22px; margin-bottom: 20px; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #334155; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; background-color: #f8fafc; transition: all 0.2s; }
        .form-control:focus { background-color: #fff; border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        /* CHROME TAB SYSTEM */
        .chrome-tabs-container { display: flex; background-color: #e2e8f0; padding: 6px 6px 0 6px; border-radius: 12px 12px 0 0; gap: 4px; overflow-x: auto; margin-bottom: 20px; border-bottom: 1px solid #cbd5e1; scrollbar-width: none; -ms-overflow-style: none; }
        .chrome-tabs-container::-webkit-scrollbar { display: none; width: 0; height: 0; }

        .chrome-tab { padding: 10px 16px; background-color: #f1f5f9; color: #475569; border-radius: 10px 10px 0 0; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.chrome-tab.active { background-color: #ffffff; color: #4f46e5; border: 1px solid #cbd5e1; border-bottom: none; position: relative; margin-bottom: -1px; padding-bottom: 11px; }

/* BUTTONS & ALERTS */
.btn-submit { background-color: #4f46e5; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: background 0.2s; width: 100%; }
.btn-submit:hover { background-color: #4338ca; }
.btn-skip { background-color: #f59e0b; color: white; border: none; padding: 12px 24px; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
.btn-skip:hover { background-color: #d97706; }

.alert { padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.alert-error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }
</style>
</head>
<body>

<div style="max-width: 1250px; margin: 0 auto;">
    <a href="lamaran_tahapan.php" class="btn-back">&larr; Kembali ke Daftar Progress</a>
</div>

<div class="main-wrapper">

<!-- ================= SISI KIRI: DATA UTUH DOKUMEN LAMARAN KERJA PELAMAR (READ ONLY) ================= -->
<div class="profile-panel">
    
    <!-- Bagian Foto & Nama Utama Pelamar -->
    <div class="profile-header-box">
        <div class="profile-avatar-box">
            <!-- PERBAIKAN: Menyertakan folder uploads/ agar gambar dari halaman profil pelamar terbaca murni -->
            <?php if(!empty($data_pelamar['foto'])): ?>
                <img src="uploads/<?= $data_pelamar['foto']; ?>" alt="Foto Profil Pelamar">
            <?php elseif(!empty($data_pelamar['foto_pelamar'])): ?>
                <img src="uploads/<?= $data_pelamar['foto_pelamar']; ?>" alt="Foto Profil Pelamar">
            <?php else: ?>
                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#0d6efd; color:white; font-size:32px; font-weight:bold;">
                    <?= strtoupper(substr($data_pelamar['nama_lengkap'] ?? 'K', 0, 1)); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-main-name"><?= htmlspecialchars($data_pelamar['nama_lengkap'] ?? '-'); ?></div>
        <div class="profile-main-sub">Melamar Posisi: <strong><?= htmlspecialchars($data_pelamar['nama_lowongan'] ?? '-'); ?></strong></div>
    </div>

    <!-- SEKTOR 1: DATA IDENTITAS PRIBADI -->
    <div class="info-section">
        <div class="section-divider">I. Biodata Pribadi Pelamar</div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Nama Lengkap</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['nama_lengkap'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">NIK (No. KTP)</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['nik'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Tempat, Tgl Lahir</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;">
                <?= htmlspecialchars($data_pelamar['tempat_lahir'] ?? '-'); ?>, 
                <?= !empty($data_pelamar['tanggal_lahir']) ? date('d M Y', strtotime($data_pelamar['tanggal_lahir'])) : '-'; ?>
            </span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Jenis Kelamin</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b"><?= htmlspecialchars($data_pelamar['jenis_kelamin'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Agama</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['agama'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Status Perkawinan</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['status_sosial'] ?? $data_pelamar['status_hubungan'] ?? '-'); ?></span>
        </div>
    </div>

    <!-- SEKTOR 2: DATA KONTAK & ALAMAT TINGGAL -->
    <div class="info-section">
        <div class="section-divider">II. Kontak & Alamat Domisili</div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Nomor Telepon/WA</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['no_telepon'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Alamat Email</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b; word-break: break-all;"><?= htmlspecialchars($data_pelamar['email'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Kota / Kabupaten</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['kota'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Provinsi</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['provinsi'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Alamat Lengkap Rumah</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; line-height: 1.5; color: #475569; font-weight: 500; word-break: break-word;"><?= htmlspecialchars($data_pelamar['alamat'] ?? '-'); ?></span>
        </div>
    </div>

    <!-- SEKTOR 3: RIWAYAT PENDIDIKAN PELAMAR -->
    <div class="info-section" style="margin-bottom: 0;">
        <div class="section-divider">III. Kualifikasi Pendidikan</div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0;">Pendidikan Terakhir</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; color: #1e293b; font-weight: 700;"><?= htmlspecialchars($data_pelamar['pendidikan'] ?? '-'); ?></span>
        </div>
        
        <?php if(!empty($data_pelamar['institusi']) || !empty($data_pelamar['jurusan'])): ?>
            <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
                <span class="data-label" style="width: 200px; flex-shrink: 0;">Nama Institusi</span>
                <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
                <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['institusi'] ?? '-'); ?></span>
            </div>
            <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 8px;">
                <span class="data-label" style="width: 200px; flex-shrink: 0;">Jurusan / IPK</span>
                <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
                <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['jurusan'] ?? '-'); ?> (IPK: <?= htmlspecialchars($data_pelamar['ipk'] ?? '-'); ?>)</span>
            </div>
        <?php endif; ?>
    </div>
    <!-- =========================================================================
         SEKTOR 4: DOKUMEN PENDUKUNG (IJAZAH & STR)
         ========================================================================= -->
    <div class="info-section" style="margin-top: 15px;">
        <div class="section-divider" style="margin-bottom: 10px; font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase;">IV. Dokumen Pendukung</div>
        
        <!-- Baris Dokumen Ijazah -->
        <div class="data-row" style="display: flex; align-items: center; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0; font-size: 13px;">Dokumen Ijazah</span>
            <span style="width: 15px; color: #94a3b8; text-align: center; font-size: 13px;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-size: 13px;">
                <?php if (!empty($list_berkas['ijazah'])): ?>
                    <a href="uploads/<?= $list_berkas['ijazah']; ?>" target="_blank" style="color: #0d6efd; font-weight: 600; text-decoration: none;">
                        👁️ Buka Ijazah (Dapat Dilihat)
                    </a>
                <?php else: ?>
                    <span style="color: #dc3545; font-weight: 500;">Belum Diunggah</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Baris Dokumen STR (Surat Tanda Registrasi) -->
        <div class="data-row" style="display: flex; align-items: center; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0; font-size: 13px;">Dokumen STR</span>
            <span style="width: 15px; color: #94a3b8; text-align: center; font-size: 13px;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-size: 13px;">
                <?php if (!empty($list_berkas['str'])): ?>
                    <a href="uploads/<?= $list_berkas['str']; ?>" target="_blank" style="color: #0d6efd; font-weight: 600; text-decoration: none;">
                        👁️ Buka STR (Dapat Dilihat)
                    </a>
                <?php elseif (!empty($list_str[0]['file_str'])): ?>
                    <a href="uploads/<?= $list_str[0]['file_str']; ?>" target="_blank" style="color: #0d6efd; font-weight: 600; text-decoration: none;">
                        👁️ Buka STR (Dapat Dilihat)
                    </a>
                <?php else: ?>
                    <span style="color: #64748b; font-weight: 500;">Tidak Ada / Tidak Wajib</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- =========================================================================
         SEKTOR 5: RIWAYAT PENGALAMAN KERJA (LOOP DYNAMIC LIST)
         ========================================================================= -->
    <div class="info-section" style="margin-top: 15px; margin-bottom: 0;">
        <div class="section-divider" style="margin-bottom: 10px; font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase;">V. Pengalaman Kerja</div>
        
        <?php if (!empty($list_pengalaman)): ?>
            <?php foreach ($list_pengalaman as $index => $exp): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 8px; font-size: 13px;">
                    <div style="font-weight: 700; color: #1e293b; margin-bottom: 2px;">
                        <?= htmlspecialchars($exp['jabatan'] ?? $exp['posisi'] ?? '-'); ?>
                    </div>
                    <div style="color: #475569; font-weight: 500; margin-bottom: 4px;">
                        🏢 <?= htmlspecialchars($exp['perusahaan'] ?? $exp['nama_perusahaan'] ?? '-'); ?>
                    </div>
                    <div style="font-size: 11px; color: #64748b;">
                        📅 Periode: 
                        <?= !empty($exp['mulai_kerja']) ? date('d M Y', strtotime($exp['mulai_kerja'])) : '-'; ?> 
                        s/d 
                        <?= (!empty($exp['selesai_kerja']) && $exp['selesai_kerja'] != '0000-00-00') ? date('d M Y', strtotime($exp['selesai_kerja'])) : 'Sekarang'; ?>
                    </div>
                    <?php if (!empty($exp['alasan_keluar'])): ?>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 4px; font-style: italic;">
                            Alasan Keluar: <?= htmlspecialchars($exp['alasan_keluar']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="font-size: 13px; color: #64748b; font-style: italic; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px dashed #cbd5e1; text-align: center;">
                Pelamar tidak memiliki/mencantumkan riwayat pengalaman kerja (Fresh Graduate).
            </div>
        <?php endif; ?>
    </div>

</div>
    
<!-- ================= SISI KANAN: FORM PENILAIAN (CLEAN & PROPORTIONAL) ================= -->
<!-- PERBAIKAN UTAMA: Memotong padding-top kontainer kanan seminimal mungkin -->
<div class="form-panel" style="padding: 5px 15px 15px 15px !important; box-sizing: border-box; height: auto !important; min-height: unset !important;">
    
    <!-- Judul Panel Kanan (Diberi padding & margin nol agar mepet ke atas) -->
    <h1 style="font-size: 20px; font-weight: 700; margin-top: 0px !important; margin-bottom: 0px !important; padding-top: 0px !important; padding-bottom: 5px !important; color: #1e293b; border-bottom: 1px solid #f1f5f9; line-height: 1.2;">
        Input Penilaian Tahapan
    </h1>

    <?php if (!empty($success_msg)): ?><div class="alert alert-success" style="margin-top: 5px; margin-bottom: 5px; padding: 6px; font-size: 13px;"><?= $success_msg; ?></div><?php endif; ?>
    <?php if (!empty($error_msg)): ?><div class="alert alert-error" style="margin-top: 5px; margin-bottom: 5px; padding: 6px; font-size: 13px;"><?= $error_msg; ?></div><?php endif; ?>

    <!-- PERBAIKAN: Mengembalikan margin menjadi NORMAL (0px) agar tidak bertabrakan, tapi pangkas paddingnya -->
    <form action="" method="POST" id="formPenilaian" style="margin-top: 0px !important; padding-top: 5px !important;">
        
        <!-- Baris Pilihan Tahapan Seleksi (Diberi margin-top minimal agar pas di bawah garis) -->
        <div class="form-group" style="margin-top: 5px !important; margin-bottom: 12px !important; padding-top: 0px !important;">
            <label style="font-size: 13px; font-weight: 600; color: #475569; margin-top: 0px !important; margin-bottom: 4px !important; display: block;">Pilih Tahapan Seleksi</label>
            <div class="chrome-tabs-container" style="margin-top: 0px !important; padding-top: 0px !important;">


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

<!-- BARIS INPUT NILAI KOMPETENSI (INDIVIDU PER TAB) -->
<div class="form-group" style="margin-top: 20px; margin-bottom: 15px;">
    <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; display: block;">Nilai Kompetensi (0.00 - 100.00)</label>
    <!-- PERBAIKAN: value dipaksa HANYA membaca variabel kueri database $data_nilai -->
    <input type="number" 
           name="nilai" 
           id="input_nilai_kompetensi"
           class="form-control" 
           step="0.01" 
           min="0" 
           max="100" 
           placeholder="Contoh: 85.50" 
           value="<?= isset($data_nilai['nilai']) && $data_nilai['nilai'] !== null ? htmlspecialchars($data_nilai['nilai']) : ''; ?>" 
           oninput="hitungStatusOtomatis(this.value)"
           style="padding: 10px; font-size: 14px; width: 100%; box-sizing: border-box;"
           required>
</div>

<!-- BARIS STATUS OTOMATIS -->
<div class="form-group" style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
    <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 0;">Status Otomatis:</label>
    <span id="badge_status_otomatis" style="padding: 4px 12px; border-radius: 4px; font-size: 13px; font-weight: 700; background: #e2e8f0; color: #64748b;">
        <?= !empty($data_nilai['status_tahap']) ? strtoupper($data_nilai['status_tahap']) : '-'; ?>
    </span>
</div>

<!-- BARIS CATATAN REKOMENDASI PENILAI (INDIVIDU PER TAB) -->
<div class="form-group" style="margin-bottom: 20px;">
    <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; display: block;">Catatan / Rekomendasi Penilai</label>
    <!-- PERBAIKAN: Isi teks dipaksa HANYA membaca variabel kueri database $data_nilai -->
    <textarea name="catatan" 
              class="form-control" 
              rows="4" 
              placeholder="Tuliskan feedback hasil evaluasi teknis kandidat..." 
              style="padding: 10px; font-size: 13px; width: 100%; box-sizing: border-box;" 
              required><?= isset($data_nilai['catatan']) ? htmlspecialchars($data_nilai['catatan']) : ''; ?></textarea>
</div>

        <!-- ACTION BUTTONS: SIMPAN & LEWATI (FLEX LAYOUT) -->
        <div style="display: flex; gap: 12px; margin-top: 10px;">
            <button type="submit" 
                    name="simpan_penilaian" 
                    style="flex: 3; background-color: #4f46e5; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer;">
                Simpan Hasil Penilaian
            </button>
            <button type="submit" 
                    name="aksi_lewati" 
                    style="flex: 1; background-color: #f59e0b; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer;"
                    formnovalidate>
                Lewati Tahap
            </button>
        </div>

    </form>
</div> <!-- Tag penutup form-panel induk -->


<!-- ================= CONTROLLER JAVASCRIPT ================= -->
<script>
// Fungsi Klik Tab Seleksi: Auto-Save Data via AJAX lalu Berpindah Tab dengan Bersih
function pilihTabChrome(elemen, tahapanId) {
    const inputNilai = document.getElementById('input_nilai_kompetensi');
    const inputCatatan = document.querySelector('textarea[name="catatan"]');
    
    // Ambil parameter ID Lamaran asli dari URL browser
    const urlParams = new URLSearchParams(window.location.search);
    const currentId = urlParams.get('id');

    // Jika kolom nilai kompetensi telah diisi angka, lakukan simpan otomatis di latar belakang (AJAX)
    if (inputNilai && inputNilai.value.trim() !== '') {
        // Persiapkan data form untuk dikirim secara asinkron
        const formData = new FormData();
        formData.append('mst_tahapan_id', document.getElementById('input_mst_tahapan_id').value); // ID tahapan LAMA yang mau disimpan
        formData.append('nilai', inputNilai.value);
        formData.append('catatan', inputCatatan ? inputCatatan.value : '');

        // Kirim data nilai ke server menggunakan Fetch API (AJAX)
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(() => {
            // Setelah database sukses mengunci nilai di latar belakang, langsung alihkan halaman ke opsi baru
            window.location.href = `penilaian_tahapan.php?id=${currentId}&tahapan_id=${tahapanId}`;
        })
        .catch(error => {
            console.error('Gagal menyimpan otomatis:', error);
            // Tetap pindahkan halaman meskipun jaringan bermasalah
            window.location.href = `penilaian_tahapan.php?id=${currentId}&tahapan_id=${tahapanId}`;
        });
    } else {
        // Jika kolom nilai masih kosong, langsung pindah tab biasa tanpa memicu simpan data
        window.location.href = `penilaian_tahapan.php?id=${currentId}&tahapan_id=${tahapanId}`;
    }
}

// Fungsi Hitung Status Otomatis secara Real-Time saat Penilai Mengetik Nilai
function hitungStatusOtomatis(nilai) {
    const badge = document.getElementById('badge_status_otomatis');
    if (!nilai || nilai.trim() === '') {
        badge.innerText = '-';
        badge.style.background = '#e2e8f0';
        badge.style.color = '#64748b';
        return;
    }
    
    const skor = parseFloat(nilai);
    if (skor >= 75.00) {
        badge.innerText = 'LULUS';
        badge.style.background = '#dcfce7'; 
        badge.style.color = '#15803d';      
    } else {
        badge.innerText = 'TIDAK LULUS';
        badge.style.background = '#fee2e2'; 
        badge.style.color = '#b91c1c';      
    }
}

// Jalankan auto warna badge saat pertama kali halaman dimuat
window.addEventListener('DOMContentLoaded', () => {
    const inputNilai = document.getElementById('input_nilai_kompetensi');
    if(inputNilai && inputNilai.value) {
        hitungStatusOtomatis(inputNilai.value);
    }
});
</script>

<script>
// DETEKSI OTOMATIS: Jika penilai menutup tab atau meninggalkan halaman tanpa klik simpan
window.addEventListener('beforeunload', function (e) {
    // Ambil parameter ID Lamaran dari URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentId = urlParams.get('id');
    
    // Siapkan data payload terkompresi
    const data = new FormData();
    data.append('action_meninggalkan_halaman', '1');
    data.append('lamaran_tahapan_id', currentId);

    // Kirim sinyal kilat ke server di latar belakang (tetap terkirim meskipun tab sudah hancur/ditutup)
    navigator.sendBeacon('penilaian_tahapan.php?id=' + currentId, data);
});
</script>

</body>
</html>
