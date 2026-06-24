<?php
// Pastikan tidak ada spasi atau baris kosong sebelum tag <?php di atas

$host     = "10.10.6.59"; 
$username = "root_host";       
$password = "password";           
$database = "magang_rekrutmen_rs";

$koneksi = mysqli_connect($host, $username, $password, $database);

if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
// JANGAN menuliskan tag penutup ?> di akhir file untuk menghindari kebocoran spasi/whitespace
