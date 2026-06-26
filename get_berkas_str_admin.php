<?php
session_start();
header('Content-Type: application/json');

// Koneksi Database
$host     = "10.10.6.59";
$user_db  = "root_host";
$pass_db  = "password";
$nama_db  = "magang_rekrutmen_rs";

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Koneksi database gagal'
    ]);
    exit;
}

$pelamar_id = isset($_GET['pelamar_id']) ? intval($_GET['pelamar_id']) : 0;

if ($pelamar_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID pelamar tidak valid'
    ]);
    exit;
}

// ======================
// AMBIL BERKAS
// ======================
$data_berkas = [];

$q_berkas = mysqli_query(
    $koneksi,
    "SELECT jenis_berkas,nama_file
     FROM pelamar_berkas
     WHERE pelamar_id='$pelamar_id'"
);

if ($q_berkas) {
    while ($row = mysqli_fetch_assoc($q_berkas)) {
        $data_berkas[] = [
            'jenis_berkas' => $row['jenis_berkas'],
            'nama_file'    => $row['nama_file']
        ];
    }
}

// ======================
// AMBIL STR
// ======================
$data_str = [];

$q_str = mysqli_query(
    $koneksi,
    "SELECT *
     FROM pelamar_str
     WHERE pelamar_id='$pelamar_id'"
);

if ($q_str) {
    while ($row = mysqli_fetch_assoc($q_str)) {

        $data_str[] = [
            'nomor_str'       => $row['nomor_str'],
            'tanggal_terbit'  => $row['tanggal_terbit'],
            'tanggal_expired' => $row['tanggal_expired'],
            'nama_file'       => $row['file_str']
        ];
    }
}

// ======================
// RETURN JSON
// ======================
echo json_encode([
    'status' => 'success',
    'berkas' => $data_berkas,
    'str'    => $data_str
]);

exit;
?>