<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. PENGATURAN KONEKSI DATABASE
// =========================================================================
$host           = "10.10.6.59";
$user_db        = "root_host";
$pass_db        = "password";
$nama_db        = "magang_rekrutmen_rs";

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) { 
    die("Koneksi gagal: " . mysqli_connect_error()); 
}

// =========================================================================
// 2. DATA SESI USER LOGIN
// =========================================================================
$pelamar_id   = $_SESSION['pelamar_id'] ?? 0;
$pelamar_nama = $_SESSION['pelamar_nama'] ?? '';

// =========================================================================
// 3. LOGIKA MEMPROSES LAMARAN FINAL (POST)
// =========================================================================
if (isset($_POST['kirim_lamaran_final'])) {
    if (!$pelamar_id) {
        echo "<script>alert('Silakan login terlebih dahulu');location='login_pelamar.php';</script>";
        exit;
    }

    $lowongan_id = (int)$_POST['lowongan_id'];
    $tanggal_sekarang = date('Y-m-d');

    $cek_deadline = mysqli_query($koneksi, "SELECT tanggal_selesai FROM rekrutmen_lowongan WHERE id='$lowongan_id'");
    if ($cek_deadline && mysqli_num_rows($cek_deadline) > 0) {
        $row_deadline = mysqli_fetch_assoc($cek_deadline);
        $tanggal_selesai_db = $row_deadline['tanggal_selesai'];

        if ($tanggal_sekarang > $tanggal_selesai_db) {
            echo "<script>alert('❌ Gagal mengirim! Lowongan pekerjaan ini telah berakhir/ditutup.'); window.location.href='lowongan_pelamar.php';</script>";
            exit;
        }
    }

    $cek = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE lowongan_id='$lowongan_id' AND pelamar_id='$pelamar_id'");

    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('❌ Anda sudah pernah melamar lowongan pekerjaan ini sebelumnya!'); window.location.href='lowongan_pelamar.php';</script>";
        exit;
    } else {
        $cek_enum = mysqli_query($koneksi, "SHOW COLUMNS FROM rekrutmen_lamaran LIKE 'status'");
        $status_final = 'Pending';
        if ($cek_enum && mysqli_num_rows($cek_enum) > 0) {
            $row_enum = mysqli_fetch_assoc($cek_enum);
            $type = $row_enum['Type'];
            preg_match_all("/'([^']+)'/", $type, $matches);
            if (!empty($matches)) { $status_final = $matches; }
        }

        mysqli_query($koneksi, "INSERT INTO rekrutmen_lamaran (lowongan_id, pelamar_id, tanggal_lamaran, status, created_at) VALUES ('$lowongan_id', '$pelamar_id', NOW(), '$status_final', NOW())");
        echo "<script>alert('✓ Selamat! Lamaran pekerjaan Anda berhasil terkirim.'); window.location.href='lowongan_pelamar.php';</script>";
        exit;
    }
}

// =========================================================================
// 4. LOGIKA MENANGKAP PARAMETER FILTER (GET) - KUNCI INDEKS URL
// =========================================================================
$cari_posisi  = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$departemen   = isset($_GET['departemen']) && $_GET['departemen'] != 'Semua Departemen' ? trim($_GET['departemen']) : '';
$tipe_kerja   = isset($_GET['tipe']) && $_GET['tipe'] != 'Semua Tipe' ? trim($_GET['tipe']) : '';

// =========================================================================
// 5. PENYUSUNAN QUERY SQL LOWONGAN MURNI (AMANKAN PRIORITAS STRUKTUR WHERE)
// =========================================================================
$tanggal_sekarang = date('Y-m-d');

$sql = "SELECT * FROM rekrutmen_lowongan 
        WHERE status='Aktif' 
        AND tanggal_mulai <= '$tanggal_sekarang'";

// SINKRONISASI TOTAL: Memaksa query menyaring teks secara Case-Insensitive menggunakan LOWER()
if ($cari_posisi !== '') {
    $search_db = mysqli_real_escape_string($koneksi, strtolower($cari_posisi));
    $sql .= " AND (LOWER(judul_lowongan) LIKE '%$search_db%' OR LOWER(deskripsi) LIKE '%$search_db%')";
}

if (!empty($departemen)) {
    $dep_db = mysqli_real_escape_string($koneksi, $departemen);
    $sql .= " AND unit = '$dep_db'";
}

if (!empty($tipe_kerja)) {
    $tipe_db = mysqli_real_escape_string($koneksi, $tipe_kerja);
    $sql .= " AND tipe = '$tipe_db'";
}

$sql .= " ORDER BY tanggal_selesai ASC";

// =========================================================================
// 6. EKSEKUSI QUERY FINAL KE DATABASE
// =========================================================================
$query_lowongan = mysqli_query($koneksi, $sql);

if (!$query_lowongan) {
    die("Gagal memuat lowongan: " . mysqli_error($koneksi));
}

// =========================================================================
// 7. AMBIL DATA PROFIL PELAMAR AKTIF UNTUK KEBUTUHAN MODAL KONFIRMASI
// =========================================================================
$data_pelamar = null;
$src_foto = "assets/images/default-avatar.png"; 

if ($pelamar_id > 0) {
    $sql_pelamar = "SELECT * FROM pelamar WHERE id = '$pelamar_id'";
    $query_pelamar = mysqli_query($koneksi, $sql_pelamar);
    
    if ($query_pelamar && mysqli_num_rows($query_pelamar) > 0) {
        $data_pelamar = mysqli_fetch_assoc($query_pelamar);
        $folder_foto = "uploads/"; 
        $nama_foto   = $data_pelamar['foto'] ?? '';

        if (!empty($nama_foto) && file_exists($folder_foto . $nama_foto)) {
            $src_foto = $folder_foto . $nama_foto;
        }
    }
}
?>

<!DOCTYPE html>
<html class="scroll-smooth" lang="id">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Karir - RSI Kendal | Bergabung Bersama Kami</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "outline": "#6d7a78",
                    "on-primary-container": "#004642",
                    "error-container": "#ffdad6",
                    "on-secondary-fixed-variant": "#444749",
                    "surface-dim": "#cbdbf5",
                    "surface-variant": "#d3e4fe",
                    "secondary-fixed": "#e0e3e5",
                    "on-secondary": "#ffffff",
                    "primary-container": "#4db9b2",
                    "primary-fixed": "#8bf4ec",
                    "outline-variant": "#bcc9c7",
                    "on-primary": "#ffffff",
                    "secondary-container": "#e0e3e5",
                    "primary-fixed-dim": "#6ed7d0",
                    "on-secondary-container": "#626567",
                    "on-tertiary-container": "#333e51",
                    "inverse-on-surface": "#eaf1ff",
                    "primary": "#006a65",
                    "on-tertiary": "#ffffff",
                    "on-surface-variant": "#3d4948",
                    "tertiary": "#545f73",
                    "on-secondary-fixed": "#191c1e",
                    "surface-container-low": "#eff4ff",
                    "surface-container-lowest": "#ffffff",
                    "on-error": "#ffffff",
                    "tertiary-container": "#9ea9c0",
                    "surface-bright": "#f8f9ff",
                    "on-error-container": "#93000a",
                    "tertiary-fixed": "#d8e3fb",
                    "on-tertiary-fixed": "#111c2d",
                    "surface": "#f8f9ff",
                    "on-tertiary-fixed-variant": "#3c475a",
                    "inverse-surface": "#213145",
                    "on-background": "#0b1c30",
                    "inverse-primary": "#6ed7d0",
                    "on-primary-fixed": "#00201e",
                    "surface-container-high": "#dce9ff",
                    "secondary-fixed-dim": "#c4c7c9",
                    "secondary": "#5c5f61",
                    "surface-tint": "#006a65",
                    "on-surface": "#0b1c30",
                    "on-primary-fixed-variant": "#00504c",
                    "tertiary-fixed-dim": "#bcc7de",
                    "background": "#f8f9ff",
                    "surface-container-highest": "#d3e4fe",
                    "surface-container": "#e5eeff",
                    "error": "#ba1a1a"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "section-gap": "52px",
                    "margin-desktop": "40px",
                    "stack-sm": "8px",
                    "margin-mobile": "16px",
                    "container-max": "1280px",
                    "stack-lg": "24px",
                    "stack-md": "12px",
                    "gutter": "16px"
            },
            "fontFamily": {
                    "label-sm": ["Inter"],
                    "display-lg-mobile": ["Inter"],
                    "body-md": ["Inter"],
                    "body-lg": ["Inter"],
                    "headline-md": ["Inter"],
                    "display-lg": ["Inter"],
                    "headline-sm": ["Inter"],
                    "label-md": ["Inter"]
            },
            "fontSize": {
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                    "display-lg-mobile": ["30px", {"lineHeight": "36px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "body-md": ["14px", {"lineHeight": "22px", "fontWeight": "400"}],
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "headline-md": ["26px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "display-lg": ["36px", {"lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "headline-sm": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "label-md": ["13px", {"lineHeight": "18px", "letterSpacing": "0.01em", "fontWeight": "500"}]
            }
          },
        },
      }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .premium-shadow {
            box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.05);
        }
        .premium-shadow-hover:hover {
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        .glass-header {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="bg-background text-on-background font-body-md selection:bg-primary-container selection:text-on-primary-container">
<!-- Top Navigation -->
<header class="fixed top-0 w-full z-50 bg-surface/90 backdrop-blur-md shadow-sm">
<nav class="max-w-container-max mx-auto px-4 md:px-margin-desktop h-16 flex justify-between items-center">
<div class="flex items-center gap-2"><img src="https://lh3.googleusercontent.com/aida-public/AB6AXuA-fKup3UmLpxKQXwXeoa6vDIdEGudQ6gscxPLvc3qdXVgANgK094MDXaRFcVEldNEw2J2zxMHBh3R81UWndLDGcalDvTE-UQeXJ8xVx5ccykGgMHTEOC6vsLxIxzSqkm4Jl1UsBU1ECAeezPmFuKkjYSv_hDJN9Ql2edK-qpzPi49jMlkt4Tc_w8VB9b620JfdgP7TSLkOaTK-ERApcDgCSu9ZzoqdYWyxjthoZOlW5PHKnS4JBwa9p60YV1PTXdtT7hXiyGpNuzw" alt="RSI Kendal Logo" class="h-8 w-auto">
<span class="text-headline-sm font-headline-sm font-bold text-primary">RSI Kendal</span>
</div>

<!-- Desktop Nav -->
<div class="flex items-center gap-4">
    <?php if (!empty($pelamar_nama)): ?>
        <!-- TAMPILAN JIKA USER SUDAH LOGIN -->
        <div class="flex items-center gap-2">
            
            <!-- 🔥 TOMBOL BARU: Lamaran Saya (Mengarah ke rekrutmen_lamaran.php) -->
            <a href="rekrutmen_lamaran.php" class="font-label-md text-label-md text-slate-700 border border-slate-200 bg-white px-3.5 py-1.5 rounded-lg hover:bg-slate-50 transition-all text-center inline-block font-semibold">
                <span class="material-symbols-outlined align-middle text-[18px] mr-1 text-slate-500">work_history</span>
                Lamaran Saya
            </a>

            <!-- Nama Pelamar (Klik untuk ke Profil) -->
            <a href="profil_pelamar.php" class="font-label-md text-label-md text-primary px-3.5 py-1.5 rounded-lg hover:bg-primary/5 transition-all text-center inline-block font-semibold">
                <span class="material-symbols-outlined align-middle text-[18px] mr-1">account_circle</span>
                <?php echo htmlspecialchars($pelamar_nama); ?>
            </a>
            
            <!-- Tombol Keluar / Logout -->
            <a href="logout_pelamar.php" class="font-label-md text-label-md bg-red-50 text-red-600 border border-red-200 px-3.5 py-1.5 rounded-lg hover:bg-red-100 transition-all text-center inline-block font-medium">
                Keluar
            </a>
        </div>
    <?php else: ?>
        <!-- TAMPILAN JIKA USER BELUM LOGIN -->
        <a href="login_pelamar.php" class="font-label-md text-label-md text-primary px-3.5 py-1.5 rounded-lg hover:bg-primary/5 transition-all text-center inline-block">
            Sign In
        </a>
        <a href="daftar_pelamar.php" class="font-label-md text-label-md bg-primary text-white px-4 py-1.5 rounded-lg hover:brightness-110 transition-all text-center inline-block">
            Sign Up
        </a>
    <?php endif; ?>
</div>

<!-- Mobile Menu Toggle -->
<button class="md:hidden text-primary">
<span class="material-symbols-outlined">menu</span>
</button>
</nav>
</header>
<main class="pt-16">
<!-- Hero Section -->
<section class="relative h-[76vh] min-h-[520px] flex items-center overflow-hidden transition-all duration-1000 opacity-100 translate-y-0">
<div class="absolute inset-0 z-0">
<div class="w-full h-full bg-cover bg-center relative" style="background-image: url('bg.png');"><div class="absolute inset-0 bg-primary/30 backdrop-blur-[1px]"></div></div>
<div class="absolute inset-0 bg-gradient-to-r from-on-primary-container/70 to-transparent"></div>
</div>
<div class="relative z-10 max-w-container-max mx-auto px-4 md:px-margin-desktop text-white">
<div class="max-w-2xl space-y-5 animate-in fade-in slide-in-from-bottom-8 duration-1000">
<h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg hover:translate-y-[-3px] transition-transform duration-500">Bergabunglah Bersama Rumah Sakit Islam Kendal
</h1>
<p class="font-body-lg text-body-lg text-white/90 max-w-xl hover:translate-y-[-2px] transition-transform duration-500">Mari tumbuh bersama kami dalam memberikan pelayanan kesehatan yang profesional, berkualitas, dan penuh kepedulian kepada masyarakat.
</p>
<div class="flex flex-col sm:flex-row gap-3 pt-3">
<a class="inline-flex items-center justify-center bg-primary-container text-on-primary-container font-label-md text-label-md px-6 py-3 rounded-lg hover:scale-105 hover:shadow-lg transition-all duration-300" href="#jobs">Lihat Lowongan
</a>

</div>
</div>
</div>
</section>
<!-- Why Join Us Section -->
<section class="py-14 bg-surface transition-all duration-1000 opacity-100 translate-y-0">
<div class="max-w-container-max mx-auto px-4 md:px-margin-desktop">
<div class="text-center mb-10">
<h2 class="font-headline-md text-headline-md text-on-background mb-4">Mengapa Berkarir Bersama Kami?</h2>
<p class="text-secondary max-w-2xl mx-auto">Kami menghargai setiap dedikasi dan memberikan ruang seluas-luasnya untuk aktualisasi diri para profesional kesehatan.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
<!-- Card 1 -->
<div class="bg-surface-container-lowest p-6 rounded-xl premium-shadow premium-shadow-hover transition-all duration-300">
<div class="w-10 h-10 bg-primary/10 text-primary rounded-lg flex items-center justify-center mb-4">
<span class="material-symbols-outlined">clinical_notes</span>
</div>
<h3 class="font-headline-sm text-headline-sm mb-2">Lingkungan Profesional</h3>
<p class="text-on-surface-variant text-body-md">Sistem kerja yang terorganisir dengan standar akreditasi rumah sakit tingkat paripurna.</p>
</div>
<!-- Card 2 -->
<div class="bg-surface-container-lowest p-6 rounded-xl premium-shadow premium-shadow-hover transition-all duration-300">
<div class="w-10 h-10 bg-primary/10 text-primary rounded-lg flex items-center justify-center mb-4">
<span class="material-symbols-outlined">trending_up</span>
</div>
<h3 class="font-headline-sm text-headline-sm mb-2">Jenjang Karir</h3>
<p class="text-on-surface-variant text-body-md">Kesempatan kenaikan jabatan yang transparan berdasarkan kinerja dan kompetensi profesional.</p>
</div>
<!-- Card 3 -->
<div class="bg-surface-container-lowest p-6 rounded-xl premium-shadow premium-shadow-hover transition-all duration-300">
<div class="w-10 h-10 bg-primary/10 text-primary rounded-lg flex items-center justify-center mb-4">
<span class="material-symbols-outlined">school</span>
</div>
<h3 class="font-headline-sm text-headline-sm mb-2">Pelatihan Berkelanjutan</h3>
<p class="text-on-surface-variant text-body-md">Program pengembangan skill rutin baik internal maupun eksternal untuk seluruh staf.</p>
</div>
<!-- Card 4 -->
<div class="bg-surface-container-lowest p-6 rounded-xl premium-shadow premium-shadow-hover transition-all duration-300">
<div class="w-10 h-10 bg-primary/10 text-primary rounded-lg flex items-center justify-center mb-4">
<span class="material-symbols-outlined">volunteer_activism</span>
</div>
<h3 class="font-headline-sm text-headline-sm mb-2">Kesejahteraan</h3>
<p class="text-on-surface-variant text-body-md">Paket remunerasi yang kompetitif, asuransi kesehatan, dan lingkungan kerja yang islami.</p>
</div>
</div>
</div>
</section>

<!-- ==================== AREA FILTER & DAFTAR LOWONGAN ==================== -->
<div class="max-w-container max-w-auto px-4 md:px-margin-desktop mb-12" style="max-width: 1200px; margin-left: auto">
    
    <!-- 1. KOMPONEN FILTER PENCARIAN -->
    <form method="GET" action="lowongan_pelamar.php" class="bg-surface-container p-4 md:p-6 rounded-xl mb-8" style="background-color: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0;">
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            
            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 13px;">Cari Posisi Lowongan</label>
                <div style="position: relative; display: flex; align-items: center;">
                    <!-- Atribut value wajib mencetak isi variabel $cari_posisi secara langsung -->
                    <input type="text" name="cari" value="<?php echo htmlspecialchars($cari_posisi); ?>" placeholder="Masukkan nama lowongan (Contoh: Perawat)..." style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-size: 14px;">
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="background-color: #00695c; color: white; border: none; padding: 11px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                    Filter Lowongan
                </button>
                <?php if ($cari_posisi !== ''): ?>
                    <a href="lowongan_pelamar.php" style="background-color: #e2e8f0; color: #1e293b; text-decoration: none; padding: 11px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; text-align: center; display: inline-block; line-height: 1.2;">
                        Lihat Semua
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </form>

    <!-- 2. GRID LISTING KARTU LOWONGAN (FIX AKURASI TARGET JUDUL ONLY) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
    <?php 
        $tanggal_sekarang = date('Y-m-d');
        $jumlah_kartu_tampil = 0; 

        // AMBIL INPUT FILTER LANGSUNG DARI URL AGAR ANTI-GAGAL
        $kata_kunci_url = isset($_GET['cari']) ? trim($_GET['cari']) : '';

        // Eksekusi ulang query murni untuk memastikan data selalu segar dan ada
        $query_ekstra = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan WHERE status='Aktif' AND tanggal_mulai <= '$tanggal_sekarang' ORDER BY tanggal_selesai ASC");

        if ($query_ekstra && mysqli_num_rows($query_ekstra) > 0) {
            while ($row_lowongan = mysqli_fetch_assoc($query_ekstra)): 
                
                // 🔥 KUNCI UTAMA: Hanya periksa kecocokan pada JUDUL LOWONGAN agar deskripsi/jabatan tidak mengacaukan filter
                if ($kata_kunci_url !== '') {
                    $judul_db     = mb_strtolower($row_lowongan['judul_lowongan'] ?? '', 'UTF-8');
                    $keyword_user = mb_strtolower($kata_kunci_url, 'UTF-8');

                    // FIX: Jika keyword TIDAK ditemukan di dalam judul lowongan, langsung buang/lewati kartu ini!
                    if (strpos($judul_db, $keyword_user) === false) {
                        continue;
                    }
                }

                $jumlah_kartu_tampil++; 
                
                $tanggal_selesai_db = $row_lowongan['tanggal_selesai'];
                $deadline = date('d M Y', strtotime($tanggal_selesai_db));
                
                if ($tanggal_sekarang > $tanggal_selesai_db) {
                    $badge_text = "Ditutup";
                    $is_expired = true;
                } else {
                    $badge_text = "Aktif";
                    $is_expired = false;
                }
        ?>
                <!-- Job Card Dinamis -->
                <div class="bg-white border border-outline-variant/30 p-5 rounded-xl" style="background: #fff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between; min-height: 280px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div>
                        <div class="flex justify-between items-start mb-3" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <span class="px-3 py-1 text-label-sm font-label-sm rounded-full" style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; background-color: #e8f5e9; color: #2e7d32;">
                                <?php echo $badge_text; ?>
                            </span>
                            <span style="color: #64748b; font-size: 12px;">Deadline: <?php echo $deadline; ?></span>
                        </div>
                        
                        <h4 style="font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($row_lowongan['judul_lowongan'] ?? 'Lowongan Kerja'); ?>
                        </h4>

                        <div style="display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 14px; margin-bottom: 12px;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">medical_services</span>
                            <?php echo htmlspecialchars($row_lowongan['unit'] ?? 'Lowongan Tersedia'); ?>
                        </div>
                        
                        <div style="color: #475569; font-size: 14px; line-height: 1.5; margin-bottom: 20px;">
                            <p class="line-clamp-3">
                                <?php echo htmlspecialchars($row_lowongan['deskripsi'] ?? 'Silakan klik detail untuk melihat kualifikasi lengkap.'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: auto;">
                        <?php if ($is_expired) : ?>
                            <div style="width: 100%; text-align: center; color: #dc2626; padding: 10px; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; font-style: italic; font-size: 14px;">
                                Lowongan telah berakhir
                            </div>
                        <?php else : ?>
                            <button type="button" onclick="bukaDetail(<?php echo $row_lowongan['id']; ?>)" style="flex: 1; padding: 10px; border: 1px solid #0d6efd; color: #0d6efd; background: transparent; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                Lihat Detail
                            </button>
                            
                            <?php 
                            $id_kerjaan = $row_lowongan['id'];
                            $sudah_lamar = false;

                            if (!empty($pelamar_id)) {
                                $cek_tombol = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE lowongan_id = '$id_kerjaan' AND pelamar_id = '$pelamar_id'");
                                if ($cek_tombol && mysqli_num_rows($cek_tombol) > 0) {
                                    $sudah_lamar = true;
                                }
                            }
                            ?>

                            <?php if ($sudah_lamar) : ?>
                                <button type="button" disabled style="flex: 1; padding: 10px; background: #94a3b8; color: white; border: none; border-radius: 8px; cursor: not-allowed; font-weight: 600; font-size: 14px;">
                                    ✓ Sudah Dilamar
                                </button>
                            <?php else : ?>
                                <button type="button" onclick="prosesLamar(<?php echo $row_lowongan['id']; ?>)" style="flex: 1; padding: 10px; background: #00695c; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                    Lamar
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
        <?php 
            endwhile;
        }

        // Tampilan pesan kosong jika tidak ada kartu yang lolos filter
        if ($jumlah_kartu_tampil === 0) { 
        ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1; width: 100%;">
                <span class="material-symbols-outlined" style="color: #64748b; font-size: 48px; display: block; margin-bottom: 10px;">search_off</span>
                <p style="color: #64748b; font-style: italic; font-size: 15px;">
                    Maaf, posisi lowongan "<?php echo htmlspecialchars($kata_kunci_url); ?>" tidak ditemukan.
                </p>
            </div>
        <?php 
        } 
        ?>
    </div>

<!-- Recruitment Process Section -->
<section class="py-14 bg-surface-container-low overflow-hidden transition-all duration-1000 opacity-100 translate-y-0">
<div class="max-w-container-max mx-auto px-4 md:px-margin-desktop">
<div class="text-center mb-10">
<h2 class="font-headline-md text-headline-md text-on-background mb-4">Tahapan Seleksi</h2>
<p class="text-secondary max-w-2xl mx-auto">Kami memastikan proses rekrutmen yang objektif dan transparan untuk menemukan talenta terbaik.</p>
</div>
<div class="relative">
<!-- Progress Line (Desktop) -->
<div class="hidden lg:block absolute top-10 left-0 w-full h-[2px] bg-outline-variant"></div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-5 relative">
<!-- Step 1 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">description</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">Kirim Lamaran</h5>
<p class="text-on-surface-variant text-label-sm">Unggah berkas melalui portal karir kami.</p>
</div>
<!-- Step 2 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">search_check</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">Seleksi Admin</h5>
<p class="text-on-surface-variant text-label-sm">Review berkas oleh tim HRD.</p>
</div>
<!-- Step 3 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">person</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">Interview HR</h5>
<p class="text-on-surface-variant text-label-sm">Perkenalan dan verifikasi awal.</p>
</div>
<!-- Step 4 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">groups</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">Interview User</h5>
<p class="text-on-surface-variant text-label-sm">Diskusi teknis dengan kepala departemen.</p>
</div>
<!-- Step 5 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">psychology</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">Psikotes</h5>
<p class="text-on-surface-variant text-label-sm">Uji kepribadian dan potensi akademik.</p>
</div>
<!-- Step 6 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">health_and_safety</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">MCU</h5>
<p class="text-on-surface-variant text-label-sm">Pemeriksaan kesehatan menyeluruh.</p>
</div>
<!-- Step 7 -->
<div class="flex flex-col items-center text-center group">
<div class="w-16 h-16 bg-white rounded-full flex items-center justify-center border-2 border-primary-container z-10 premium-shadow mb-4 group-hover:bg-primary transition-all duration-300">
<span class="material-symbols-outlined text-primary text-[20px] group-hover:text-white transition-colors">handshake</span>
</div>
<h5 class="font-label-md text-label-md text-on-surface mb-2">Penawaran Kerja</h5>
<h6 class="text-on-surface-variant text-label-sm">Bergabung menjadi keluarga besar.</h6>
</div>
</div>
</div>
</div>
</section>
<!-- FAQ Section -->
<section class="py-14 transition-all duration-1000 opacity-100 translate-y-0">
<div class="max-w-3xl mx-auto px-4">
<div class="text-center mb-8">
<h2 class="font-headline-md text-headline-md text-on-background mb-4">Pertanyaan Populer</h2>
</div>
<div class="space-y-3">
<details class="group bg-surface-container-low rounded-xl premium-shadow overflow-hidden" open="">
<summary class="flex items-center justify-between p-4 cursor-pointer list-none font-headline-sm text-[16px]">Bagaimana cara melamar pekerjaan di RSI Kendal?
<span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-4 pt-0 text-on-surface-variant">Pilih posisi yang sesuai di portal karir ini, lalu klik tombol "Lamar Sekarang". Anda akan diminta untuk mengunggah CV dan dokumen pendukung lainnya.</div>
</details>
<details class="group bg-surface-container-low rounded-xl premium-shadow overflow-hidden">
<summary class="flex items-center justify-between p-4 cursor-pointer list-none font-headline-sm text-[16px]">Bolehkah saya melamar lebih dari satu posisi?
<span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-4 pt-0 text-on-surface-variant">Kami menyarankan untuk melamar satu posisi yang paling sesuai dengan kualifikasi utama Anda agar proses seleksi lebih efektif.
</div>
</details>
<details class="group bg-surface-container-low rounded-xl premium-shadow overflow-hidden">
<summary class="flex items-center justify-between p-4 cursor-pointer list-none font-headline-sm text-[16px]">Berapa lama proses seleksi berlangsung?
<span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-4 pt-0 text-on-surface-variant">Rata-rata proses seleksi membutuhkan waktu 2-4 minggu sejak lamaran diterima, tergantung pada kebutuhan departemen.
</div>
</details>
</div>
</div>
</section>

<!-- Final CTA Section -->
<section class="py-14 px-4 transition-all duration-1000 opacity-100 translate-y-0">
    <div class="max-w-container-max mx-auto bg-primary rounded-3xl p-8 md:p-12 text-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-48 h-48 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-primary-fixed/20 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
        <div class="relative z-10 max-w-2xl mx-auto">
            <h2 class="font-display-lg-mobile md:font-headline-md text-white mb-4">Siap Menjadi Bagian dari Rumah Sakit Islam Kendal?</h2>
            <p class="text-white/80 font-body-lg text-body-lg mb-6">Jadilah garda terdepan dalam pelayanan kesehatan berkualitas bagi umat.</p>
            
            <!-- PERBAIKAN: Mengubah <button> menjadi <a> dengan href="#jobs" -->
            <a href="#jobs" class="inline-block bg-white text-primary font-bold px-8 py-3 rounded-xl hover:scale-105 transition-all duration-300 shadow-lg text-center">
                Lihat Semua Lowongan
            </a>
        </div>
    </div>
</section>
</main>

<!-- Footer -->
<footer class="bg-surface-container-highest dark:bg-inverse-surface border-t border-outline-variant mt-section-gap">
<div class="max-w-container-max mx-auto px-4 md:px-margin-desktop py-8">
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
<div class="col-span-1 md:col-span-1">
<span class="text-headline-sm font-headline-sm font-bold text-primary mb-4 block">RSI Kendal</span>
<p class="text-on-surface-variant text-body-md mb-4">Rumah Sakit Islam Kendal - Profesional, Berkualitas, Penuh Kepedulian.</p>
<div class="flex gap-3">
<a class="w-10 h-10 bg-white/10 flex items-center justify-center rounded-lg hover:bg-primary hover:text-white transition-all text-on-surface" href="#">
<span class="material-symbols-outlined">public</span>
</a>
<a class="w-10 h-10 bg-white/10 flex items-center justify-center rounded-lg hover:bg-primary hover:text-white transition-all text-on-surface" href="#">
<span class="material-symbols-outlined">alternate_email</span>
</a>
</div>
</div>
<div>
<h4 class="font-bold mb-4 text-on-surface">Navigasi Cepat</h4>
<ul class="space-y-3">
<li class=""><a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Tentang Kami</a></li>
<li class=""><a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Lowongan Kerja</a></li>
<li class=""><a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Kontak</a></li>
</ul>
</div>
<div>
<h4 class="font-bold mb-4 text-on-surface">Bantuan</h4>
<ul class="space-y-3">
<li class=""><a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Kebijakan Privasi</a></li>
<li class=""><a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Syarat &amp; Ketentuan</a></li>
<li class=""><a class="text-on-surface-variant hover:text-primary transition-colors" href="#">FAQ</a></li>
</ul>
</div>
<div>
<h4 class="font-bold mb-4 text-on-surface">Alamat</h4>
<p class="text-on-surface-variant text-body-md">
                        Jl. Raya Soekarno-Hatta No. 123,<br>
                        Kendal, Jawa Tengah 51313<br><br>
<span class="font-bold">Telepon:</span> (0294) 123456<br>
<span class="font-bold">Email:</span> sdm@rsikendal.com
                    </p>
</div>
</div>
<div class="border-t border-outline-variant pt-6 flex flex-col md:flex-row justify-between items-center gap-3 text-on-surface-variant text-label-md">
<p class="">© 2024 Rumah Sakit Islam Kendal. Seluruh hak cipta dilindungi.</p>
<div class="flex gap-4">
<a class="hover:text-primary" href="#">Facebook</a>
<a class="hover:text-primary" href="#">Instagram</a>
<a class="hover:text-primary" href="#">LinkedIn</a>
</div>
</div>
</div>
</footer>

    <!-- Modal Pop-up Rangkuman Profil Pelamar -->
    <div id="modalKonfirmasiLamar" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 mx-4 relative transform scale-95 transition-transform duration-300 shadow-2xl flex flex-col max-h-[85vh]">
            <button onclick="tutupKonfirmasi()" class="absolute right-4 top-4 text-outline hover:text-primary" type="button">
                <span class="material-symbols-outlined">close</span>
            </button>
            
            <h3 class="font-headline-md text-headline-md text-on-background mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[24px]">assignment_turned_in</span>
                Konfirmasi Data Profil Anda
            </h3>
            <p class="text-label-sm text-outline mb-4">Pastikan seluruh data di bawah ini sudah benar sebelum mengirim lamaran.</p>
            
            <!-- Area Tampilan Seluruh Data Profil -->
            <div id="isiProfilPelamar" class="space-y-3 text-body-md text-on-surface-variant overflow-y-auto pr-2 border-y border-outline-variant/30 py-4 my-2">
                <!-- Data akan disuntikkan secara dinamis oleh JavaScript -->
            </div>
            
            <!-- Form Final Submit Kirim ke Database -->
            <form action="" method="POST" class="mt-4 flex gap-3">
                <input type="hidden" name="lowongan_id" id="finalLowonganId" value="">
                <button type="button" onclick="tutupKonfirmasi()" class="flex-1 py-2.5 border border-outline text-outline rounded-xl font-label-md hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" name="kirim_lamaran_final" class="flex-1 py-2.5 bg-primary text-white rounded-xl font-label-md hover:brightness-110 shadow-sm transition-all">Kirim Lamaran Final</button>
            </form>
        </div>
    </div>

<!-- ======================================================== -->
<!-- TEMPATKAN HTML MODAL POP-UP DI SINI (DI ATAS SCRIPT)     -->
<!-- ======================================================== -->
<div id="modalDetail" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl max-w-4xl w-full p-6 mx-4 relative transform scale-95 transition-transform duration-300 shadow-2xl">
        
        <!-- Tombol Close -->
        <button onclick="tutupDetail()" class="absolute right-4 top-4 text-outline hover:text-primary" type="button">
            <span class="material-symbols-outlined">close</span>
        </button>

        <!-- Konten Detail Lowongan -->
        <div class="space-y-4">
            <span class="px-3 py-1 bg-primary/10 text-primary text-label-sm font-label-sm rounded-full">Aktif</span>
            <h3 id="modalJudul" class="font-headline-md text-headline-md text-on-background mt-2">Nama Lowongan</h3>
            
            <div class="border-t border-outline-variant/30 my-2"></div>
            
            <!-- Ditambahkan max-h agar muat menampung deskripsi, kualifikasi, dan persyaratan saat di-scroll -->
            <div class="space-y-4 text-on-surface-variant text-body-md overflow-y-auto max-h-[400px] pr-2">
                <!-- Deskripsi -->
                <div>
                    <p class="font-semibold text-primary mb-1">Deskripsi :</p>
                    <p id="modalDeskripsi" class="whitespace-pre-line text-justify text-on-surface-variant"></p>
                </div>

                <!-- BARU: Kualifikasi -->
                <div>
                    <p class="font-semibold text-primary mb-1">Kualifikasi :</p>
                    <p id="modalKualifikasi" class="whitespace-pre-line text-justify text-on-surface-variant"></p>
                </div>

                <!-- BARU: Persyaratan -->
                <div>
                    <p class="font-semibold text-primary mb-1">Persyaratan Dokumen :</p>
                    <p id="modalPersyaratan" class="whitespace-pre-line text-justify text-on-surface-variant"></p>
                </div>

                <div class="bg-surface-container-low p-3 rounded-xl flex justify-between text-label-md mt-2">
                    <span>Jumlah Kebutuhan: <strong id="modalKebutuhan">0</strong> orang</span>
                </div>
            </div>
            
            <div class="border-t border-outline-variant/30 my-2"></div>
            <p class="text-label-sm text-outline">Batas Pendaftaran: <span id="modalDeadline" class="font-bold">-</span></p>
        </div>
    </div>
</div>

<script>
    // 1. FUNGSI ANCHOR SCROLL HALUS
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // 2. FUNGSI FADE-IN SCROLL ANIMASI (DIPERBAIKI)
    const observerOptions = { threshold: 0.1 };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('opacity-100', 'translate-y-0');
                entry.target.classList.remove('opacity-0', 'translate-y-8');
            }
        });
    }, observerOptions);

    // PERBAIKAN UTAMA: Mengecualikan kedua modal (modalDetail & modalKonfirmasiLamar) agar tidak disembunyikan oleh animasi scroll
    document.querySelectorAll('section').forEach(section => {
        if (section.id !== 'modalDetail' && section.id !== 'modalKonfirmasiLamar' && !section.contains(document.getElementById('modalDetail')) && !section.contains(document.getElementById('modalKonfirmasiLamar'))) {
            section.classList.add('transition-all', 'duration-1000', 'opacity-0', 'translate-y-8');
            observer.observe(section);
        }
    });

    // 3. FUNGSI AJAX FETCH & TAMPILKAN MODAL POP-UP DETAIL LOWONGAN
    function bukaDetail(id) {
        const modal = document.getElementById('modalDetail');
        const modalContent = modal.querySelector('.transform');
        
        fetch('get_detail_lowongan.php?id=' + id)
            .then(response => {
                if (!response.ok) throw new Error('Respon jaringan bermasalah');
                return response.json();
            })
            .then(data => {
                if (data) {
                    document.getElementById('modalJudul').innerText = data.nama_lowongan || data.judul_lowongan || 'Lowongan';
                    document.getElementById('modalDeskripsi').innerText = data.deskripsi || '-';
                    
                    // --- TAMBAHKAN DUA BARIS BARU INI ---
                    document.getElementById('modalKualifikasi').innerText = data.kualifikasi || '-';
                    document.getElementById('modalPersyaratan').innerText = data.persyaratan || '-';
                    // ------------------------------------
                    
                    document.getElementById('modalKebutuhan').innerText = data.jumlah_kebutuhan || '0';
                    document.getElementById('modalDeadline').innerText = data.tanggal_selesai || '-';
                    
                    modal.style.display = "flex"; 
                    modal.classList.remove('hidden');
                    
                    setTimeout(() => {
                        modal.classList.remove('opacity-0');
                        modalContent.classList.remove('scale-95');
                    }, 20);
                }
            })
            .catch(err => {
                console.error('Gagal mengambil data detail:', err);
                alert('Gagal memuat detail lowongan. Pastikan file get_detail_lowongan.php sudah benar.');
            });
    }

    // 4. FUNGSI TUTUP MODAL DETAIL LOWONGAN
    function tutupDetail() {
        const modal = document.getElementById('modalDetail');
        const modalContent = modal.querySelector('.transform');
        
        modal.classList.add('opacity-0');
        modalContent.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.style.display = "none";
        }, 300);
    }

    // 5. FUNGSI LOGIKA VALIDASI VALIDASI VALIDASI BERTINGKAT TOMBOL LAMAR
    function prosesLamar(lowonganId) {
        if (!lowonganId) {
            alert('Gagal membaca ID Lowongan Kerja.');
            return;
        }

        fetch('cek_status_pelamar.php')
            .then(response => {
                if (!response.ok) throw new Error('File cek_status_pelamar.php tidak ditemukan');
                return response.json();
            })
            .then(res => {
                // VALIDASI 1: Belum Login
                if (res.status === 'belum_login') {
                    alert('Silakan masuk ke akun karir Anda terlebih dahulu untuk melamar pekerjaan ini.');
                    window.location.href = 'login_pelamar.php';
                } 
                // VALIDASI 2: Belum melengkapi biodata profil
                else if (res.status === 'belum_lengkap') {
                    alert('Data profil Anda belum lengkap. Silakan lengkapi biodata Anda terlebih dahulu di halaman profil.');
                    window.location.href = 'profil_pelamar.php';
                } 
                // VALIDASI 3: Sukses, Tampilkan Pop-up Seluruh Biodata Pelamar
                else if (res.status === 'siap_lamar') {
                    const modal = document.getElementById('modalKonfirmasiLamar');
                    const modalContent = modal.querySelector('.transform');
                    const areaIsi = document.getElementById('isiProfilPelamar');
                    
                    document.getElementById('finalLowonganId').value = lowonganId;
                    
                    areaIsi.innerHTML = '';
                    const data = res.data;
                    
                    for (const [key, value] of Object.entries(data)) {
                        const namaKolom = key.replace(/_/g, ' ').toUpperCase();
                        const nilaiKolom = value ? value : '<span class="text-red-500 italic">Belum diisi</span>';
                        
                        areaIsi.innerHTML += `
                            <div class="grid grid-cols-3 gap-2 border-b border-outline-variant/10 pb-2 text-left">
                                <span class="font-semibold text-on-background text-label-sm">${namaKolom}</span>
                                <span class="col-span-2 text-justify">${nilaiKolom}</span>
                            </div>
                        `;
                    }
                    
                    modal.style.display = "flex";
                    modal.classList.remove('hidden');
                    setTimeout(() => {
                        modal.classList.remove('opacity-0');
                        modalContent.classList.remove('scale-95');
                    }, 20);
                }
            })
            .catch(err => {
                console.error('Gagal memproses validasi pelamar:', err);
                alert('Sistem validasi bermasalah. Pastikan file cek_status_pelamar.php sudah dibuat dengan benar.');
            });
    }

    // 6. FUNGSI TUTUP MODAL KONFIRMASI LAMARAN
    function tutupKonfirmasi() {
        const modal = document.getElementById('modalKonfirmasiLamar');
        const modalContent = modal.querySelector('.transform');
        modal.classList.add('opacity-0');
        modalContent.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.style.display = "none";
        }, 300);
    }

    // 7. PENANGANAN KLIK AREA LUAR UNTUK MENUTUP MODAL SECARA OTOMATIS
    window.addEventListener('click', function(e) {
        const modalDetail = document.getElementById('modalDetail');
        const modalKonfirmasi = document.getElementById('modalKonfirmasiLamar');
        if (e.target === modalDetail) tutupDetail();
        if (e.target === modalKonfirmasi) tutupKonfirmasi();
    });
</script>

</body>
</html>
