<?php
session_start();

$host     = "10.10.6.59";
$user_db  = "root_host";
$pass_db  = "password";
$nama_db  = "magang_rekrutmen_rs";

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$conn) {
    die("Koneksi database gagal : " . mysqli_connect_error());
}

function getTotal($conn, $sql){
    $q = mysqli_query($conn,$sql);
    $r = mysqli_fetch_assoc($q);
    return (int)($r['total'] ?? 0);
}

$stat_lowongan = getTotal($conn,"SELECT COUNT(*) total FROM rekrutmen_lowongan");
$stat_pelamar  = getTotal($conn,"SELECT COUNT(*) total FROM pelamar");
$stat_pool     = getTotal($conn,"SELECT COUNT(*) total FROM talent_pool");

$stat_baru = getTotal($conn,"
SELECT COUNT(*) total
FROM lamaran_tahapan
WHERE status IS NULL OR UPPER(TRIM(status))='PENDING'
");

$stat_proses = getTotal($conn,"
SELECT COUNT(*) total
FROM lamaran_tahapan
WHERE UPPER(TRIM(status))='PROSES'
");

$stat_lulus = getTotal($conn,"
SELECT COUNT(*) total
FROM lamaran_tahapan
WHERE UPPER(TRIM(status)) IN ('LULUS','DITERIMA','TERIMA')
");

$stat_tolak = getTotal($conn,"
SELECT COUNT(*) total
FROM lamaran_tahapan
WHERE UPPER(TRIM(status)) IN ('TOLAK','DITOLAK','TIDAK LULUS')
");

$jabatan_label=[];
$jabatan_data=[];

$q=mysqli_query($conn,"
SELECT mj.nama_jabatan,COUNT(rl.id) jumlah
FROM mst_jabatan mj
LEFT JOIN rekrutmen_lowongan rw ON rw.jabatan_id=mj.id
LEFT JOIN rekrutmen_lamaran rl ON rl.lowongan_id=rw.id
GROUP BY mj.id
ORDER BY jumlah DESC
");

while($r=mysqli_fetch_assoc($q)){
    $jabatan_label[]=$r['nama_jabatan'];
    $jabatan_data[]=(int)$r['jumlah'];
}

$unit_label = [];
$unit_data  = [];

$q_unit = mysqli_query($conn,"
SELECT mu.nama_unit, COUNT(rl.id) jumlah
FROM mst_unit mu
LEFT JOIN rekrutmen_lowongan rw ON rw.unit_id = mu.id
LEFT JOIN rekrutmen_lamaran rl ON rl.lowongan_id = rw.id
GROUP BY mu.id
ORDER BY jumlah DESC
");

while($r = mysqli_fetch_assoc($q_unit)){
    $unit_label[] = $r['nama_unit'];
    $unit_data[]  = (int)$r['jumlah'];
}

$bulan_label = [];
$bulan_data  = [];

$q_bulan = mysqli_query($conn,"
SELECT
YEAR(created_at) AS tahun,
MONTH(created_at) AS bulan_angka,
DATE_FORMAT(MIN(created_at),'%M %Y') AS bulan,
COUNT(*) AS jumlah
FROM rekrutmen_lamaran
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY tahun, bulan_angka
");

while($r=mysqli_fetch_assoc($q_bulan)){
    $bulan_label[] = $r['bulan'];
    $bulan_data[]  = (int)$r['jumlah'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Jabatan - Magang ID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }
        .support-card { background: #fff5f5; border: 1px solid #fee2e2; padding: 16px; border-radius: 20px; text-align: center; margin-top: 20px; }
        .support-card a { display: block; width: 100%; background: #dc2626; color: white; padding: 12px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); }
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 24px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #212529; }
        .content-header p { font-size: 14px; color: #6c757d; margin-top: 4px; }
        .control-bar { display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-top: 10px; }
        .search-box { display: flex; gap: 8px; flex: 1; max-width: 450px; }
        .input-search { width: 100%; padding: 10px 16px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; color: #495057; outline: none; background: #ffffff; }
        .btn-search { background: #3182ce; color: white; border: none; padding: 0 24px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .action-right { display: flex; gap: 10px; }
        .btn-add { background: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .table-wrapper { background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: #f8fafc; color: #212529; padding: 16px 12px; font-weight: 700; border-bottom: 2px solid #dee2e6; text-align: center; }
        td { padding: 16px 12px; color: #495057; border-bottom: 1px solid #dee2e6; vertical-align: middle; text-align: center; }
        .action-container { display: flex; gap: 6px; justify-content: center; }
        .btn-action { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: white; text-decoration: none; font-size: 14px; }
        .btn-edit { background: #00a896; }   
        .btn-delete { background: #e74c3c; } 
        .table-input { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; color: #1e293b; background: #ffffff; outline: none; }
    /* ================= DASHBOARD ================= */

.stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
    margin-top:10px;
}

.stat-card{
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:20px;
    box-shadow:0 4px 6px rgba(0,0,0,0.03);
}

.stat-title{
    font-size:12px;
    color:#94a3b8;
    font-weight:700;
    text-transform:uppercase;
    margin-bottom:8px;
}

.stat-number{
    font-size:28px;
    font-weight:800;
    color:#1e293b;
}

.charts-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-top:20px;
}

.chart-box{
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:20px;
    box-shadow:0 4px 6px rgba(0,0,0,0.03);
}

.chart-box h3{
    margin-bottom:15px;
    color:#1e293b;
    font-size:16px;
    font-weight:700;
}

.chart-box canvas{
    width:100% !important;
    height:320px !important;
}

@media(max-width:900px){
    .charts-grid{
        grid-template-columns:1fr;
    }
}
    </style>
</head>
<body>

    <div class="dashboard-container">
        <!-- SIDEBAR MENU KIRI -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item active">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item" style="text-decoration: none;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item" style="text-decoration: none;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item">Lamaran Tahapan</a>
                    <a href="talent_pool.php" class="menu-item">Talent Pool</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>
                </nav>
            </div>
            <div class="support-card">
                <a href="logout.php">Log Out</a>
            </div>
        </aside>

<main class="main-content">

    <div class="content-header">
        <h1>Dashboard Rekrutmen</h1>
        <p>Ringkasan aktivitas rekrutmen magang</p>
    </div>

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-title">Lowongan</div>
            <div class="stat-number"><?= $stat_lowongan ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Pelamar</div>
            <div class="stat-number"><?= $stat_pelamar ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Pending</div>
            <div class="stat-number"><?= $stat_baru ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Proses</div>
            <div class="stat-number"><?= $stat_proses ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Diterima</div>
            <div class="stat-number"><?= $stat_lulus ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Ditolak</div>
            <div class="stat-number"><?= $stat_tolak ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Talent Pool</div>
            <div class="stat-number"><?= $stat_pool ?></div>
        </div>

    </div>

<div class="charts-grid">

    <div class="chart-box">
        <h3>Pelamar per Jabatan</h3>
        <canvas id="chartJabatan"></canvas>
    </div>

    <div class="chart-box">
        <h3>Status Seleksi</h3>
        <canvas id="chartStatus"></canvas>
    </div>

    <div class="chart-box">
        <h3>Pelamar per Unit</h3>
        <canvas id="chartUnit"></canvas>
    </div>

    <div class="chart-box">
        <h3>Rekrutmen per Bulan</h3>
        <canvas id="chartBulan"></canvas>
    </div>

</div>

</main>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('chartJabatan'),{
    type:'bar',
    data:{
        labels: <?= json_encode($jabatan_label) ?>,
        datasets:[{
            label:'Jumlah Pelamar',
            data: <?= json_encode($jabatan_data) ?>,
            backgroundColor:'#4f46e5',
            borderRadius:8
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

new Chart(document.getElementById('chartStatus'),{
    type:'doughnut',
    data:{
        labels:['Pending','Proses','Diterima','Ditolak'],
        datasets:[{
            data:[
                <?= $stat_baru ?>,
                <?= $stat_proses ?>,
                <?= $stat_lulus ?>,
                <?= $stat_tolak ?>
            ],
            backgroundColor:[
                '#f59e0b',
                '#3b82f6',
                '#10b981',
                '#ef4444'
            ]
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

new Chart(document.getElementById('chartUnit'),{
    type:'bar',
    data:{
        labels: <?=json_encode($unit_label)?>,
        datasets:[{
            label:'Jumlah Pelamar',
            data: <?=json_encode($unit_data)?>,
            backgroundColor:'#10b981'
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

new Chart(document.getElementById('chartBulan'),{
    type:'line',
    data:{
        labels: <?=json_encode($bulan_label)?>,
        datasets:[{
            label:'Jumlah Pelamar',
            data: <?=json_encode($bulan_data)?>,
            borderColor:'#4f46e5',
            backgroundColor:'rgba(79,70,229,0.15)',
            fill:true,
            tension:0.3
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});
</script>
</body>
</html>