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

$error_message = "";
$success_message = "";

// 2. LOGIKA MEMPROSES SUBMIT GANTI PASSWORD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ganti_password_submit'])) {
    $email_input    = mysqli_real_escape_string($koneksi, trim($_POST['email']));
    $password_baru  = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // Pengecekan 1: Pastikan email terdaftar di dalam tabel users
    $query_cek = mysqli_query($koneksi, "SELECT id FROM users WHERE email = '$email_input'");
    
    if (mysqli_num_rows($query_cek) > 0) {
        // Pengecekan 2: Update kata sandi baru murni teks biasa agar cocok dengan database Anda
        $query_update = "UPDATE users SET password = '$password_baru' WHERE email = '$email_input'";
        
        if (mysqli_query($koneksi, $query_update)) {
            // Memunculkan pop-up sukses asli bawaan browser dan mengalihkan ke halaman masuk pelamar
            echo "<script>
                    alert('Kata sandi akun Anda berhasil diperbarui! Silakan masuk kembali dengan sandi baru Anda.');
                    window.location.href = 'login_pelamar.php';
                  </script>";
            exit();
        } else {
            $error_message = "Gagal memperbarui kata sandi: " . mysqli_error($koneksi);
        }
    } else {
        $error_message = "Alamat email tidak ditemukan di dalam sistem karir!";
    }
}
?>

<!DOCTYPE html><html class="light" lang="id"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Masuk | RSI Kendal Careers</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "secondary-fixed": "#e0e3e5",
                    "on-background": "#0b1c30",
                    "on-surface-variant": "#3d4948",
                    "inverse-surface": "#213145",
                    "error": "#ba1a1a",
                    "surface-container": "#e5eeff",
                    "primary-container": "#4db9b2",
                    "on-primary-container": "#004642",
                    "on-primary": "#ffffff",
                    "tertiary": "#545f73",
                    "on-tertiary-fixed-variant": "#3c475a",
                    "tertiary-fixed-dim": "#bcc7de",
                    "on-tertiary-fixed": "#111c2d",
                    "tertiary-container": "#9ea9c0",
                    "secondary-container": "#e0e3e5",
                    "primary-fixed-dim": "#6ed7d0",
                    "primary-fixed": "#8bf4ec",
                    "primary": "#006a65",
                    "on-error-container": "#93000a",
                    "inverse-primary": "#6ed7d0",
                    "surface-container-highest": "#d3e4fe",
                    "surface-container-high": "#dce9ff",
                    "on-primary-fixed-variant": "#00504c",
                    "surface-container-lowest": "#ffffff",
                    "outline-variant": "#bcc9c7",
                    "on-tertiary-container": "#333e51",
                    "surface-container-low": "#eff4ff",
                    "surface-bright": "#f8f9ff",
                    "on-secondary": "#ffffff",
                    "surface-variant": "#d3e4fe",
                    "surface-tint": "#006a65",
                    "on-primary-fixed": "#00201e",
                    "on-secondary-container": "#626567",
                    "on-error": "#ffffff",
                    "error-container": "#ffdad6",
                    "outline": "#6d7a78",
                    "inverse-on-surface": "#eaf1ff",
                    "on-secondary-fixed-variant": "#444749",
                    "on-tertiary": "#ffffff",
                    "secondary": "#5c5f61",
                    "surface-dim": "#cbdbf5",
                    "secondary-fixed-dim": "#c4c7c9",
                    "surface": "#f8f9ff",
                    "on-secondary-fixed": "#191c1e",
                    "on-surface": "#0b1c30",
                    "background": "#f8f9ff",
                    "tertiary-fixed": "#d8e3fb"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "stack-sm": "8px",
                    "margin-desktop": "40px",
                    "section-gap": "48px",
                    "container-max": "1280px",
                    "margin-mobile": "16px",
                    "stack-lg": "24px",
                    "stack-md": "12px",
                    "gutter": "16px"
            },
            "fontFamily": {
                    "headline-md": ["Inter"],
                    "headline-sm": ["Inter"],
                    "display-lg": ["Inter"],
                    "label-sm": ["Inter"],
                    "body-md": ["Inter"],
                    "label-md": ["Inter"],
                    "display-lg-mobile": ["Inter"],
                    "body-lg": ["Inter"]
            },
            "fontSize": {
                    "headline-md": ["26px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "headline-sm": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "display-lg": ["36px", {"lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                    "body-md": ["14px", {"lineHeight": "22px", "fontWeight": "400"}],
                    "label-md": ["13px", {"lineHeight": "18px", "letterSpacing": "0.01em", "fontWeight": "500"}],
                    "display-lg-mobile": ["30px", {"lineHeight": "36px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}]
            }
          },
        },
      }
    </script>
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .soft-shadow {
            box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.05);
        }
        .hover-card:hover {
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
            transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body class="bg-background text-on-background min-h-screen flex flex-col">
<!-- TopNavBar (Shared Component Guide) -->
<header class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-4 md:px-margin-desktop h-16 bg-surface dark:bg-on-background shadow-sm">
<div class="flex items-center gap-stack-sm">
<img alt="RSI Kendal Logo" class="h-10 w-auto" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA7_eG7QY9kXyoYohLINJaqjG5Ak7zoa-aYo64ludFyBmQMCmnQaPbgl4C7r59jvEtYCW1QwjwTQIql5N-DlE0PHSlwWscTiD3LJDxyq1rOA91YS-yBfGlORJ3Euxl5s3GJ4n8a7UMzTARS-6DOCObwD0XNVw5696zRXHzsVY91i8vwyPla0B3OlIxm0mK5MUgOWoH9RS5S0-NZdyosZZjnq7VrE9u8GNzYTnbeIHqY7GNoW_NOrCTCoYLmpbfm50U8IfD0f58k7E8">
<span class="text-headline-sm font-headline-sm font-bold text-primary dark:text-primary-fixed">RSI Kendal Careers</span>
</div>
</header>

<main class="flex-grow flex items-center justify-center pt-20 pb-10 px-4 relative overflow-hidden">
<!-- Background Atmospheric Element -->
<div class="absolute inset-0 z-0 opacity-10">
<div class="absolute top-0 right-0 w-[360px] h-[360px] bg-primary rounded-full blur-[90px] -mr-40 -mt-40" style="transform: translate(9.67188px, 19.6522px);"></div>
<div class="absolute bottom-0 left-0 w-[300px] h-[300px] bg-tertiary rounded-full blur-[80px] -ml-32 -mb-32" style="transform: translate(-14.5078px, -29.4783px);"></div>
</div>
<div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 bg-surface rounded-2xl overflow-hidden soft-shadow relative z-10 border border-outline-variant/30">
<!-- Left Side: Branding/Visual -->
<div class="hidden md:block relative overflow-hidden group">
<div class="absolute inset-0 bg-primary/20 mix-blend-overlay z-10"></div>
<div class="absolute inset-0 bg-gradient-to-t from-on-background/80 via-on-background/20 to-transparent z-20"></div>
<img alt="Hospital Environment" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" src="bg.png">
<div class="absolute bottom-0 left-0 p-8 z-30">
<h1 class="font-display-lg text-display-lg text-white mb-3">Membangun Karir Profesional dengan Nilai Islami</h1>
<p class="font-body-lg text-body-lg text-surface-container-low opacity-90 max-w-md">
                        Bergabunglah dengan tim medis terbaik di RSI Kendal. Kami mencari talenta yang berdedikasi tinggi untuk melayani masyarakat.
                    </p>
</div>
</div>

<!-- Right Side: Lupa Password Form -->
<div class="p-6 md:p-10 flex flex-col justify-center bg-white">
    <div class="mb-8 text-center md:text-left">
        <h2 class="font-headline-md text-headline-md text-on-background mb-2">Lupa Password</h2>
        <p class="font-body-md text-body-md text-outline">Silakan ganti password akun Anda di sini.</p>
    </div>
    
    <!-- Memastikan method POST aktif dan name tombol dikenali PHP -->
    <form class="space-y-4" id="resetPasswordForm" method="POST" action="">
        
        <!-- PERBAIKAN FINAL: Menambahkan input tersembunyi sebagai penanda parameter submit untuk PHP -->
        <input type="hidden" name="ganti_password_submit" value="1">
        
        <!-- Notifikasi Pesan Eror di atas Input jika Validasi Gagal -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-error-container text-on-error-container p-3 rounded-xl text-body-md border border-error/20 text-left">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Email Field -->
        <div class="space-y-1.5 text-left">
            <label class="block font-label-md text-label-md text-on-surface-variant" for="email">Alamat Email</label>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-[18px]">mail</span>
                <input class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-outline-variant focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all outline-none text-body-md font-body-md bg-white" id="email" name="email" placeholder="budi.kurniawan@gmail.com" required type="email">
            </div>
        </div>

        <!-- Password Field -->
        <div class="space-y-1.5 text-left">
            <label class="block font-label-md text-label-md text-on-surface-variant" for="password">Kata Sandi Baru</label>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-[18px]">lock</span>
                <input class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-outline-variant focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all outline-none text-body-md font-body-md bg-white" id="password" name="password" placeholder="••••••••" required type="password">
                <button class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors" onclick="togglePassword()" type="button">
                    <span class="material-symbols-outlined text-[18px]" id="passwordIcon">visibility</span>
                </button>
            </div>
        </div>

        <div class="flex items-center gap-2 py-1 text-left">
            <input class="w-4 h-4 text-primary border-outline-variant rounded focus:ring-primary" id="remember" type="checkbox">
            <label class="font-label-md text-label-md text-on-surface-variant select-none" for="remember">Ingat saya untuk sesi berikutnya</label>
        </div>

        <!-- Tombol submit utama -->
        <button type="submit" name="ganti_password_submit" class="w-full bg-primary text-on-primary py-3 rounded-xl font-headline-sm text-label-md hover:shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2">
            Ganti Password
        </button>
    </form>

    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-outline-variant"></div>
        </div>
    </div>

    <p class="text-center font-body-md text-body-md text-outline">
        Sudah ingat akun Anda? <a class="text-primary font-bold hover:underline" href="login_pelamar.php">Masuk Sekarang</a>
    </p>
</div>

<script>
    // 1. FUNGSI UNTUK MENYEMBUNYIKAN & MENAMPILKAN KATA SANDI
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('passwordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordIcon.innerText = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            passwordIcon.innerText = 'visibility';
        }
    }

    // 2. LOGIKA PENANGANAN SUBMIT FORMULIR (SINKRONISASI KE resetPasswordForm)
    document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
        // PERBAIKAN: e.preventDefault() DIHAPUS agar form bisa terkirim ke server PHP
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        
        // Mempertahankan visual feedback animasi berputar saat tombol ditekan
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Memproses...';
        
        // PERBAIKAN: setTimeout dan alert pengganggu dihapus agar data langsung diproses ke database
    });

    // 3. EFEK INTERAKSI LATAR BELAKANG (MOUSE MOVE EFFECT)
    document.addEventListener('mousemove', (e) => {
        const x = e.clientX / window.innerWidth;
        const y = e.clientY / window.innerHeight;
        
        const lights = document.querySelectorAll('.absolute.inset-0.z-0 div');
        // Validasi untuk memastikan elemen div background ada agar tidak memicu error JS
        if (lights && lights.length >= 2) { 
            lights[0].style.transform = `translate(${x * 20}px, ${y * 20}px)`;
            lights[1].style.transform = `translate(${-x * 30}px, ${-y * 30}px)`;
        }
    });
</script>

</body>
</html>
