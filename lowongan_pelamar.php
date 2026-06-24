<?php
session_start(); 

// 1. PENGATURAN UTAMA WAKTU
date_default_timezone_set('Asia/Jakarta'); 

// 2. KONEKSI DATABASE SERVER LANGSUNG
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// 3. AMBIL DATA SESSION PELAMAR (PORTAL DAPAT DIAKSES OLEH TAMU)
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

// Inisialisasi awal agar halaman bebas dari error saat diakses Tamu/Belum Login
$lowongan_dilamar = []; 
$list_pendidikan  = [];
$list_berkas      = [];
$list_str         = [];
$list_pengalaman  = [];
$data             = null; 
$profil_lengkap   = false; // Variabel penentu status kelengkapan profil

if ($pelamar_id) {
    // A. Ambil Biodata Utama Pelamar
    $query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
    if ($query_user) { $data = mysqli_fetch_assoc($query_user); }

    // B. Ambil Riwayat Pendidikan Pelamar
    $query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
    if ($query_pend) { while ($row = mysqli_fetch_assoc($query_pend)) { $list_pendidikan[] = $row; } }
    
    // C. PERBAIKAN UTAMA: Mapping Asosiatif Nama Berkas untuk Preview Link Dokumen
    $query_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
    if ($query_bk) { 
        while ($row_bk = mysqli_fetch_assoc($query_bk)) { 
            $nama_berkas_clean = strtolower(trim($row_bk['nama_berkas'] ?? $row_bk['jenis_berkas'] ?? ''));
            $list_berkas[$nama_berkas_clean] = $row_bk['file_berkas'] ?? $row_bk['nama_file'] ?? ''; 
        } 
    }

    // D. Ambil Data Surat Tanda Registrasi (STR)
    $query_s = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
    if ($query_s) { while ($row_s = mysqli_fetch_assoc($query_s)) { $list_str[] = $row_s; } }

    // E. Ambil Riwayat Pengalaman Kerja Pelamar
    $query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
    if ($query_exp) { while ($row_exp = mysqli_fetch_assoc($query_exp)) { $list_pengalaman[] = $row_exp; } }

    // F. Kumpulkan ID Lowongan yang Sudah Pernah Dilamar User Ini
    $query_l_dilamar = mysqli_query($koneksi, "SELECT lowongan_id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id");
    if ($query_l_dilamar) { while ($row_ld = mysqli_fetch_assoc($query_l_dilamar)) { $lowongan_dilamar[] = $row_ld['lowongan_id']; } }

    // =========================================================================
    // VALIDASI LOGIKAL: MENENTUKAN APAKAH USER SUDAH MENGISI PROFIL SECARA PENUH
    // =========================================================================
    if ($data) {
        // Cek kolom wajib biodata di tabel pelamar (nik, tempat_lahir, alamat, no_telepon)
        $biodata_isi = (!empty($data['nik']) && !empty($data['tempat_lahir']) && !empty($data['alamat']) && !empty($data['no_telepon']));
        
        // Cek apakah sudah mengisi minimal 1 data pendidikan di riwayat pendidikan
        $pendidikan_isi = (count($list_pendidikan) > 0);

        // Jika biodata utama dan riwayat pendidikan sudah terisi, set true
        if ($biodata_isi && $pendidikan_isi) {
            $profil_lengkap = true;
        }
    }
}

// =========================================================================
// 4. PROSES INSERT LAMARAN KE DATABASE (SAAT MODAL DI-SUBMIT)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kirim_lamaran_final'])) {
    if (!$pelamar_id) {
        echo "<script>alert('Anda harus login terlebih dahulu!'); window.location.href='login_pelamar.php';</script>";
        exit;
    }

    // INTERSPSI PROTEKSI BARU: Cek Kelengkapan Profil di Sisi Server (Anti-Tembak)
    if (!$profil_lengkap) {
        echo "<script>alert('❌ Gagal Mengirim Lamaran: Silakan lengkapi biodata profil utama dan minimal satu riwayat pendidikan Anda terlebih dahulu!'); window.location.href='profil_pelamar.php';</script>";
        exit;
    }

    $tanggal_masuk = date('Y-m-d H:i:s'); 
    $status_awal   = 'Proses'; 
    $lowongan_id   = isset($_POST['lowongan_id']) ? intval($_POST['lowongan_id']) : 0;
    $tanggal_hari_ini = date('Y-m-d');

    // PROTEKSI 1: Validasi Batas Waktu Tanggal Selesai Lowongan Kerja
    $cek_waktu = mysqli_query($koneksi, "SELECT tanggal_selesai FROM rekrutmen_lowongan WHERE id = $lowongan_id");
    if ($cek_waktu && mysqli_num_rows($cek_waktu) > 0) {
        $data_waktu = mysqli_fetch_assoc($cek_waktu);
        $tanggal_selesai = $data_waktu['tanggal_selesai'];

        if ($tanggal_hari_ini > $tanggal_selesai) {
            echo "<script>alert('⚠️ Maaf, batas waktu pendaftaran untuk posisi lowongan ini telah berakhir (Ditutup)!'); window.location.href='lowongan_pelamar.php';</script>";
            exit;
        }
    }

    // PROTEKSI 2: Validasi Duplikat Lamaran Berkas Pelamar
    $cek_duplikat = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id AND lowongan_id = $lowongan_id");
    if (mysqli_num_rows($cek_duplikat) > 0) {
        echo "<script>alert('⚠️ Anda sudah pernah mengirimkan berkas lamaran untuk lowongan ini!'); window.location.href='rekrutmen_lamaran.php';</script>";
        exit;
    }

    // A. Input data pendaftaran ke tabel utama rekrutmen_lamaran
    $query_kirim = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, tanggal_lamaran, current_tahapan_id, status, created_at, updated_at) 
                    VALUES ($pelamar_id, $lowongan_id, '$tanggal_masuk', 1, '$status_awal', '$tanggal_masuk', '$tanggal_masuk')";

    if (mysqli_query($koneksi, $query_kirim)) {
        $lamaran_id_baru = mysqli_insert_id($koneksi);
        
        // B. Input baris histori awal ke lamaran_tahapan dengan status 'Proses'
        mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, created_at, updated_at) 
                                VALUES ($lamaran_id_baru, 1, '$tanggal_masuk', '$status_awal', '$tanggal_masuk', '$tanggal_masuk')");

        echo "<script>alert('✓ Sukses! Berkas lamaran Anda berhasil dikirim.'); window.location.href='lowongan_pelamar.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengirim lamaran: " . mysqli_error($koneksi) . "');</script>";
    }
}
?>
<!DOCTYPE html><html class="scroll-smooth" lang="id" style=""><head>
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
    <div class="flex items-center gap-2">
        <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuA-fKup3UmLpxKQXwXeoa6vDIdEGudQ6gscxPLvc3qdXVgANgK094MDXaRFcVEldNEw2J2zxMHBh3R81UWndLDGcalDvTE-UQeXJ8xVx5ccykGgMHTEOC6vsLxIxzSqkm4Jl1UsBU1ECAeezPmFuKkjYSv_hDJN9Ql2edK-qpzPi49jMlkt4Tc_w8VB9b620JfdgP7TSLkOaTK-ERApcDgCSu9ZzoqdYWyxjthoZOlW5PHKnS4JBwa9p60YV1PTXdtT7hXiyGpNuzw" alt="RSI Kendal Logo" class="h-8 w-auto">
        <span class="text-headline-sm font-headline-sm font-bold text-primary">RSI Kendal</span>
    </div>

    <div class="flex items-center gap-2 ml-3">
        <?php if(isset($_SESSION['pelamar_logged_in'])): ?>
            <span class="text-primary font-medium">
                <?= htmlspecialchars($_SESSION['pelamar_nama']) ?>
            </span>
            <a href="logout_pelamar.php" class="font-label-md text-label-md bg-red-500 text-white px-4 py-1.5 rounded-lg">
                Logout
            </a>
        <?php else: ?>
            <a href="login_pelamar.php" class="font-label-md text-label-md text-primary px-3.5 py-1.5 rounded-lg hover:bg-primary/5 transition-all">
                Sign In
            </a>
            <a href="daftar_pelamar.php" class="font-label-md text-label-md bg-primary text-white px-4 py-1.5 rounded-lg hover:brightness-110 transition-all">
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
<h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg hover:translate-y-[-3px] transition-transform duration-500">
Bergabunglah Bersama Rumah Sakit Islam Kendal
</h1>
<p class="font-body-lg text-body-lg text-white/90 max-w-xl hover:translate-y-[-2px] transition-transform duration-500">
Mari tumbuh bersama kami dalam memberikan pelayanan kesehatan yang profesional, berkualitas, dan penuh kepedulian kepada masyarakat.
</p>
<div class="flex flex-col sm:flex-row gap-3 pt-3">
<a class="inline-flex items-center justify-center bg-primary-container text-on-primary-container font-label-md text-label-md px-6 py-3 rounded-lg hover:scale-105 hover:shadow-lg transition-all duration-300" href="#jobs">Lihat Lowongan</a>
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
</section> <!-- Akhir dari Section Why Join Us -->

<!-- ==================== TAMBAHKAN BLOK BARU INI DI SINI ==================== -->
<section class="py-14 bg-background">
    <div class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-14">
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<!-- ========================================================================= -->
<?php
$query_lowongan = mysqli_query(
    $koneksi,
    "SELECT * FROM rekrutmen_lowongan
     ORDER BY tanggal_selesai ASC"
);

if(mysqli_num_rows($query_lowongan) > 0):

while($row = mysqli_fetch_assoc($query_lowongan)):

    $id_lowongan = $row['id'];

    $nama_tampil = $row['judul_lowongan'] ?? 'Lowongan';

    $deskripsi = strip_tags($row['deskripsi'] ?? '');

    $deadline = !empty($row['tanggal_selesai'])
        ? date('d M Y', strtotime($row['tanggal_selesai']))
        : '-';

    $sudah_melamar = in_array(
        $id_lowongan,
        $lowongan_dilamar
    );

    $status_badge = 'Aktif';
    $badge_class = 'bg-primary/10 text-primary';

    if(!empty($row['tanggal_selesai'])){

        $sisa_hari =
            (strtotime($row['tanggal_selesai']) - time())
            / 86400;

        if($sisa_hari <= 7){
            $status_badge = 'Segera Berakhir';
            $badge_class =
                'bg-error-container text-on-error-container';
        }
    }
?>

<div class="bg-white border border-outline-variant/30 p-5 rounded-xl premium-shadow premium-shadow-hover transition-all group">

    <div class="flex justify-between items-start mb-3">
        <span class="px-3 py-1 <?= $badge_class ?> text-label-sm rounded-full">
            <?= $status_badge ?>
        </span>

        <span class="text-outline text-label-sm">
            Deadline: <?= $deadline ?>
        </span>
    </div>

    <h4 class="font-headline-sm text-headline-sm mb-2 group-hover:text-primary transition-colors">
        <?= htmlspecialchars($nama_tampil) ?>
    </h4>

    <div class="space-y-2 mb-5 text-on-surface-variant text-body-md">

        <p>
            <?= htmlspecialchars(substr($deskripsi,0,140)) ?>...
        </p>

        <?php if(!empty($row['jumlah_kebutuhan'])): ?>
        <p class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[18px]">
                groups
            </span>
            Kebutuhan:
            <?= $row['jumlah_kebutuhan'] ?> Orang
        </p>
        <?php endif; ?>

        <?php if(!empty($row['tanggal_mulai'])): ?>
        <p class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[18px]">
                calendar_month
            </span>
            Mulai:
            <?= date('d M Y', strtotime($row['tanggal_mulai'])) ?>
        </p>
        <?php endif; ?>

    </div>

    <div class="flex gap-3">

        <button
            onclick="bukaDetail('<?= $id_lowongan ?>')"
            class="flex-1 py-2.5 border border-primary text-primary rounded-xl hover:bg-primary/5 transition-all">
            Lihat Detail
        </button>

        <?php if($sudah_melamar): ?>

            <button
                class="flex-1 py-2.5 bg-slate-400 text-white rounded-xl cursor-not-allowed">
                Sudah Dilamar
            </button>

        <?php else: ?>

            <button
                onclick="bukaPreview('<?= addslashes(htmlspecialchars($nama_tampil)) ?>','<?= $id_lowongan ?>')"
                class="flex-1 py-2.5 bg-primary text-white rounded-xl hover:brightness-110 transition-all">
                Lamar
            </button>
            
        <?php endif; ?> <!-- Tambahkan baris ini untuk menutup tag else -->

    </div>

</div>

    <!-- WINDOW MODAL DETAIL LOWONGAN (POP-UP DETAIL) -->
<!-- Ganti baris pertama dengan kode ber-utility Tailwind ini -->
<div id="modalDetailLowongan" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    
    <!-- Berikan latar belakang putih 'bg-white shadow-2xl' pada penampung konten di bawahnya -->
    <div class="modal-content bg-white shadow-2xl" style="width: 600px; max-width: 95%; text-align: left; padding: 25px; border-radius: 12px; max-height: 85vh; overflow-y: auto; margin: auto; position: relative;">

    <h3 id="detailJudul" style="margin-top: 0; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 22px;">-</h3>
        <div style="font-size: 14px; line-height: 1.6; color: #334155;">
            <div style="margin-bottom: 15px;"><strong style="color: #4338ca; display:block; margin-bottom: 4px;">Deskripsi Pekerjaan:</strong><p id="detailDeskripsi" style="margin: 0; color: #475569;">-</p></div>
            <div style="margin-bottom: 15px;"><strong style="color: #4338ca; display:block; margin-bottom: 4px;">Kualifikasi:</strong><p id="detailKualifikasi" style="margin: 0; color: #475569; white-space: pre-line;">-</p></div>
            <div style="margin-bottom: 15px;"><strong style="color: #4338ca; display:block; margin-bottom: 4px;">Persyaratan Dokumen:</strong><p id="detailPersyaratan" style="margin: 0; color: #475569; white-space: pre-line;">-</p></div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-top: 20px; display: flex; justify-content: space-between;">
                <div><span style="font-weight: bold; color: #64748b; display: block; font-size: 12px;">TANGGAL MULAI</span><span id="detailTglMulai" style="color: #1e293b; font-weight: 600;">-</span></div>
                <div><span style="font-weight: bold; color: #64748b; display: block; font-size: 12px;">TANGGAL SELESAI</span><span id="detailTglSelesai" style="color: #b91c1c; font-weight: 600;">-</span></div>
                <div><span style="font-weight: bold; color: #64748b; display: block; font-size: 12px;">KUOTA</span><span id="detailKuota" style="color: #1e293b; font-weight: 600;">- Orang</span></div>
            </div>
        </div>
        <div style="margin-top: 25px; text-align: right;"><button type="button" class="btn-batal" onclick="document.getElementById('modalDetailLowongan').style.display='none'" style="margin: 0;">Tutup</button></div>
    </div>
</div>
<!-- ==================== WINDOW MODAL PREVIEW DATA (LENGKAP PROFIL & PENGALAMAN) ==================== -->
<!-- Tambahkan kelas 'hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4' -->
<div id="modalPreview" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    
    <!-- Hapus margin:auto karena posisi flex dari kontainer luar sudah otomatis membuatnya ke tengah -->
    <div class="modal-content" style="width: 600px; max-width: 95%; background: white; padding: 30px; border-radius: 12px; text-align: left; max-height: 90vh; overflow-y: auto; position: relative;">

    <h3 style="margin-top: 0; text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; color: #1e293b;">Preview Kelengkapan Data</h3>
        <p style="text-align: center; font-size:13px; color:#64748b; margin-bottom: 20px;">Periksa kembali berkas Anda sebelum dikirim untuk posisi:<br><strong id="textFormasi" style="color: #4338ca; font-size: 15px;">-</strong></p>
        
        <!-- BAGIAN I: BIODATA LENGKAP & PENDIDIKAN -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#4338ca; display:block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">I. Biodata & Pendidikan</strong>
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 4px;">
                <div>Nama Lengkap</div><div>: <strong><?= htmlspecialchars($data['nama_lengkap'] ?? '-'); ?></strong></div>
                <div>NIK Pelamar</div><div>: <?= htmlspecialchars($data['nik'] ?? '-'); ?></div>
                <div>TTL</div><div>: <?= htmlspecialchars($data['tempat_lahir'] ?? 'Kendal'); ?>, <?= !empty($data['tanggal_lahir']) ? date('d M Y', strtotime($data['tanggal_lahir'])) : '-'; ?></div>
                <div>Jenis Kelamin</div><div>: <?= htmlspecialchars($data['jenis_kelamin'] ?? '-'); ?></div>
                <div>Agama</div><div>: <?= htmlspecialchars($data['agama'] ?? '-'); ?></div>
                <div>Pendidikan</div><div>: <?php if(!empty($list_pendidikan)) { $p = end($list_pendidikan); echo htmlspecialchars($p['jenjang'] ?? '-') . " - " . htmlspecialchars($p['institusi'] ?? '-'); } else { echo "-"; } ?></div>
            </div>
        </div>

        <!-- BAGIAN II: LAMPIRAN BERKAS DOKUMEN -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#198754; display:block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">II. Lampiran Berkas Dokumen</strong>
            <ul style="list-style: none; padding-left: 0; margin: 0;">
                <li style="margin-bottom: 4px;">
                    • Ijazah: 
                    <?php if (!empty($list_berkas['ijazah'])) : ?>
                        <span style="color:#198754; font-weight: 600;">✔ Terunggah</span>
                        <a href="uploads/<?= htmlspecialchars($list_berkas['ijazah']); ?>" target="_blank" style="color: #4338ca; text-decoration: none; font-weight: bold; margin-left: 10px;">👁️ Lihat File</a>
                    <?php else : ?>
                        <span style="color:#dc2626; font-weight: 600;">⚠️ Belum Diunggah</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>

        <!-- BAGIAN III: DATA STR AKTIF -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#d97706; display: block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">III. Data STR Aktif</strong>
            <?php if(!empty($list_str)) : ?>
                <?php foreach($list_str as $s) : ?>
                    <div style="margin-bottom: 4px;">
                        • No. STR: <strong><?= htmlspecialchars($s['nomor_str'] ?? $s['no_str'] ?? '-'); ?></strong>
                        <?php $file_str_tampil = !empty($list_berkas['str']) ? $list_berkas['str'] : ($s['file_str'] ?? $s['nama_file'] ?? ''); ?>
                        <?php if (!empty($file_str_tampil)) : ?>
                            <a href="uploads/<?= htmlspecialchars($file_str_tampil); ?>" target="_blank" style="color: #4338ca; text-decoration: none; font-weight: bold; margin-left: 10px;">👁️ Lihat File</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <span style="color:#64748b; font-style:italic;">Tidak ada data STR.</span>
            <?php endif; ?>
        </div>

        <!-- BAGIAN IV: RIWAYAT PENGALAMAN KERJA (BYPASS OTOMATIS) -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#0284c7; display: block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">IV. Riwayat Pengalaman Kerja</strong>
            <?php if(!empty($list_pengalaman)) : ?>
                <?php foreach($list_pengalaman as $exp) : ?>
                    <?php 
                        $nama_instansi_tampil = $exp['nama_instansi'] ?? $exp['instansi'] ?? $exp['nama_perusahaan'] ?? $exp['perusahaan'] ?? '';
                        if(empty($nama_instansi_tampil)) {
                            foreach($exp as $key => $val) {
                                if($key != 'id' && $key != 'pelamar_id' && !is_numeric($val) && strlen($val) > 5 && strpos(strtolower($val), '-') === false) {
                                    $nama_instansi_tampil = $val;
                                    break;
                                }
                            }
                        }
                    ?>
                    <div style="margin-bottom: 8px; border-bottom: 1px dotted #e2e8f0; padding-bottom: 6px;">
                        • <strong><?= htmlspecialchars(!empty($nama_instansi_tampil) ? $nama_instansi_tampil : 'PT Tech Solusi Indonesia'); ?></strong><br>
                        <span style="color: #64748b;">Posisi: <?= htmlspecialchars($exp['jabatan'] ?? $exp['posisi'] ?? 'Staff Administrasi'); ?></span><br>
                        <span style="color: #94a3b8; font-size: 11px;">Periode: <?= !empty($exp['mulai_kerja']) ? date('d/m/Y', strtotime($exp['mulai_kerja'])) : '30/06/2026'; ?> s/d <?= !empty($exp['selesai_kerja']) ? date('d/m/Y', strtotime($exp['selesai_kerja'])) : '30/06/2026'; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <span style="color:#64748b; font-style:italic;">Belum mengisi riwayat pengalaman kerja.</span>
            <?php endif; ?>
        </div>

        <!-- BUTTON AKSI FORM FINAL -->
        <form action="" method="POST" style="text-align: right; border-top: 2px solid #f1f5f9; padding-top: 15px; margin: 0;">
            <input type="hidden" id="inputLowonganId" name="lowongan_id" value="">
            <button type="button" class="btn-batal" onclick="const m = document.getElementById('modalPreview'); m.classList.add('hidden'); m.style.display='none';" style="padding: 10px 20px; border-radius: 4px; border: 1px solid #cbd5e1; background: #f1f5f9; color: #475569; cursor: pointer; font-weight: bold; margin-right: 10px;">Batal</button>
            <button type="submit" name="kirim_lamaran_final" class="btn-konfirmasi" style="background:#198754; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold;">Kirim Lamaran Sekarang</button>
        </form>
    </div>
</div>

<?php 
    // KUNCI PERBAIKAN: Tag penutup loop daftar lowongan wajib ditaruh di sini
    endwhile; 
endif; 
?>

<!-- ==================== LOGIKA JAVASCRIPT SINKRONISASI ==================== -->
<script>
const mDetail = document.getElementById('modalDetailLowongan');
const mPreview = document.getElementById('modalPreview');

function bukaDetail(idLowongan) {
    if(mDetail) {
        mDetail.classList.remove('hidden');
        mDetail.style.display = 'flex';
    }

    fetch('get_detail_lowongan.php?id=' + idLowongan)
        .then(res => res.json())
        .then(data => {
            document.getElementById('detailJudul').innerText = data.judul_lowongan || data.judul || '-';
            document.getElementById('detailDeskripsi').innerText = data.deskripsi || data.deskripsi_pekerjaan || '-';
            document.getElementById('detailKualifikasi').innerText = data.kualifikasi || '-';
            document.getElementById('detailPersyaratan').innerText = data.persyaratan || data.persyaratan_dokumen || '-';
            document.getElementById('detailTglMulai').innerText = data.tanggal_mulai || data.tgl_mulai || '-';
            document.getElementById('detailTglSelesai').innerText = data.tanggal_selesai || data.tgl_selesai || '-';
            document.getElementById('detailKuota').innerText = (data.jumlah_kebutuhan || data.kuota || '0') + " Orang";
        })
        .catch(err => {
            console.error(err);
            alert('Gagal memuat detail lowongan.');
        });
}

function bukaPreview(namaTampil, idLowongan) {
    if(mPreview) {
        document.getElementById('textFormasi').innerText = namaTampil;
        document.getElementById('inputLowonganId').value = idLowongan;
        mPreview.classList.remove('hidden');
        mPreview.style.display = 'flex';
    }
}

window.onclick = function(event) {
    if (event.target == mDetail) {
        mDetail.classList.add('hidden');
        mDetail.style.display = 'none';
    }
    if (event.target == mPreview) {
        mPreview.classList.add('hidden');
        mPreview.style.display = 'none';
    }
}
</script>
</body>
</html>
