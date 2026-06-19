<?php
session_start();
// Hapus semua data pelamar dari session
unset($_SESSION['pelamar_logged_in']);
unset($_SESSION['pelamar_id']);
unset($_SESSION['pelamar_nama']);

// Lempar kembali ke list lowongan kerja
echo "<script>alert('Anda telah keluar akun.'); window.location='lowongan_pelamar.php';</script>";
exit;
?>
