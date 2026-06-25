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

// =========================================================================
// 3. AMBIL DATA SESSION USER LOGIN & FETCHING SYSTEM SEMUA TABEL
// =========================================================================
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

if (!$pelamar_id) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location.href='login_pelamar.php';</script>";
    exit;
}

// 🟢 A. FETCH DATA BIODATA UTAMA (Untuk Form Sebelah Kiri)
$query_profil = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = '$pelamar_id'");
$data = mysqli_fetch_assoc($query_profil);

// Sinkronisasi otomatis jika kolom di tabel MySQL Anda bernama 'nama', bukan 'nama_lengkap'
if ($data) {
    if (!isset($data['nama_lengkap']) && isset($data['nama'])) {
        $data['nama_lengkap'] = $data['nama'];
    }
}

// 🟢 B. FETCH DATA RIWAYAT PENDIDIKAN (Sistem Anda yang Sudah Ada)
$query_ambil = "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = '$pelamar_id'";
$result_ambil = mysqli_query($koneksi, $query_ambil);

$cek_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar_pendidikan WHERE Field IN ('tingkat_pendidikan', 'pendidikan', 'jenjang')");
$kolom_jenjang = (mysqli_num_rows($cek_kolom) > 0) ? mysqli_fetch_assoc($cek_kolom)['Field'] : 'pendidikan';

$cek_kolom_thn = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar_pendidikan WHERE Field IN ('tahun_lulus', 'tahun')");
$kolom_tahun_db = (mysqli_num_rows($cek_kolom_thn) > 0) ? mysqli_fetch_assoc($cek_kolom_thn)['Field'] : 'tahun_lulus';

$data_pendidikan = [];
if ($result_ambil && mysqli_num_rows($result_ambil) > 0) {
    while ($row = mysqli_fetch_assoc($result_ambil)) {
        $row['pendidikan']  = trim($row[$kolom_jenjang] ?? '');
        $row['tahun_lulus'] = trim($row[$kolom_tahun_db] ?? '');
        $data_pendidikan[]  = $row;
    }
}

// 🟢 C. FETCH DATA RIWAYAT PENGALAMAN KERJA (Untuk Form Sebelah Kanan)
$query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = '$pelamar_id'");
$list_pengalaman = [];
if ($query_exp && mysqli_num_rows($query_exp) > 0) {
    while ($row_exp = mysqli_fetch_assoc($query_exp)) {
        $list_pengalaman[] = $row_exp;
    }
}

// =========================================================================
// 4. LOGIC BACKEND: PROSES UPDATE BIODATA (FIXED AUTO-INSERT & KOLOM NAMA)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profil'])) {
    $nama_lengkap    = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $nik             = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $tempat_lahir    = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir   = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin   = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $agama           = mysqli_real_escape_string($koneksi, $_POST['agama']);
    
    // PERBAIKAN WARNING: Fallback fleksibel membaca atribut name dari HTML form
    $status_hubungan = mysqli_real_escape_string($koneksi, $_POST['status_sosial'] ?? $_POST['status_hubungan'] ?? '');
    
    $no_telepon      = mysqli_real_escape_string($koneksi, $_POST['no_telepon']);
    $kota            = mysqli_real_escape_string($koneksi, $_POST['kota']);
    $provinsi        = mysqli_real_escape_string($koneksi, $_POST['provinsi']);
    $alamat          = mysqli_real_escape_string($koneksi, $_POST['alamat']);

    // PENGECEKAN DUPLICATE NIK: Memastikan NIK belum digunakan oleh pelamar lain
    $cek_nik = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE nik = '$nik' AND id != '$pelamar_id'");
    if (mysqli_num_rows($cek_nik) > 0) {
        echo "<script>alert('❌ Gagal: Nomor NIK tersebut sudah terdaftar pada akun pelamar lain!'); window.history.back();</script>";
        exit;
    }

    // 🔥 1. DETEKSI NAMA KOLOM NAMA UTAMA (Dinamis: 'nama' atau 'nama_lengkap')
    $cek_kolom_nama = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar LIKE 'nama_lengkap'");
    $kolom_nama_db = (mysqli_num_rows($cek_kolom_nama) > 0) ? 'nama_lengkap' : 'nama';

    // 🔥 2. FIX AUTO-INSERT: Jika ID belum ada di tabel pelamar, buatkan barisnya dahulu
    $cek_data_induk = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE id = '$pelamar_id'");
    if (mysqli_num_rows($cek_data_induk) == 0) {
        mysqli_query($koneksi, "INSERT INTO pelamar (id, $kolom_nama_db) VALUES ('$pelamar_id', '$nama_lengkap')");
    }

    $query_foto_part = "";
    if (!empty($_FILES['foto']['name'])) {
        $nama_file_foto = time() . "_" . $_FILES['foto']['name'];
        if (move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $nama_file_foto)) {
            $query_foto_part = ", foto = '$nama_file_foto'";
        }
    }

    // 🔥 3. EKSEKUSI UPDATE DATA (Menggunakan variabel $kolom_nama_db hasil deteksi database)
    $query_update = "UPDATE pelamar SET 
                        $kolom_nama_db='$nama_lengkap', 
                        nik='$nik', 
                        tempat_lahir='$tempat_lahir', 
                        tanggal_lahir='$tanggal_lahir', 
                        jenis_kelamin='$jenis_kelamin', 
                        agama='$agama', 
                        status_sosial='$status_hubungan', 
                        no_telepon='$no_telepon', 
                        kota='$kota', 
                        provinsi='$provinsi', 
                        alamat='$alamat' 
                        $query_foto_part 
                     WHERE id = '$pelamar_id'";
                     
    if (mysqli_query($koneksi, $query_update)) {
        echo "<script>alert('✓ Biodata profil berhasil diperbarui!'); window.location.href='profil_pelamar.php';</script>";
        exit;
    } else {
        // Jika ada masalah struktur tabel lainnya, sistem akan berhenti di sini dan memunculkan error transparan
        echo "<h3>Gagal Menyimpan Data! Periksa kesalahan struktur tabel di bawah ini:</h3>";
        echo "Error MySQL: " . mysqli_error($koneksi);
        exit;
    }
}

// =========================================================================
// 4A. LOGIC BACKEND: PROSES SIMPAN DATA RIWAYAT PENDIDIKAN + TAHUN LULUS (SUPER FAST)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_pendidikan'])) {
    $tingkat_arr   = $_POST['pendidikan'] ?? []; 
    $institusi_arr = $_POST['institusi'] ?? [];
    $jurusan_arr   = $_POST['jurusan'] ?? [];
    $ipk_arr       = $_POST['ipk'] ?? [];
    $tahun_arr     = $_POST['tahun_lulus'] ?? []; 

    // 1. Deteksi dinamis nama kolom jenjang di tabel 'pelamar_pendidikan'
    $cek_kolom_jenjang = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar_pendidikan WHERE Field IN ('tingkat_pendidikan', 'pendidikan', 'jenjang')");
    $nama_kolom_jenjang = (mysqli_num_rows($cek_kolom_jenjang) > 0) ? mysqli_fetch_assoc($cek_kolom_jenjang)['Field'] : 'pendidikan';

    // 2. Deteksi dinamis nama kolom nama sekolah/kampus di tabel 'pelamar_pendidikan'
    $cek_kolom_kampus = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar_pendidikan WHERE Field IN ('nama_institusi', 'institusi', 'sekolah', 'kampus')");
    $nama_kolom_kampus = (mysqli_num_rows($cek_kolom_kampus) > 0) ? mysqli_fetch_assoc($cek_kolom_kampus)['Field'] : 'institusi';

    // 3. Deteksi dinamis nama kolom tahun lulus di tabel 'pelamar_pendidikan'
    $cek_kolom_tahun = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar_pendidikan WHERE Field IN ('tahun_lulus', 'tahun')");
    if (mysqli_num_rows($cek_kolom_tahun) > 0) {
        $nama_kolom_tahun = mysqli_fetch_assoc($cek_kolom_tahun)['Field'];
    } else {
        // Otomatis buat kolom baru jika belum ada di database Anda
        mysqli_query($koneksi, "ALTER TABLE pelamar_pendidikan ADD COLUMN tahun_lulus VARCHAR(10) NULL");
        $nama_kolom_tahun = 'tahun_lulus';
    }

    // Bersihkan riwayat pendidikan lama pelamar untuk mencegah data ganda duplikat
    mysqli_query($koneksi, "DELETE FROM pelamar_pendidikan WHERE pelamar_id = '$pelamar_id'");

    $values_insert = [];
    $pendidikan_terakhir_singkat = '';

    // Susun data ke dalam array memori server
    foreach ($tingkat_arr as $index => $tingkat) {
        if (empty(trim($tingkat))) continue;

        $tingkat_clean   = mysqli_real_escape_string($koneksi, $tingkat);
        $institusi_clean = mysqli_real_escape_string($koneksi, $institusi_arr[$index] ?? '');
        $jurusan_clean   = mysqli_real_escape_string($koneksi, $jurusan_arr[$index] ?? '');
        $ipk_clean       = mysqli_real_escape_string($koneksi, $ipk_arr[$index] ?? '');
        $tahun_clean     = mysqli_real_escape_string($koneksi, $tahun_arr[$index] ?? '');

        $values_insert[] = "('$pelamar_id', '$tingkat_clean', '$institusi_clean', '$jurusan_clean', '$ipk_clean', '$tahun_clean')";
        
        if (empty($pendidikan_terakhir_singkat)) {
            $pendidikan_terakhir_singkat = substr($tingkat_clean, 0, 10);
        }
    }

    $sukses_pend = true;

    // Terapkan teknik Bulk Insert: Mengirim seluruh data dalam 1 baris perintah SQL tunggal
    if (!empty($values_insert)) {
        $query_bulk = "INSERT INTO pelamar_pendidikan (pelamar_id, $nama_kolom_jenjang, $nama_kolom_kampus, jurusan, ipk, $nama_kolom_tahun) VALUES " . implode(', ', $values_insert);
        
        if (!mysqli_query($koneksi, $query_bulk)) {
            $sukses_pend = false;
        }

        // Jalankan update ringkasan tabel pelamar secara aman tanpa memicu Fatal Error
        if ($sukses_pend && !empty($pendidikan_terakhir_singkat)) {
            $cek_kolom_induk = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar LIKE 'pendidikan'");
            if (mysqli_num_rows($cek_kolom_induk) > 0) {
                mysqli_query($koneksi, "UPDATE pelamar SET pendidikan = '$pendidikan_terakhir_singkat' WHERE id = '$pelamar_id'");
            }
        }
    }
    
    if ($sukses_pend) { 
        echo "<script>alert('✓ Riwayat pendidikan berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>"; 
        exit; 
    } else {
        echo "Gagal menyimpan data pendidikan: " . mysqli_error($koneksi);
        exit;
    }
}

// =========================================================================
// 4B. LOGIC BACKEND: PROSES SIMPAN DATA PENGALAMAN KERJA (FIXED KOLOM SQL)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_pengalaman'])) {
    
    // 1. Validasi awal bentuk data session
    if (empty($pelamar_id) || !is_numeric($pelamar_id)) {
        echo "<script>alert('Error: Sesi pelamar tidak valid atau tidak ditemukan!'); window.location.href='profil_pelamar.php';</script>";
        exit;
    }

    $pelamar_id = (int)$pelamar_id;

    // 🔥 FIX UTAMA: CEK DAN AUTO-CREATE JIKA ID PELAMAR BELUM ADA DI TABEL INDUK
    $cek_pelamar = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE id = $pelamar_id");
    if (mysqli_num_rows($cek_pelamar) == 0) {
        // Ambil nama dari session untuk nama default database
        $nama_default = isset($_SESSION['pelamar_nama']) ? mysqli_real_escape_string($koneksi, $_SESSION['pelamar_nama']) : 'Pelamar Baru';
        
        // Deteksi nama kolom (nama atau nama_lengkap) agar tidak error syntax
        $cek_kolom_nama = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar LIKE 'nama_lengkap'");
        $kolom_nama_db = (mysqli_num_rows($cek_kolom_nama) > 0) ? 'nama_lengkap' : 'nama';
        
        // Buatkan baris data baru secara paksa di tabel induk pelamar
        mysqli_query($koneksi, "INSERT INTO pelamar (id, $kolom_nama_db) VALUES ($pelamar_id, '$nama_default')");
    }

    $perusahaan_arr  = $_POST['nama_perusahaan'] ?? [];
    $jabatan_arr     = $_POST['jabatan'] ?? [];
    $mulai_kerja_arr = $_POST['mulai_kerja'] ?? [];
    $selesai_arr     = $_POST['selesai_kerja'] ?? [];
    $alasan_arr      = $_POST['alasan_keluar'] ?? [];
    $sukses_kerja    = true;

    // Hapus data lama pelamar tersebut terlebih dahulu (sekarang aman karena ID pasti ada)
    mysqli_query($koneksi, "DELETE FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id");

    foreach ($perusahaan_arr as $index => $perusahaan) {
        if (empty(trim($perusahaan))) continue;

        $perusahaan_clean = mysqli_real_escape_string($koneksi, $perusahaan);
        $jabatan_clean    = !empty($jabatan_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $jabatan_arr[$index]) . "'" : "NULL";
        $mulai_clean      = !empty($mulai_kerja_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $mulai_kerja_arr[$index]) . "'" : "NULL";
        $selesai_clean    = !empty($selesai_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $selesai_arr[$index]) . "'" : "NULL";
        $alasan_clean     = !empty($alasan_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $alasan_arr[$index]) . "'" : "NULL";

        $query_ins_exp = "INSERT INTO pelamar_pengalaman (pelamar_id, perusahaan, jabatan, mulai_kerja, selesai_kerja, alasan_keluar) 
                          VALUES ($pelamar_id, '$perusahaan_clean', $jabatan_clean, $mulai_clean, $selesai_clean, $alasan_clean)";
                          
        if (!mysqli_query($koneksi, $query_ins_exp)) { 
            $sukses_kerja = false; 
        }
    }
    if ($sukses_kerja) { 
        echo "<script>alert('✓ Riwayat pengalaman kerja berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>"; 
        exit; 
    } else {
        echo "Gagal menyimpan data ke database: " . mysqli_error($koneksi);
        exit;
    }
}


// 5. LOGIC BACKEND: PROSES SIMPAN DATA STR (SINKRON HEIDISQL)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_str'])) {
    $nomor_str_arr      = $_POST['nomor_str'] ?? [];
    $tgl_terbit_arr     = $_POST['tanggal_terbit'] ?? [];
    $tgl_expired_arr    = $_POST['tanggal_expired'] ?? [];
    $file_str_lama_arr  = $_POST['file_str_lama'] ?? [];
    $sukses_insert      = true;

    mysqli_query($koneksi, "DELETE FROM pelamar_str WHERE pelamar_id = $pelamar_id");

    foreach ($nomor_str_arr as $index => $nomor_str) {
        if (empty(trim($nomor_str))) continue;

        $nomor_clean   = mysqli_real_escape_string($koneksi, $nomor_str);
        $tgl_terbit    = !empty($tgl_terbit_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $tgl_terbit_arr[$index]) . "'" : "NULL";
        $tgl_expired   = !empty($tgl_expired_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $tgl_expired_arr[$index]) . "'" : "NULL";
        $nama_file_str = $file_str_lama_arr[$index] ?? '';

        if (isset($_FILES['file_str']['name'][$index]) && !empty($_FILES['file_str']['name'][$index])) {
            $f_ext  = strtolower(pathinfo($_FILES['file_str']['name'][$index], PATHINFO_EXTENSION));
            if (in_array($f_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $nama_file_str = "str_" . $pelamar_id . "_" . time() . "_" . $index . "." . $f_ext;
                move_uploaded_file($_FILES['file_str']['tmp_name'][$index], "uploads/" . $nama_file_str);
            }
        }

        $query_ins_str = "INSERT INTO pelamar_str (pelamar_id, nomor_str, tanggal_terbit, tanggal_expired, file_str, created_at, updated_at) VALUES ($pelamar_id, '$nomor_clean', $tgl_terbit, $tgl_expired, '$nama_file_str', NOW(), NOW())";
        if (!mysqli_query($koneksi, $query_ins_str)) { $sukses_insert = false; }
    }
    if ($sukses_insert) { echo "<script>alert('✓ Data STR berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>"; exit; }
}

// 6. LOGIC BACKEND: PROSES SIMPAN BERKAS DOKUMEN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_berkas'])) {
    $jenis_berkas_arr     = $_POST['jenis_berkas'] ?? [];
    $file_berkas_lama_arr = $_POST['file_berkas_lama'] ?? [];
    $sukses_berkas        = true;

    mysqli_query($koneksi, "DELETE FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");

    foreach ($jenis_berkas_arr as $index => $jenis_berkas) {
        if (empty(trim($jenis_berkas))) continue;

        $jenis_clean       = mysqli_real_escape_string($koneksi, $jenis_berkas);
        $nama_file_berkas  = $file_berkas_lama_arr[$index] ?? '';

        if (isset($_FILES['file_berkas']['name'][$index]) && !empty($_FILES['file_berkas']['name'][$index])) {
            $b_ext  = strtolower(pathinfo($_FILES['file_berkas']['name'][$index], PATHINFO_EXTENSION));
            if (in_array($b_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $nama_file_berkas = "berkas_" . $pelamar_id . "_" . time() . "_" . $index . "." . $b_ext;
                move_uploaded_file($_FILES['file_berkas']['tmp_name'][$index], "uploads/" . $nama_file_berkas);
            }
        }

        $query_ins_bk = "INSERT INTO pelamar_berkas (pelamar_id, jenis_berkas, nama_file) VALUES ($pelamar_id, '$jenis_clean', '$nama_file_berkas')";
        if (!mysqli_query($koneksi, $query_ins_bk)) { $sukses_berkas = false; }
    }
    if ($sukses_berkas) { echo "<script>alert('✓ Berkas berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>"; exit; }
}

// 7. LOADER DATA SINKRONISASI KE FORM VIEW
$data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id"));

$list_pengalaman = [];
$q_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
while ($r = mysqli_fetch_assoc($q_exp)) { $list_pengalaman[] = $r; }

$list_pendidikan = [];
$q_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
while ($r = mysqli_fetch_assoc($q_pend)) { $list_pendidikan[] = $r; }

$list_berkas = [];
$q_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
while ($r = mysqli_fetch_assoc($q_bk)) { $list_berkas[] = $r; }

$list_str = [];
$q_str = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
while ($r = mysqli_fetch_assoc($q_str)) { $list_str[] = $r; }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Pelamar Magang</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .brand { color: #0d6efd; text-decoration: none; font-weight: bold; }
        .main-container { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; max-width: 1300px; margin: 0 auto; align-items: start; }
        .card-profil { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .card-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .form-control:focus { border-color: #0d6efd; outline: none; }
        .btn-simpan-full { width: 100%; background-color: #0d6efd; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-simpan-full:hover { background-color: #0b5ed7; }
    </style>
</head>
<body>

     <div class="navbar">
        <a href="lowongan_pelamar.php" class="brand">&larr; KEMBALI KE PORTAL LOWONGAN</a>
        <span style="font-size: 14px; font-weight: 600; color: #64748b;">Pengaturan Profil Akun</span>
    </div>

    <!-- KONTEN UTAMA (SISTEM GRID 2 KOLOM) -->
    <div class="main-container">
        
        <!-- ==================== KOLOM SEBELAH KIRI ==================== -->
        <div>
            <!-- KARTU 1: BIODATA -->
            <div class="card-profil">
                <div class="card-title" style="color: #0d6efd; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Biodata Profil Pelamar</div>
                <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    
                    <!-- FOTO PROFIL -->
                    <div style="text-align: center; margin-bottom: 25px; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                        <label style="display: block; font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 10px;">Foto Profil</label>
                        <div class="avatar-wrapper" style="position: relative; width: 110px; height: 110px; margin: 0 auto; cursor: pointer;" onclick="document.getElementById('inputFoto').click();">
                            <?php if (!empty($data['foto'])) : ?>
                                <img id="previewFoto" src="uploads/<?= htmlspecialchars($data['foto']); ?>" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #cbd5e1;">
                            <?php else : ?>
                                <div id="placeholderFoto" style="width: 110px; height: 110px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px; border: 3px solid #cbd5e1;">Belum Ada Foto</div>
                                <img id="previewFoto" src="" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #cbd5e1; display: none;">
                            <?php endif; ?>
                        </div>
                        <small style="display: block; margin-top: 8px; color: #64748b; font-size: 11px;">Klik gambar di atas untuk mengganti foto berkas</small>
                        <input type="file" id="inputFoto" name="foto" accept="image/*" style="display: none;" onchange="bacaGambar(this)">
                    </div>

                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data['nama_lengkap'] ?? ''); ?>" required></div>
                    <div class="form-group"><label>NIK (Nomor Induk Kependudukan)</label><input type="text" name="nik" class="form-control" value="<?= htmlspecialchars($data['nik'] ?? ''); ?>" required></div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" class="form-control" value="<?= htmlspecialchars($data['tempat_lahir'] ?? ''); ?>"></div>
                        <div class="form-group" style="flex: 1;"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control" value="<?= $data['tanggal_lahir'] ?? ''; ?>"></div>
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-control">
                                <option value="Laki-laki" <?= ($data['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?= ($data['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;"><label>Agama</label><input type="text" name="agama" class="form-control" value="<?= htmlspecialchars($data['agama'] ?? ''); ?>"></div>
                    </div>

                    <div style="display: flex; gap: 15px;">
<div class="form-group" style="flex: 1;">
    <label>Status Hubungan / Sosial</label>
    <!-- PERBAIKAN: Ganti name dari status_hubungan menjadi status_sosial -->
    <select name="status_sosial" class="form-control">
        <option value="">-- Pilih Status --</option>
        <option value="Belum Kawin" <?= ($data['status_sosial'] ?? '') == 'Belum Kawin' ? 'selected' : ''; ?>>Belum Kawin</option>
        <option value="Kawin" <?= ($data['status_sosial'] ?? '') == 'Kawin' ? 'selected' : ''; ?>>Kawin</option>
    </select>
</div>

                        <div class="form-group" style="flex: 1;">
                            <label>Nomor Telepon / WhatsApp</label>
                            <input type="tel" name="no_telepon" class="form-control" placeholder="Contoh: 08123456789" value="<?= htmlspecialchars($data['no_telepon'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;"><label>Kota</label><input type="text" name="kota" class="form-control" value="<?= htmlspecialchars($data['kota'] ?? ''); ?>"></div>
                        <div class="form-group" style="flex: 1;"><label>Provinsi</label><input type="text" name="provinsi" class="form-control" value="<?= htmlspecialchars($data['provinsi'] ?? ''); ?>"></div>
                    </div>
                    
                    <div class="form-group"><label>Alamat Rumah Lengkap</label><textarea name="alamat" class="form-control" rows="3" style="resize: none;"><?= htmlspecialchars($data['alamat'] ?? ''); ?></textarea></div>
                    <button type="submit" name="update_profil" class="btn-simpan-full">Perbarui Biodata Profil</button>
                </form>
            </div>

            <!-- KARTU 5: DATA STR (PINDAH KE SINI AGAR PAS DAN SEIMBANG) -->
            <div class="card-profil">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                    <div class="card-title" style="color: #0d6efd; margin-bottom: 0;">Data Surat Tanda Registrasi (STR)</div>
                    <button type="button" onclick="tambahBarisSTR()" style="background-color: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah STR</button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <div id="container-str">
                        <?php 
                        if (empty($list_str)) { $list_str[] = ['nomor_str' => '', 'tanggal_terbit' => '', 'tanggal_expired' => '', 'file_str' => '']; }
                        foreach ($list_str as $str) : 
                        ?>
                            <div class="item-str-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                                <div style="text-align: right; margin-bottom: 10px;">
                                    <button type="button" onclick="hapusBarisDinamis(this, 'container-str')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Nomor STR</label>
                                    <input type="text" name="nomor_str[]" class="form-control" value="<?= htmlspecialchars($str['nomor_str'] ?? ''); ?>" required>
                                </div>
                                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                                    <div class="form-group" style="flex: 1;"><label style="font-size: 12px; font-weight: bold; color: #475569;">Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" value="<?= $str['tanggal_terbit'] ?? ''; ?>"></div>
                                    <div class="form-group" style="flex: 1;"><label style="font-size: 12px; font-weight: bold; color: #475569;">Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" value="<?= $str['tanggal_expired'] ?? ''; ?>"></div>
                                </div>
                                <input type="hidden" name="file_str_lama[]" value="<?= htmlspecialchars($str['file_str'] ?? ''); ?>">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Upload Dokumen STR</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="flex: 1;">
                                                                                <?php if (!empty($str['file_str'])) : ?>
                                            <a href="uploads/<?= htmlspecialchars($str['file_str']); ?>" target="_blank" style="background-color: #0d6efd; color: white; text-decoration: none; padding: 10px 14px; border-radius: 6px; font-size: 13px; font-weight: bold; white-space: nowrap;">👁 Lihat STR</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="simpan_str" class="btn-simpan-full" style="background-color: #d97706; width: 100%; margin-top: 10px;">Simpan Data STR</button>
                </form>
            </div>

        </div> <!-- PENUTUP KOLOM SEBELAH KIRI -->
        
        <!-- ==================== KOLOM KANAN: PENGALAMAN, PENDIDIKAN, BERKAS ==================== -->
        <div>            
        <!-- KARTU 2: PENGALAMAN KERJA (FIXED SINKRONISASI TOMBOL SUBMIT) -->
        <div class="card-profil">
            <!-- Tag pembuka form diletakkan di paling atas kartu agar membungkus seluruh elemen di dalamnya -->
            <form action="" method="POST" enctype="multipart/form-data">        
                
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;">
                    <div class="card-title" style="color: #0d6efd; margin-bottom: 0; font-weight: 700; font-size: 16px;">Riwayat Pengalaman Kerja</div>
                    <button type="button" onclick="tambahBarisPengalaman()" style="background-color: #0d6efd; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah Pengalaman</button>
                </div>

 <!-- Wadah target id untuk JavaScript agar baris baru tidak merusak form layout -->
        <div id="wadah-pengalaman">
            <!-- Loop data lama yang sudah tersimpan agar langsung muncul di halaman -->
            <?php if (!empty($list_pengalaman)): ?>
                <?php foreach ($list_pengalaman as $index => $exp): ?>
                    <div class="pengalaman-item" style="margin-bottom: 20px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 15px; position: relative;">
                        <?php if ($index > 0): ?>
                            <span style="position: absolute; right: 0; top: 0; color: #dc3545; font-size: 12px; cursor: pointer; font-weight: 600;" onclick="this.parentElement.remove()">Hapus</span>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Nama Perusahaan / Instansi</label>
                            <input type="text" name="nama_perusahaan[]" class="form-control" value="<?= htmlspecialchars($exp['perusahaan'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Jabatan / Posisi</label>
                            <input type="text" name="jabatan[]" class="form-control" value="<?= htmlspecialchars($exp['jabatan'] ?? ''); ?>" required>
                        </div>
                        <div style="display: flex; gap: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label>Mulai Kerja</label>
                                <input type="date" name="mulai_kerja[]" class="form-control" value="<?= $exp['mulai_kerja'] ?? ''; ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Selesai Kerja</label>
                                <input type="date" name="selesai_kerja[]" class="form-control" value="<?= $exp['selesai_kerja'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Alasan Keluar</label>
                            <input type="text" name="alasan_keluar[]" class="form-control" value="<?= htmlspecialchars($exp['alasan_keluar'] ?? ''); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Form kosong default jika pelamar belum pernah mengisi data kerja -->
                <div class="pengalaman-item" style="margin-bottom: 20px; padding-bottom: 15px;">
                    <div class="form-group">
                        <label>Nama Perusahaan / Instansi</label>
                        <input type="text" name="nama_perusahaan[]" class="form-control" placeholder="Contoh: PT Tech Indonesia">
                    </div>
                    <div class="form-group">
                        <label>Jabatan / Posisi</label>
                        <input type="text" name="jabatan[]" class="form-control" placeholder="Contoh: Staff Administrasi">
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Mulai Kerja</label>
                            <input type="date" name="mulai_kerja[]" class="form-control">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Selesai Kerja</label>
                            <input type="date" name="selesai_kerja[]" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Alasan Keluar</label>
                        <input type="text" name="alasan_keluar[]" class="form-control" placeholder="Tulis alasan singkat...">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" name="simpan_pengalaman" class="btn-simpan-full" style="background-color: #0d6efd; color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; margin-top: 10px;">Simpan Pengalaman Kerja</button>
    </form>
</div>

<!-- KARTU 3: RIWAYAT PENDIDIKAN -->
<div class="card-profil" style="margin-top: 25px;">
    <form action="" method="POST">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;">
            <div class="card-title" style="color: #0d6efd; margin-bottom: 0; font-weight: 700; font-size: 16px;">Riwayat Pendidikan</div>
            <!-- Tombol memicu fungsi JavaScript di bawah -->
            <button type="button" onclick="tambahBarisPendidikan()" style="background-color: #0d6efd; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah Jenjang</button>
        </div>

        <!-- PERBAIKAN: ID diubah menjadi 'container-pendidikan' agar sinkron dengan JavaScript -->
        <div id="container-pendidikan">
            <?php if (!empty($data_pendidikan)): ?>
                <?php foreach ($data_pendidikan as $index => $pend): ?>
                    <div class="item-pendidikan-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px; position: relative;">
                        <?php if ($index > 0): ?>
                            <div style="text-align: right;"><button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button></div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                <label style="font-size: 11px; font-weight: bold; color: #475569;">Jenjang</label>
                                <select name="pendidikan[]" class="form-control" required style="padding:6px 12px; font-size:13px;">
                                    <option value="">-- Pilih --</option>
                                    <option value="SMA" <?= (strcasecmp($pend['jenjang'] ?? '', 'SMA') === 0) ? 'selected' : ''; ?>>SMA / SMK / MA</option>
                                    <option value="D3"  <?= (strcasecmp($pend['jenjang'] ?? '', 'D3') === 0) ? 'selected' : ''; ?>>D3 (Diploma)</option>
                                    <option value="S1 (Sarjana)" <?= (strcasecmp($pend['jenjang'] ?? '', 'S1 (Sarjana)') === 0 || strcasecmp($pend['jenjang'] ?? '', 'S1') === 0) ? 'selected' : ''; ?>>S1 (Sarjana)</option>
                                    <option value="S2"  <?= (strcasecmp($pend['jenjang'] ?? '', 'S2') === 0) ? 'selected' : ''; ?>>S2 (Magister)</option>
                                    <option value="S3"  <?= (strcasecmp($pend['jenjang'] ?? '', 'S3') === 0) ? 'selected' : ''; ?>>S3 (Doktor)</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                <label style="font-size: 11px; font-weight: bold; color: #475569;">Institusi</label>
                                <input type="text" name="institusi[]" class="form-control" value="<?= htmlspecialchars($pend['institusi'] ?? ''); ?>" required style="padding:6px 12px; font-size:13px;">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px;">
                            <div class="form-group" style="flex: 2; margin-bottom: 0;">
                                <label style="font-size: 11px; font-weight: bold; color: #475569;">Jurusan</label>
                                <input type="text" name="jurusan[]" class="form-control" value="<?= htmlspecialchars($pend['jurusan'] ?? ''); ?>" style="padding:6px 12px; font-size:13px;">
                            </div>
                            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                <label style="font-size: 11px; font-weight: bold; color: #475569;">Tahun</label>
                                <input type="number" name="tahun_lulus[]" class="form-control" value="<?= htmlspecialchars($pend['tahun_lulus'] ?? ''); ?>" min="1950" max="2035" style="padding:6px 12px; font-size:13px;">
                            </div>
                            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                <label style="font-size: 11px; font-weight: bold; color: #475569;">IPK</label>
                                <input type="number" step="0.01" min="0" max="4" name="ipk[]" class="form-control" value="<?= htmlspecialchars($pend['ipk'] ?? ''); ?>" style="padding:6px 12px; font-size:13px;">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Form bawaan kosong jika data di database kosong -->
                <div class="item-pendidikan-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                    <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: bold; color: #475569;">Jenjang</label>
                            <select name="pendidikan[]" class="form-control" required style="padding:6px 12px; font-size:13px;">
                                <option value="">-- Pilih --</option>
                                <option value="SMA">SMA / SMK / MA</option>
                                <option value="D3">D3 (Diploma)</option>
                                <option value="S1 (Sarjana)">S1 (Sarjana)</option>
                                <option value="S2">S2 (Magister)</option>
                                <option value="S3">S3 (Doktor)</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Institusi</label><input type="text" name="institusi[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 2; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Jurusan</label><input type="text" name="jurusan[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
                        <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Tahun</label><input type="number" name="tahun_lulus[]" class="form-control" min="1950" max="2035" style="padding:6px 12px; font-size:13px;"></div>
                        <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">IPK</label><input type="number" step="0.01" min="0" max="4" name="ipk[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" name="simpan_pendidikan" class="btn-simpan-full" style="background-color: #0d6efd; color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; margin-top: 10px;">Simpan Riwayat Pendidikan</button>
    </form>
</div>

            <!-- KARTU 4: UPLOAD BERKAS -->
            <div class="card-profil">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                    <div class="card-title" style="color: #0d6efd; margin-bottom: 0;">Upload Berkas Pelamar</div>
                    <button type="button" onclick="tambahBarisBerkas()" style="background-color: #198754; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah Berkas</button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <div id="container-berkas">
                        <?php 
                        if (empty($list_berkas)) { $list_berkas[] = ['jenis_berkas' => '', 'nama_file' => '']; }
                        foreach ($list_berkas as $bk) : 
                        ?>
                            <div class="item-berkas-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Nama / Jenis Berkas</label>
                                    <input type="text" name="jenis_berkas[]" class="form-control" value="<?= htmlspecialchars($bk['jenis_berkas'] ?? ''); ?>" required>
                                </div>
                                
                                <input type="hidden" name="file_berkas_lama[]" value="<?= htmlspecialchars($bk['nama_file'] ?? ''); ?>">
                                
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Pilih File Baru (Kosongkan jika tidak ingin mengubah)</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="flex: 1;">
                                        
                                        <?php if (!empty($bk['nama_file'])) : ?>
                                            <a href="uploads/<?= htmlspecialchars($bk['nama_file']); ?>" target="_blank" style="background-color: #0d6efd; color: white; text-decoration: none; padding: 10px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; text-align: center; white-space: nowrap; transition: 0.2s;" onmouseover="this.style.backgroundColor='#0b5ed7'" onmouseout="this.style.backgroundColor='#0d6efd'">
                                                👁 Lihat Berkas
                                            </a>
                                        <?php else : ?>
                                            <span style="font-size: 12px; color: #64748b; font-style: italic; white-space: nowrap;">Belum ada file</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="text-align: right; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                                    <button type="button" onclick="hapusBarisDinamis(this, 'container-berkas')" style="background: none; border: none; color: #dc3545; font-size: 12px; cursor: pointer; font-weight: bold; padding: 0;">Hapus</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="simpan_berkas" class="btn-simpan-full" style="background-color: #198754; width: 100%; margin-top: 10px;">Simpan Berkas Dokumen</button>
                </form>
            </div>

        </div> <!-- PENUTUP KOLOM KANAN -->
    </div> <!-- PENUTUP main-container -->

    <!-- ==================== TAHAP 3: LOGIC JAVASCRIPT DINAMIS MULTI-FORM ==================== -->
    <script>
    // 1. Fungsi bantu global untuk menghapus baris form dinamis secara aman
    function hapusBarisDinamis(btn, containerId) {
        const container = document.getElementById(containerId);
        const baris = btn.closest('.item-pengalaman-row, .item-pendidikan-row, .item-berkas-row, .item-str-row');
        
        // Validasi agar menyisakan minimal 1 baris input di layar agar form tidak kosong total
        if (container.children.length > 1) {
            baris.remove();
        } else {
            alert('Minimal harus menyisakan satu baris data pada formulir ini.');
        }
    }

    // 2. Handler Tambah Baris Dinamis: Riwayat Pengalaman Kerja
    function tambahBarisPengalaman() {
        const container = document.getElementById('container-pengalaman');
        const html = `
            <div class="item-pengalaman-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                <div style="text-align: right;"><button type="button" onclick="hapusBarisDinamis(this, 'container-pengalaman')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button></div>
                <div class="form-group"><label style="font-size: 11px; font-weight: bold; color: #475569;">Nama Perusahaan / Instansi</label><input type="text" name="perusahaan[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                <div class="form-group"><label style="font-size: 11px; font-weight: bold; color: #475569;">Jabatan / Posisi</label><input type="text" name="jabatan[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Mulai Kerja</label><input type="date" name="mulai_kerja[]" class="form-control" required style="padding:4px 12px; font-size:12px;"></div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Selesai Kerja</label><input type="date" name="selesai_kerja[]" class="form-control" style="padding:4px 12px; font-size:12px;"></div>
                </div>
                <div class="form-group" style="margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Alasan Keluar</label><textarea name="alasan_keluar[]" class="form-control" rows="2" placeholder="Tulis alasan singkat..." style="resize: none; padding:6px 12px; font-size:13px;"></textarea></div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

// 3. Handler Tambah Baris Dinamis: Riwayat Pendidikan (FIXED NAME & VALUE)
function tambahBarisPendidikan() {
    const container = document.getElementById('container-pendidikan');
    const html = `
        <div class="item-pendidikan-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
            <div style="text-align: right;"><button type="button" onclick="hapusBarisDinamis(this, 'container-pendidikan')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button></div>
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label style="font-size: 11px; font-weight: bold; color: #475569;">Jenjang</label>
                    <!-- PERBAIKAN: Mengubah name="jenjang[]" menjadi name="pendidikan[]" -->
                    <select name="pendidikan[]" class="form-control" required style="padding:6px 12px; font-size:13px;">
                        <option value="">-- Pilih --</option>
                        <option value="SMA">SMA / SMK / MA</option>
                        <option value="D3">D3 (Diploma)</option>
                        <option value="S1 (Sarjana)">S1 (Sarjana)</option>
                        <option value="S2">S2 (Magister)</option>
                        <option value="S3">S3 (Doktor)</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Institusi</label><input type="text" name="institusi[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
            </div>
            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex: 2; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Jurusan</label><input type="text" name="jurusan[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Tahun</label><input type="number" name="tahun_lulus[]" class="form-control" min="1950" max="2035" style="padding:6px 12px; font-size:13px;"></div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">IPK</label><input type="number" step="0.01" min="0" max="4" name="ipk[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

    // 4. Handler Tambah Baris Dinamis: Upload Berkas Dokumen
    function tambahBarisBerkas() {
        const container = document.getElementById('container-berkas');
        const html = `
            <div class="item-berkas-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                <div class="form-group"><label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Nama / Jenis Berkas</label><input type="text" name="jenis_berkas[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                <input type="hidden" name="file_berkas_lama[]" value="">
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Pilih File Baru</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="flex: 1;">
                        <span style="font-size: 12px; color: #64748b; font-style: italic; white-space: nowrap;">Berkas baru</span>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                    <button type="button" onclick="hapusBarisDinamis(this, 'container-berkas')" style="background: none; border: none; color: #dc3545; font-size: 12px; cursor: pointer; font-weight: bold; padding: 0;">Hapus</button>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    // 5. Handler Preview Unggahan Gambar Foto Profil Instan
    function bacaGambar(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewFoto').src = e.target.result;
                document.getElementById('previewFoto').style.display = 'block';
                if(document.getElementById('placeholderFoto')) {
                    document.getElementById('placeholderFoto').style.display = 'none';
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

</body>
</html>
