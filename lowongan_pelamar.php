<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

$host="10.10.6.59";
$user_db="root_host";
$pass_db="password";
$nama_db="magang_rekrutmen_rs";

$koneksi=mysqli_connect($host,$user_db,$pass_db,$nama_db);
if(!$koneksi){ die("Koneksi gagal: ".mysqli_connect_error()); }

$pelamar_id   = $_SESSION['pelamar_id'] ?? 0;
$pelamar_nama = $_SESSION['pelamar_nama'] ?? '';

if(isset($_POST['kirim_lamaran_final'])){
    if(!$pelamar_id){
        echo "<script>alert('Silakan login terlebih dahulu');location='login_pelamar.php';</script>";
        exit;
    }

    $lowongan_id = (int)$_POST['lowongan_id'];

    $cek=mysqli_query($koneksi,"SELECT id FROM rekrutmen_lamaran
        WHERE lowongan_id='$lowongan_id' AND pelamar_id='$pelamar_id'");

    if(mysqli_num_rows($cek)==0){
        mysqli_query($koneksi,"INSERT INTO rekrutmen_lamaran
        (lowongan_id,pelamar_id,tanggal_lamaran,status,created_at)
        VALUES
        ('$lowongan_id','$pelamar_id',NOW(),'Lamaran Masuk',NOW())");
    }

    header("Location: lowongan_pelamar.php");
    exit;
}

$query_lowongan=mysqli_query($koneksi,"
SELECT *
FROM rekrutmen_lowongan
WHERE status='Aktif'
ORDER BY tanggal_selesai ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lowongan Pelamar</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100">

<div class="bg-white shadow p-4 flex justify-between">
    <h1 class="font-bold text-xl">Karir RSI Kendal</h1>

    <div>
        <?php if($pelamar_id): ?>
            <span><?= htmlspecialchars($pelamar_nama) ?></span>
            <a href="logout_pelamar.php" class="ml-3 text-red-600">Logout</a>
        <?php else: ?>
            <a href="login_pelamar.php">Login</a>
        <?php endif; ?>
    </div>
</div>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">Lowongan Tersedia</h2>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">

<?php while($row=mysqli_fetch_assoc($query_lowongan)): ?>

<?php
$id_lowongan=$row['id'];

$sudah_melamar=false;

if($pelamar_id){
    $q=mysqli_query($koneksi,"SELECT id FROM rekrutmen_lamaran
    WHERE lowongan_id='$id_lowongan'
    AND pelamar_id='$pelamar_id'
    LIMIT 1");

    $sudah_melamar=mysqli_num_rows($q)>0;
}
?>

<div class="bg-white rounded-xl shadow p-5">
    <h3 class="font-bold text-lg mb-2">
        <?= htmlspecialchars($row['judul_lowongan']) ?>
    </h3>

    <p class="text-sm text-slate-600 mb-3">
        <?= htmlspecialchars(substr(strip_tags($row['deskripsi']),0,180)) ?>
    </p>

    <p class="mb-2">
        Kuota: <?= (int)$row['jumlah_kebutuhan'] ?>
    </p>

    <p class="mb-4">
        Deadline:
        <?= !empty($row['tanggal_selesai']) ? date('d-m-Y',strtotime($row['tanggal_selesai'])) : '-' ?>
    </p>

    <?php if($sudah_melamar): ?>
        <button class="w-full bg-slate-400 text-white p-2 rounded">
            Sudah Dilamar
        </button>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="lowongan_id" value="<?= $id_lowongan ?>">
            <button name="kirim_lamaran_final"
                    class="w-full bg-emerald-600 text-white p-2 rounded">
                Lamar Sekarang
            </button>
        </form>
    <?php endif; ?>
</div>

<?php endwhile; ?>

    </div>
</div>

</body>
</html>
