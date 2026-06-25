<?php
session_start();

// Hapus semua data session yang tersimpan
session_unset();
session_destroy();

// Alihkan kembali pengguna ke halaman login utama
header("Location: login_pelamar.php");
exit();
?>
