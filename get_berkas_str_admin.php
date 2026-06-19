<?php
session_start();
header('Content-Type: application/json');

// 1. KONEKSI DATABASE SERVER (Disamakan dengan data_pelamar.php)
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit;
}

// 2. PROSES AMBIL DATA BERDASARKAN ID PELAMAR
if (isset($_GET['pelamar_id'])) {
    $pelamar_id = intval($_GET['pelamar_id']);
    
    // A. Tarik semua berkas dokumen terunggah milik pelamar ini
    $list_berkas = [];
    $query_berkas = mysqli_query($koneksi, "SELECT jenis_berkas, nama_file FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
    if ($query_berkas) {
        while ($row = mysqli_fetch_assoc($query_berkas)) {
            $list_berkas[] = $row;
        }
    }

    // B. Tarik semua data STR aktif milik pelamar ini
    $list_str = [];
    $query_str = mysqli_query($koneksi, "SELECT nomor_str, tanggal_terbit, tanggal_expired, file_str FROM pelamar_str WHERE pelamar_id = $pelamar_id");
    if ($query_str) {
        while ($row = mysqli_fetch_assoc($query_str)) {
            $list_str[] = $row;
        }
    }

    // C. Kembalikan data sukses dalam satu paket JSON terpadu
    echo json_encode([
        'status' => 'success',
        'berkas' => $list_berkas,
        'str'    => $list_str
    ]);

} else {
    echo json_encode(['status' => 'error', 'message' => 'Parameter ID pelamar tidak valid']);
}
?>
