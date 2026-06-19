<?php
session_start();

// 1. PENGATURAN ZONA WAKTU
date_default_timezone_set('Asia/Jakarta');

// 2. KONEKSI DATABASE SERVER (Disamakan dengan file utama Anda)
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit;
}

mysqli_query($koneksi, "SET time_zone = '+07:00'");

// 3. PROSES AMBIL DATA BERDASARKAN ID LOWONGAN
if (isset($_GET['id'])) {
    // Memastikan ID berupa angka bulat untuk keamanan database
    $id = intval($_GET['id']);
    
    // Query mengambil baris lowongan sesuai struktur database Anda
    $query = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan WHERE id = $id");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);
        
        // Mengembalikan data sukses dalam format JSON agar bisa dibaca JavaScript
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Data lowongan tidak ditemukan di database.'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Parameter ID tidak valid.'
    ]);
}
?>
