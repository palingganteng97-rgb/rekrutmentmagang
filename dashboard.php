<?php
// =========================================================================
// 1. BAGIAN DATA (Teks Terpisah yang Bisa Disalin / Diubah)
// =========================================================================

$hari_grafik = ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];

$aktivitas_periode = [
    'judul' => 'Aktivitas Periode',
    'waktu' => 'Juni 2026',
    'items' => [
        ['label' => 'Posisi Lowongan Dibuka', 'nilai' => 23],
        ['label' => 'Kandidat Di-interview', 'nilai' => 154],
        ['label' => 'Kampus Bermitra', 'nilai' => 10]
    ],
    'tombol' => 'Unduh Rekap Laporan'
];

$posisi_magang_terpopuler = [
    'judul' => 'Posisi Magang Terpopuler',
    'link_teks' => 'Lihat Semua',
    'daftar' => [
        [
            'posisi' => 'UI/UX Designer - Intern',
            'divisi' => 'Divisi Produk Digital',
            'pendaftar' => '14 Pendaftar hari ini',
            'tag' => 'Kreatif',
            'warna_badge' => 'purple'
        ],
        [
            'posisi' => 'Web Developer (React / Tailwind)',
            'divisi' => 'Divisi Engineering',
            'pendaftar' => '32 Pendaftar hari ini',
            'tag' => 'Teknis',
            'warna_badge' => 'blue'
        ]
    ]
];

$pelamar_masuk_terbaru = [
    'judul' => 'Pelamar Masuk Terbaru',
    'daftar' => [
        [
            'inisial' => 'AC',
            'nama' => 'Adhiatma Cruz',
            'kampus' => 'Univ. Dian Nuswantoro',
            'waktu' => '10m lalu',
            'warna_bg' => 'blue'
        ],
        [
            'inisial' => 'RK',
            'nama' => 'Rosalina K.',
            'kampus' => 'Universitas Diponegoro',
            'waktu' => '1j lalu',
            'warna_bg' => 'purple'
        ]
    ]
];

// =========================================================================
// 2. BAGIAN TAMPILAN UTAMA (HTML & TAILWIND CSS)
// =========================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Rekrutmen Magang</title>
    <!-- Tailwind CSS & FontAwesome Icons -->
    <script src="https://tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cloudflare.com">
    <style>
        /* Custom Glassmorphism UI Style matching your login page */
        .glass-panel {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-[#121424] via-[#1a1c38] to-[#13112b] text-slate-200 font-sans min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-[#0d0f1d]/80 border-r border-white/5 flex flex-col justify-between p-6">
        <div>
            <div class="flex items-center gap-3 mb-10 px-2">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/30">M</div>
                <span class="font-bold text-lg tracking-wider bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">MAGANG ID</span>
            </div>
            <nav class="space-y-2">
                <a href="#" class="flex items-center gap-4 px-4 py-3 rounded-xl bg-gradient-to-r from-blue-600/20 to-transparent border-l-4 border-blue-500 text-white font-medium transition-all">
                    <i class="fa-solid fa-chart-pie text-blue-400"></i> Dashboard
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 rounded-xl text-slate-400 hover:bg-white/5 hover:text-slate-200 transition-all">
                    <i class="fa-solid fa-briefcase"></i> Lowongan
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 rounded-xl text-slate-400 hover:bg-white/5 hover:text-slate-200 transition-all">
                    <i class="fa-solid fa-users"></i> Pelamar
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 rounded-xl text-slate-400 hover:bg-white/5 hover:text-slate-200 transition-all">
                    <i class="fa-solid fa-calendar-days"></i> Interview
                </a>
            </nav>
        </div>
        <div class="pt-4 border-t border-white/5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-600 border border-white/20 flex items-center justify-center text-sm font-semibold">RA</div>
            <div>
                <h4 class="text-sm font-semibold text-white">RestuAjiM</h4>
                <p class="text-xs text-slate-500">HR Admin</p>
            </div>
        </div>
    </aside>

    <!-- CONTENT AREA -->
    <main class="flex-1 p-8 overflow-y-auto max-w-[1600px] mx-auto w-full space-y-6">
        
        <!-- HEADER -->
        <header class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-wide">Dashboard</h1>
                <p class="text-xs text-slate-500 mt-1">Jumat, 12 Juni 2026</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" placeholder="Cari data..." class="bg-[#181a30] border border-white/5 rounded-full pl-11 pr-6 py-2 text-sm text-slate-200 focus:outline-none focus:border-blue-500/50 w-64 transition-all">
                </div>
                <button class="w-10 h-10 rounded-full bg-[#181a30] border border-white/5 flex items-center justify-center hover:bg-white/5 text-slate-300 transition-all relative">
                    <i class="fa-regular fa-bell"></i>
                    <span class="w-2 h-2 bg-blue-500 rounded-full absolute top-2 right-2.5"></span>
                </button>
            </div>
        </header>

        <!-- STATS CARDS -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass-panel p-6 rounded-2xl flex justify-between items-center">
                <div>
                    <p class="text-xs font-semibold text-slate-400 tracking-wider uppercase">Total Pendaftar</p>
                    <h3 class="text-3xl font-bold text-white mt-2">2.358</h3>
                </div>
                <div class="w-12 h-12 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400"><i class="fa-solid fa-users text-xl"></i></div>
            </div>
            <div class="glass-panel p-6 rounded-2xl flex justify-between items-center">
                <div>
                    <p class="text-xs font-semibold text-slate-400 tracking-wider uppercase">Lolos Berkas</p>
                    <h3 class="text-3xl font-bold text-white mt-2">1.568</h3>
                </div>
                <div class="w-12 h-12 rounded-full bg-cyan-500/10 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-file-circle-check text-xl"></i></div>
            </div>
            <div class="glass-panel p-6 rounded-2xl flex justify-between items-center">
                <div>
                    <p class="text-xs font-semibold text-slate-400 tracking-wider uppercase">Kuota Diterima</p>
                    <h3 class="text-3xl font-bold text-white mt-2">845</h3>
                </div>
                <div class="w-12 h-12 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400"><i class="fa-solid fa-user-check text-xl"></i></div>
            </div>
        </section>

        <!-- MAIN CHARTS & ACTIVITY ROW -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Bar Chart Component -->
            <div class="glass-panel p-6 rounded-2xl lg:col-span-2">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-white tracking-wide">Tren Grafik Aplikasi Aktif</h3>
                </div>
                <div class="h-64 flex items-end justify-between gap-2 pt-4 px-2 relative border-b border-white/5">
                    <div class="absolute left-0 w-full border-t border-white/5 top-1/4"></div>
                    <div class="absolute left-0 w-full border-t border-white/5 top-2/4"></div>
                    <div class="absolute left-0 w-full border-t border-white/5 top-3/4"></div>
                    
                    <?php 
                    $tinggi_simulasi =;
                    foreach ($hari_grafik as $index => $hari): 
                    ?>
                    <div class="w-full flex flex-col items-center gap-2 z-10">
                        <div class="w-3 bg-gradient-to-t from-blue-600 via-purple-500 to-orange-400 rounded-t-full shadow-lg" style="height: <?php echo $tinggi_simulasi[$index] * 4; ?>px;"></div>
                        <span class="text-[10px] text-slate-500"><?php echo $hari; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Activity Panel (Glow Purple-Orange Gradient) -->
            <div class="bg-gradient-to-b from-[#df5c32]/15 to-[#6a2574]/15 border border-[#df5c32]/10 p-6 rounded-2xl flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-white tracking-wide"><?php echo $aktivitas_periode['judul']; ?></h3>
                        <span class="text-[10px] text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full font-medium"><?php echo $aktivitas_periode['waktu']; ?></span>
                    </div>
