<?php
session_start();
header('Content-Type: application/json');

// 1. KONEKSI DATABASE (Sesuaikan dengan konfigurasi server pusat Anda)
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$conn) {
    echo json_encode(['nilai' => '', 'catatan' => 'Gagal koneksi database']);
    exit;
}

// 2. AMBIL PARAMETER DARI JAVASCRIPT FETCH
$id_lamaran      = $_GET['id_lamaran'] ?? 0;
$id_mst_tahapan  = $_GET['id_mst_tahapan'] ?? 0;

if (!$id_lamaran || !$id_mst_tahapan) {
    echo json_encode(['nilai' => '', 'catatan' => 'Parameter tidak valid']);
    exit;
}

// 3. QUERY MENGAMBIL DATA NILAI YANG SUDAH TERSIMPAN
$query = "SELECT nilai, catatan FROM penilaian_tahapan 
          WHERE lamaran_tahapan_id = '$id_lamaran' 
          AND mst_tahapan_id = '$id_mst_tahapan' 
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    
    // Format output JSON agar bisa dibaca oleh JavaScript
    echo json_encode([
        'nilai'   => $data['nilai'] !== null ? number_format($data['nilai'], 2, '.', '') : '',
        'catatan' => $data['catatan'] ?? ''
    ]);
} else {
    // Jika data belum pernah diinput di database, kirim string kosong
    echo json_encode([
        'nilai'   => '',
        'catatan' => ''
    ]);
}
exit;
