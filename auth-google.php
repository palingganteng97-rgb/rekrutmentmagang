<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 1. KONEKSI KE HEIDISQL DATABASE RS ANDA
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. KREDENSIAL CLIENT ID DAN SECRET RESMI MILIK ANDA
$client_id     = "://googleusercontent.com"; 
$client_secret = "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"; 
$redirect_uri  = "http://localhost:8080/rekrutmentmagang/auth-google.php";

// Ambil dan simpan ID Lowongan yang sedang dilamar ke Session agar tidak hilang
if (isset($_GET['lowongan_id'])) {
    $_SESSION['target_lowongan_id'] = intval($_GET['lowongan_id']);
}

// =========================================================================
// PROSES JIKA USER BARUSAN SELESAI MEMILIH EMAIL GMAIL-NYA (CALLBACK)
// =========================================================================
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Tukar Authorization Code dengan Access Token ke Server Google via cURL
    $token_url = 'https://googleapis.com';
    $params = [
        'code'          => $code,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass proteksi SSL lokal agar tidak macet
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        // PERBAIKAN UTAMA: Mengambil profil data Google menggunakan cURL (Anti-Gagal)
        $profile_url = 'https://googleapis.com';
        
        $ch_profile = curl_init();
        curl_setopt($ch_profile, CURLOPT_URL, $profile_url . '?access_token=' . $token_data['access_token']);
        curl_setopt($ch_profile, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_profile, CURLOPT_SSL_VERIFYPEER, false); // Bypass proteksi SSL lokal
        $user_info_json = curl_exec($ch_profile);
        curl_close($ch_profile);
        
        $user_info = json_decode($user_info_json, true);

        if (isset($user_info['email'])) {
            $email = mysqli_real_escape_string($koneksi, $user_info['email']);
            $nama  = mysqli_real_escape_string($koneksi, $user_info['name']);
            $id_lowongan = isset($_SESSION['target_lowongan_id']) ? $_SESSION['target_lowongan_id'] : 1;

            // A. Cek atau Tambahkan Pelamar Baru ke Tabel pelamar
            $cek = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE email = '$email'");
            if (mysqli_num_rows($cek) > 0) {
                $user_db = mysqli_fetch_assoc($cek);
                $pelamar_id = $user_db['id'];
            } else {
                mysqli_query($koneksi, "INSERT INTO pelamar (nama, email, created_at, updated_at) VALUES ('$nama', '$email', NOW(), NOW())");
                $pelamar_id = mysqli_insert_id($koneksi);
            }

            // B. Daftarkan lamaran ke tabel rekrutmen_lamaran
            $input_lamaran = mysqli_query($koneksi, "INSERT INTO rekrutmen_lamaran (lowongan_id, created_at, updated_at) VALUES ('$id_lowongan', NOW(), NOW())");
            if ($input_lamaran) {
                $lamaran_id = mysqli_insert_id($koneksi);
                // C. Set Tahapan Seleksi Masuk ke lamaran_tahapan (Status: Pending)
                mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, status, created_at, updated_at) VALUES ('$lamaran_id', 'Pending', NOW(), NOW())");
            }

            // Tampilkan alert sukses dan kembalikan pelamar ke halaman lowongan utama Anda
            echo "<script>
                    alert('Sukses Mendaftar! Berkas lamaran atas nama ($email) berhasil dikirim langsung ke sistem seleksi Admin.');
                    window.location.href = 'lowongan_pelamar.php';
                  </script>";
            exit();
        }
    } else {
        echo "<h3>Gagal Autentikasi Token</h3> Respon error dari Google: " . htmlspecialchars($response);
    }
} else {
    // =========================================================================
    // PROSES PERTAMA KALI JALAN: MELEMPAR USER KE HALAMAN LOG IN RESMI GOOGLE
    // =========================================================================
    $auth_endpoint = "https://google.com";
    $query_string = http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'openid profile email',
        'prompt'        => 'select_account' 
    ]);

    header("Location: " . $auth_endpoint . "?" . $query_string);
    exit();
}
