<?php
// 1. PENGATURAN KONEKSI DATABASE (Menggunakan IP server Anda)
$host     = "10.10.6.59"; 
$username = "root_host";       
$password = "password";           
$database = "magang_rekrutmen_rs";

$koneksi = mysqli_connect($host, $username, $password, $database);

// Periksa apakah koneksi berhasil atau tidak
if (!$koneksi) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Koneksi database gagal: ' . mysqli_connect_error()]);
    exit;
}

// 2. SET HEADER UNTUK MENGIRIM DATA JSON
header('Content-Type: application/json');

// 3. MEMPROSES REQUEST DARI JAVASCRIPT
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Mengambil data lowongan spesifik berdasarkan ID yang diklik
    $query = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan WHERE id = '$id'");
    
    if (mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);
        
        // Kirim data murni hasil database dalam bentuk JSON ke browser
        echo json_encode($data);
        exit;
    }
}

// Respon cadangan jika data ID tidak ditemukan di database
echo json_encode([
    'judul_lowongan' => 'Detail Tidak Tersedia',
    'deskripsi' => '-',
    'kualifikasi' => '-',
    'persyaratan' => '-',
    'tanggal_mulai' => '-',
    'tanggal_selesai' => '-',
    'jumlah_kebutuhan' => '0'
]);
