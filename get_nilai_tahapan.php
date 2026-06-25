<?php
session_start();
header('Content-Type: application/json');

// 1. KONEKSI DATABASE (Menggunakan file koneksi.php Anda agar aman)
include 'koneksi.php';

// 2. AMBIL PARAMETER DARI JAVASCRIPT FETCH
$id_lamaran     = isset($_GET['id_lamaran']) ? intval($_GET['id_lamaran']) : 0;
$id_mst_tahapan = isset($_GET['id_mst_tahapan']) ? intval($_GET['id_mst_tahapan']) : 0;

if (!$id_lamaran || !$id_mst_tahapan) {
    echo json_encode(['nilai' => '', 'catatan' => 'Parameter tidak valid']);
    exit;
}

// 3. QUERY MENGAMBIL DATA NILAI YANG SUDAH TERSIMPAN (Lengkap dengan WHERE mst_tahapan_id)
$query = "SELECT nilai, catatan FROM penilaian_tahapan 
          WHERE lamaran_tahapan_id = '$id_lamaran' 
          AND mst_tahapan_id = '$id_mst_tahapan' 
          LIMIT 1";

$result = mysqli_query($koneksi, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode([
        'nilai'   => $row['nilai'],
        'catatan' => $row['catatan']
    ]);
} else {
    // Jika belum pernah diisi, kirim data kosong agar form dikosongkan
    echo json_encode([
        'nilai'   => '',
        'catatan' => ''
    ]);
}
exit;
