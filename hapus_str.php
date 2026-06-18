<?php
session_start();

// Proteksi halaman
if (!isset($_SESSION['pelamar_logged_in'])) {
    header("Location: lowongan_pelamar.php");
    exit;
}

// Pengaturan koneksi database
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (isset($_GET['id'])) {
    $id_str = intval($_GET['id']);
    $pelamar_id = $_SESSION['pelamar_id'];

    // 1. Ambil nama file lama terlebih dahulu untuk dihapus dari folder uploads
    $query_file = mysqli_query($koneksi, "SELECT file_str FROM pelamar_str WHERE id = $id_str AND pelamar_id = $pelamar_id");
    if ($data_file = mysqli_fetch_assoc($query_file)) {
        if (!empty($data_file['file_str']) && file_exists("uploads/" . $data_file['file_str'])) {
            unlink("uploads/" . $data_file['file_str']); // Hapus file fisik dari server
        }
    }

    // 2. Hapus data dari tabel database
    mysqli_query($koneksi, "DELETE FROM pelamar_str WHERE id = $id_str AND pelamar_id = $pelamar_id");
}

// Kembalikan pelamar ke halaman profil utama
header("Location: profil_pelamar.php");
exit;
