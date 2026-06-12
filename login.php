<?php 
session_start(); 

// 1. PENGATURAN KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. LOGIKA PROSES SUBMIT FORM LOGIN
$error_message = "";
if (isset($_POST['login'])) {
    $username_input = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password_input = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // Cek username di database
    $query_login = "SELECT * FROM users WHERE username = '$username_input'";
    $hasil_login = mysqli_query($koneksi, $query_login);
    
    if ($hasil_login && mysqli_num_rows($hasil_login) > 0) {
        $data_user = mysqli_fetch_assoc($hasil_login);
        
        // Cek kecocokan password
        if ($data_user['password'] == $password_input) {
            
            // ================== LOGIKA BLOKIR USER NONAKTIF ==================
            if ($data_user['status'] == 'Nonaktif') {
                $error_message = "Gagal Masuk! Akun Anda berstatus NONAKTIF. Silakan hubungi Administrator Utama.";
            } else {
                // Jika status 'Aktif', izinkan masuk ke dashboard
                $_SESSION['username'] = $data_user['username'];
                
                // Update waktu last_login ke database
                mysqli_query($koneksi, "UPDATE users SET last_login = NOW() WHERE id = '".$data_user['id']."'");
                
                echo "<script>alert('Login Berhasil!'); window.location='dashboard.php';</script>";
                exit();
            }
            // ==============================================================
            
        } else {
            $error_message = "Password yang Anda masukkan salah!";
        }
    } else {
        $error_message = "Username tidak terdaftar!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekrutmen - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .login-card { background: white; padding: 40px; border-radius: 24px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; text-align: center; }
        .brand-title { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
        .brand-subtitle { font-size: 13px; color: #94a3b8; margin-bottom: 30px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; text-align: left; margin-bottom: 18px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid #ced4da; border-radius: 10px; font-size: 14px; font-weight: 600; color: #334155; background: #f8fafc; outline: none; transition: all 0.2s; }
        .form-input:focus { border-color: #4f46e5; background: #ffffff; }
        .btn-login { width: 100%; background: #4f46e5; color: white; border: none; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 10px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }
        .error-alert { background: #fff5f5; border: 1px solid #fee2e2; color: #dc2626; padding: 12px; border-radius: 10px; font-size: 12px; font-weight: 600; margin-bottom: 20px; text-align: left; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-title">MAGANG ID</div>
        <div class="brand-subtitle">Silakan masuk untuk mengelola rekrutmen</div>

        <?php if(!empty($error_message)): ?>
            <div class="error-alert"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- FORM INPUT DENGAN ATTRIBUT NAME YANG SUDAH TERKUNCI RAPAT -->
        <form action="" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-input" placeholder="Masukkan username Anda" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-input" placeholder="Masukkan password Anda" required>
            </div>
            <button type="submit" name="login" class="btn-login">Masuk ke Dashboard</button>
        </form>
    </div>

</body>
</html>
