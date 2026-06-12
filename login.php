<?php
session_start();
$host = "10.10.6.59";
$username = "root_host"; // Sesuaikan dengan kredensial database magang Anda
$password = "password";     // Masukkan password database Anda jika ada
$database = "magang_rekrutmen_rs";

mysqli_report(MYSQLI_REPORT_OFF);
$koneksi = @mysqli_connect($host, $username, $password, $database);
$error = "";

if (isset($_POST['login'])) {
    if (!$koneksi) {
        $error = "Gagal terhubung ke database!";
    } else {
        $user_email = mysqli_real_escape_string($koneksi, $_POST['username_email']);
        $pass = $_POST['password'];

        // Mengecek berdasarkan username ATAU email sesuai kolom di HeidiSQL Anda
        $query = "SELECT * FROM users WHERE username = '$user_email' OR email = '$user_email'";
        $result = mysqli_query($koneksi, $query);

        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            // Menggunakan password_verify jika password di database Anda di-hash
            if (password_verify($pass, $row['password']) || $pass === $row['password']) {
                $_SESSION['login'] = true;
                $_SESSION['admin_user'] = $row['username'];
                header("Location: tampilkan.php");
                exit();
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Akun tidak ditemukan!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Glassmorphism UI</title>
    <style>
        /* RESET & BACKGROUND GRADIENT SATIN */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { 
            background: radial-gradient(circle at 80% 20%, #683044 0%, #30264b 40%, #202b46 80%, #3a5775 100%);
            min-height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
        }

        /* GLASS CONTAINER BOX */
        .login-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            width: 380px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        /* AVATAR ICON USER */
        .avatar-container {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            margin: 0 auto 40px auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .avatar-icon {
            width: 55px;
            height: 55px;
            background-color: rgba(255, 255, 255, 0.4);
            clip-path: path('M27.5 25C34.4036 25 40 19.4036 40 12.5C40 5.59644 34.4036 0 27.5 0C20.5964 0 15 5.59644 15 12.5C15 19.4036 20.5964 25 27.5 25ZM27.5 30C12.3122 30 0 42.3122 0 57.5C0 58.8807 1.11929 60 2.5 60H52.5C53.8807 60 55 58.8807 55 57.5C55 42.3122 42.6878 30 27.5 30Z');
            transform: scale(0.8) translateY(5px);
        }

        /* FORM FIELD MINIMALIS LINE */
        .form-group {
            position: relative;
            margin-bottom: 30px;
            border-bottom: 1.5px solid rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            padding-bottom: 5px;
        }
        .form-group span {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            margin-right: 15px;
            display: inline-block;
            width: 20px;
            text-align: center;
        }
        .form-group input {
            width: 100%;
            background: none;
            border: none;
            outline: none;
            color: #ffffff;
            font-size: 15px;
            padding: 5px 0;
        }
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        /* OPTIONS (REMEMBER ME & FORGOT) */
        .options-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 35px;
            padding: 0 2px;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .remember-me input {
            accent-color: #5c6bc0;
            cursor: pointer;
        }
        .forgot-link {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link:hover {
            color: #ffffff;
        }

        /* BUTTON LOGIN BLUE GLOW GRADIENT */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #4353b3 0%, #4770c9 50%, #468ee6 100%);
            border: none;
            border-radius: 25px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 2px;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(67, 83, 179, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(67, 83, 179, 0.6);
        }

        /* NOTIFIKASI ERROR */
        .error-msg {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #ff9f9f;
            padding: 10px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <!-- CARD UTAMA -->
    <div class="login-card">
        
        <!-- BAGIAN AVATAR BULAT -->
        <div class="avatar-container">
            <div class="avatar-icon"></div>
        </div>

        <!-- PESAN EROR JIKA KONEKSI/LOGIN GAGAL -->
        <?php if(!empty($error)): ?>
            <div class="error-msg"><?= $error; ?></div>
        <?php endif; ?>

        <!-- FORMULIR INPUT -->
        <form method="POST" action="">
            <!-- Input Email ID / Username -->
            <div class="form-group">
                <span>✉</span>
                <input type="text" name="username_email" placeholder="Email ID" required autocomplete="off">
            </div>

            <!-- Input Password -->
            <div class="form-group">
                <span>🔒</span>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <!-- Opsi Tambahan -->
            <div class="options-container">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="lupa_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <!-- Tombol Submit -->
            <button type="submit" name="login" class="btn-login">LOGIN</button>
        </form>
    </div>

</body>
</html>
