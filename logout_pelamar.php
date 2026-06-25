<?php
session_start();

// Hapus seluruh data session pelamar yang aktif
session_unset();
session_destroy();

// PERBAIKAN: Alihkan kembali pengguna ke halaman lowongan, bukan ke halaman login
header("Location: lowongan_pelamar.php");
exit();
?>
