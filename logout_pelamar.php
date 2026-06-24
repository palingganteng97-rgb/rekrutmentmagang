<?php
session_start();

// 1. Hapus semua data pelamar dari session (Kode Anda)
unset($_SESSION['pelamar_logged_in']);
unset($_SESSION['pelamar_id']);
unset($_SESSION['pelamar_nama']);

// 2. Tambahan: Hancurkan sesi jika sudah tidak ada data yang tersimpan
if (empty($_SESSION)) {
    session_destroy(); 
}

// 3. Lempar kembali ke list lowongan kerja (Kode Anda)
echo "<script>alert('Anda telah keluar akun.'); window.location='lowongan_pelamar.php';</script>";
exit;
?>
