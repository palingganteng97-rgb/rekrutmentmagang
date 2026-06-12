<?php
// 1. PENGATURAN KONEKSI DATABASE (Sesuaikan IP dari HeidiSQL Anda)
$host     = "10.10.6.59"; 
$username = "root_host";       // Sesuaikan dengan username database Anda
$password = "password";           // Masukkan password database Anda jika ada
$database = "magang_rekrutmen_rs";

$koneksi = mysqli_connect($host, $username, $password, $database);

// Periksa apakah koneksi berhasil
if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// 2. MENGAMBIL DATA DARI TABEL USERS
$query  = "SELECT * FROM users ORDER BY id DESC";
$result = mysqli_query($koneksi, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Pengguna - Magang RS</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 30px; color: #333; }
        .table-card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto; }
        h2 { margin-bottom: 5px; color: #2c3e50; }
        p { color: #7f8c8d; font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; padding: 12px; border-bottom: 2px solid #eee; color: #555; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        tr:hover { background-color: #fcfcfc; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white; }
        .badge-aktif { background-color: #2ecc71; }
        .badge-non { background-color: #e74c3c; }
    </style>
</head>
<body>

<div class="table-card">
    <h2>DATA USERS</h2>
    <p>Daftar pengguna sistem rekrutmen rumah sakit</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Lengkap</th>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            // 3. LOOPING DATA DARI DATABASE
            while ($row = mysqli_fetch_assoc($result)) : 
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><strong><?= htmlspecialchars($row['nama']); ?></strong></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><?= htmlspecialchars($row['email']); ?></td>
                <td>
                    <?php if ($row['status'] == 'Aktif') : ?>
                        <span class="badge badge-aktif">Aktif</span>
                    <?php else : ?>
                        <span class="badge badge-non">Non-Aktif</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
