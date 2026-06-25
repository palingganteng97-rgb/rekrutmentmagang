<?php
// Pastikan tidak ada spasi atau baris kosong di atas tag php ini!
session_start();

// Matikan error reporting HTML agar tidak merusak format JSON jika ada notice
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// 1. KONEKSI DATABASE
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit;
}

// KONDISI 1: Cek apakah user sudah login
if (!isset($_SESSION['pelamar_id']) || empty($_SESSION['pelamar_id'])) {
    echo json_encode(['status' => 'belum_login']);
    exit;
}

$pelamar_id = $_SESSION['pelamar_id'];

// 2. AMBIL DATA PROFIL PELAMAR
$query = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = '$pelamar_id'");

// Jika data pelamar tidak ditemukan sama sekali di tabel pelamar
if (mysqli_num_rows($query) == 0) {
    echo json_encode(['status' => 'belum_lengkap']);
    exit;
}

$data_pelamar = mysqli_fetch_assoc($query);

// KONDISI 2: Cek kelengkapan data (Sesuaikan kolom dengan tabel pelamar Anda)
// Memeriksa kolom wajib: nama_lengkap (atau email), nik, telepon, dan alamat
if (empty($data_pelamar['nama_lengkap']) && empty($data_pelamar['email'])) {
    echo json_encode(['status' => 'belum_lengkap']);
    exit;
}

// KONDISI 3: Jika lolos semua validasi, kirim seluruh data profil pelamar
echo json_encode([
    'status' => 'siap_lamar',
    'data' => $data_pelamar
]);
exit;
?>
