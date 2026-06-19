<?php
session_start();
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) { die("Koneksi gagal: " . mysqli_connect_error()); }

$error_message = "";
if (isset($_POST['daftar'])) {
    $email    = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // Cek duplikasi email
    $cek_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE email = '$email'");
    
    if (mysqli_num_rows($cek_user) > 0) {
        $error_message = "Email tersebut sudah terdaftar! Gunakan email lain.";
    } else {
        // DETEKSI OTOMATIS NAMA KOLOM PASSWORD DI DATABASE ANDA
        $cek_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM pelamar LIKE 'password'");
        $nama_kolom_pass = (mysqli_num_rows($cek_kolom) > 0) ? 'password' : 'sandi';

        // Jalankan query dinamis sesuai nama kolom yang ada
        $query_daftar = "INSERT INTO pelamar (email, $nama_kolom_pass, nama_lengkap) VALUES ('$email', '$password', '$email')";
        
        if (mysqli_query($koneksi, $query_daftar)) {
            echo "<script>alert('Pendaftaran Akun Sukses! Silakan masuk.'); window.location='login_pelamar.php';</script>";
            exit;
        } else {
            $error_message = "Gagal mendaftar: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Pelamar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .card { background: white; padding: 40px; border-radius: 24px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); text-align: center; }
        .title { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; text-align: left; margin-bottom: 15px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid #ced4da; border-radius: 10px; font-size: 14px; background: #f8fafc; outline: none; }
        .form-input:focus { border-color: #00b57a; background: #fff; }
        .btn-submit { width: 100%; background: #00b57a; color: white; border: none; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .alert { background: #fff5f5; border: 1px solid #fee2e2; color: #dc2626; padding: 12px; border-radius: 10px; font-size: 12px; margin-bottom: 15px; text-align: left; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">DAFTAR PELAMAR</div>
        <?php if(!empty($error_message)): ?><div class="alert"><?php echo $error_message; ?></div><?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <label>Alamat Email</label>
                <input type="email" name="email" class="form-input" required placeholder="Masukkan email aktif Anda">
            </div>
            <div class="form-group">
                <label>Buat Password</label>
                <input type="password" name="password" class="form-input" required placeholder="Masukkan kata sandi baru">
            </div>
            <button type="submit" name="daftar" class="btn-submit">Buat Akun Sekarang</button>
            <p style="font-size: 13px; color: #64748b; margin-top: 20px;">Sudah punya akun? <a href="login_pelamar.php" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Masuk disini</a></p>
        </form>
    </div>
</body>
</html>
