<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 1. KONFIGURASI DATABASE ADMIN
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Kunci Kredensial Google Cloud Anda yang tadi
$client_id     = "://googleusercontent.com";
$client_secret = "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";
$redirect_uri  = "http://localhost:8080/rekrutmentmagang/auth-google.php";

// Tangkap ID lowongan kerja yang dipilih pelamar agar tidak hilang
if (isset($_GET['lowongan_id'])) {
    $_SESSION['target_lowongan_id'] = intval($_GET['lowongan_id']);
}

// =========================================================================
// LANGKAH B: JIKA GOOGLE MENGIRIMKAN KODE RESPONS BALIK (USER SELESAI PILIH AKUN)
// =========================================================================
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Tukar kode autentikasi menjadi Access Token via cURL instan
    $url = 'https://googleapis.com';
    $params = [
        'code'          => $code,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        // Ambil data profil riil (Email & Nama) langsung dari API Google
        $user_info_url = 'https://googleapis.com' . $token_data['access_token'];
        $user_info_json = file_get_contents($user_info_url);
        $user_data = json_decode($user_info_json, true);

        if (isset($user_data['email'])) {
            $email_pelamar = mysqli_real_escape_string($koneksi, $user_data['email']);
            $nama_pelamar  = mysqli_real_escape_string($koneksi, $user_data['name']);
            $lowongan_id   = isset($_SESSION['target_lowongan_id']) ? $_SESSION['target_lowongan_id'] : 1;

            // Masukkan data pelamar ke database admin
            $cek_user = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE email = '$email_pelamar'");
            if (mysqli_num_rows($cek_user) > 0) {
                $data_p = mysqli_fetch_assoc($cek_user);
                $pelamar_id = $data_p['id'];
            } else {
                mysqli_query($koneksi, "INSERT INTO pelamar (nama, email, created_at, updated_at) VALUES ('$nama_pelamar', '$email_pelamar', NOW(), NOW())");
                $pelamar_id = mysqli_insert_id($koneksi);
            }

            // Catat ke tabel rekrutmen_lamaran & lamaran_tahapan
            $insert_lamaran = mysqli_query($koneksi, "INSERT INTO rekrutmen_lamaran (lowongan_id, created_at, updated_at) VALUES ('$lowongan_id', NOW(), NOW())");
            if ($insert_lamaran) {
                $lamaran_id = mysqli_insert_id($koneksi);
                mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, status, created_at, updated_at) VALUES ('$lamaran_id', 'Pending', NOW(), NOW())");
            }

            // Tampilkan alert sukses dan lempar kembali ke halaman utama
            echo "<script>
                    alert('Sukses Melamar! Akun Google (" . $email_pelamar . ") Anda berhasil didaftarkan ke sistem seleksi.');
                    window.location.href = 'lowongan_pelamar.php';
                  </script>";
            exit();
        }
    } else {
        echo "Gagal mendapatkan token autentikasi dari Google. Pastikan URI pengalihan di Google Console sudah tepat.";
    }
} else {
    // =========================================================================
    // LANGKAH A: ALIHKAN BROWSER LANGSUNG KE LOG IN GOOGLE RESMI SECARA OTOMATIS
    // =========================================================================
    $google_oauth_url = "https://google.com?" . http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'openid profile email',
        'access_type'   => 'offline',
        'prompt'        => 'select_account' // Memaksa Google memunculkan daftar pilihan akun
    ]);

    header("Location: " . $google_oauth_url);
    exit();
}
