<?php
session_start();
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) { die("Koneksi gagal: " . mysqli_connect_error()); }

$error_message = "";
if (isset($_POST['login'])) {
    $email_input    = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password_input = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // DETEKSI OTOMATIS NAMA KOLOM PASSWORD DI DATABASE ANDA
    $cek_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar LIKE 'password'");
    $nama_kolom_pass = (mysqli_num_rows($cek_kolom) > 0) ? 'password' : 'sandi';

    // Query menyesuaikan kolom password database secara otomatis
    $query = "SELECT * FROM pelamar WHERE email = '$email_input' AND $nama_kolom_pass = '$password_input'";
    $hasil = mysqli_query($koneksi, $query);
    
    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data_pelamar = mysqli_fetch_assoc($hasil);
        
        $_SESSION['pelamar_logged_in'] = true;
        $_SESSION['pelamar_id']        = $data_pelamar['id'];
        $_SESSION['pelamar_nama']      = !empty($data_pelamar['nama_lengkap']) ? $data_pelamar['nama_lengkap'] : $data_pelamar['email'];
        
        echo "<script>alert('Berhasil Masuk!'); window.location='lowongan_pelamar.php';</script>";
        exit;
    } else {
        $error_message = "Email atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Portal Pelamar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .card { background: white; padding: 40px; border-radius: 24px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); text-align: center; }
        .title { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; text-align: left; margin-bottom: 15px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid #ced4da; border-radius: 10px; font-size: 14px; background: #f8fafc; outline: none; }
        .form-input:focus { border-color: #4f46e5; background: #fff; }
        .btn-submit { width: 100%; background: #4f46e5; color: white; border: none; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .alert { background: #fff5f5; border: 1px solid #fee2e2; color: #dc2626; padding: 12px; border-radius: 10px; font-size: 12px; margin-bottom: 15px; text-align: left; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">MASUK PELAMAR</div>
        <?php if(!empty($error_message)): ?><div class="alert"><?php echo $error_message; ?></div><?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <label>Email Pendaftaran</label>
                <input type="email" name="email" class="form-input" required placeholder="Masukkan email Anda">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-input" required placeholder="Masukkan password Anda">
            </div>
            <button type="submit" name="login" class="btn-submit">Masuk Ke Lowongan</button>
            <p style="font-size: 13px; color: #64748b; margin-top: 20px;">Belum punya akun? <a href="daftar_pelamar.php" style="color: #00b57a; text-decoration: none; font-weight: 600;">Daftar baru</a></p>
        </form>
    </div>
</body>
</html>
