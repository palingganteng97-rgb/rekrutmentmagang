<?php
$host     = "10.10.6.59"; 
$username = "root_host";       
$password = "password";           
$database = "magang_rekrutmen_rs";

$koneksi = mysqli_connect($host, $username, $password, $database);

if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
