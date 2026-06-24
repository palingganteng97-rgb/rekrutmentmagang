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
    // Menggunakan 'email' karena input form Anda bertipe email & name="email"
    $email_input    = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password_input = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // Cari data di database (Sesuaikan nama kolom email/username jika berbeda di DB Anda)
    $query_login = "SELECT * FROM users WHERE email = '$email_input' OR username = '$email_input'";
    $hasil_login = mysqli_query($koneksi, $query_login);
    
    if ($hasil_login && mysqli_num_rows($hasil_login) > 0) {
        $data_user = mysqli_fetch_assoc($hasil_login);
        
        // Cek kecocokan password
        if ($data_user['password'] == $password_input) {
            
            // Logika Blokir User Nonaktif
            if ($data_user['status'] == 'Nonaktif') {
                $error_message = "Gagal Masuk! Akun Anda berstatus NONAKTIF. Silakan hubungi Administrator Utama.";
            } else {
                // SINKRONISASI SESI UNTUK HALAMAN LOWONGAN
                $_SESSION['username']     = $data_user['username'];
                $_SESSION['pelamar_id']   = $data_user['id'];
                
                // Ambil dari kolom nama_lengkap atau nama. Jika tidak ada, pakai username pembantu.
                if (!empty($data_user['nama_lengkap'])) {
                    $_SESSION['pelamar_nama'] = $data_user['nama_lengkap'];
                } elseif (!empty($data_user['nama'])) {
                    $_SESSION['pelamar_nama'] = $data_user['nama'];
                } else {
                    $_SESSION['pelamar_nama'] = $data_user['username'];
                }
                
                // Update waktu last_login ke database
                mysqli_query($koneksi, "UPDATE users SET last_login = NOW() WHERE id = '".$data_user['id']."'");
                
                // Berpindah langsung ke halaman lowongan pelamar
                header("Location: lowongan_pelamar.php");
                exit();
            }
            
        } else {
            $error_message = "Password yang Anda masukkan salah!";
        }
    } else {
        $error_message = "Alamat Email atau Username tidak terdaftar!";
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
<!-- Right Side: Sign In Form -->
<div class="p-6 md:p-10 flex flex-col justify-center">
<div class="mb-8 text-center md:text-left">
<h2 class="font-headline-md text-headline-md text-on-background mb-2">Selamat Datang Kembali</h2>
<p class="font-body-md text-body-md text-outline">Silakan masuk ke akun karir Anda untuk melamar pekerjaan atau melihat status aplikasi.</p>
</div>
<form class="space-y-4" id="loginForm">
<!-- Email Field -->
<div class="space-y-1.5">
<label class="block font-label-md text-label-md text-on-surface-variant" for="email">Alamat Email</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-[18px]">mail</span>
<input class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-outline-variant focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all outline-none text-body-md font-body-md bg-white" id="email" name="email" placeholder="nama@email.com" required="" type="email">
</div>
</div>
<!-- Password Field -->
<div class="space-y-1.5">
<div class="flex justify-between items-center">
<label class="block font-label-md text-label-md text-on-surface-variant" for="password">Kata Sandi</label>
<a class="font-label-md text-label-md text-primary hover:underline transition-all" href="#">Lupa kata sandi?</a>
</div>
<div class="relative">
<span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-[18px]">lock</span>
<input class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-outline-variant focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all outline-none text-body-md font-body-md bg-white" id="password" name="password" placeholder="••••••••" required="" type="password">
<button class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors" onclick="togglePassword()" type="button">
<span class="material-symbols-outlined text-[18px]" id="passwordIcon">visibility</span>
</button>
</div>
</div>
<div class="flex items-center gap-2">
<input class="w-4 h-4 text-primary border-outline-variant rounded focus:ring-primary" id="remember" type="checkbox">
<label class="font-label-md text-label-md text-on-surface-variant select-none" for="remember">Ingat saya untuk sesi berikutnya</label>
</div>
<button class="w-full bg-primary text-on-primary py-3 rounded-xl font-headline-sm text-label-md hover:shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2" type="submit">
                        Masuk Ke Akun
                    </button>
</form>

<div class="space-y-4">
</div>
<p class="mt-6 text-center font-body-md text-body-md text-outline">Belum punya akun? <a class="text-primary font-bold hover:underline" href="#">Daftar Sekarang</a>
</p>
</div>
</div>
</main>

<script>
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

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Basic UI feedback for interaction
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Memproses...';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Sistem sedang dalam pengembangan. Silakan hubungi admin HR RSI Kendal.');
            }, 1500);
        });

        // Mouse move effect for background subtle interaction
        document.addEventListener('mousemove', (e) => {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            const lights = document.querySelectorAll('.absolute.inset-0.z-0 div');
            lights[0].style.transform = `translate(${x * 20}px, ${y * 20}px)`;
            lights[1].style.transform = `translate(${-x * 30}px, ${-y * 30}px)`;
        });
    </script>
</body>
</html>
