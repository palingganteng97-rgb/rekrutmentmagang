<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. KONEKSI DATABASE
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. QUERY RELASI UNTUK MENGGABUNGKAN DATA PELAMAR, LOWONGAN, DAN TAHAPANNYA
$query = "SELECT 
            p.nama_lengkap AS nama_pelamar, 
            l.judul_lowongan, 
            t.status AS status_lamaran,
            t.tanggal_mulai
          FROM lamaran_tahapan t
          JOIN rekrutmen_lamaran r ON t.lamaran_id = r.id
          JOIN pelamar p ON r.pelamar_id = p.id
          JOIN rekrutmen_lowongan l ON r.lowongan_id = l.id
          ORDER BY t.id DESC";

$result = mysqli_query($koneksi, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelamar Masuk</title>
    <!-- Menggunakan font bergaya modern agar serasi dengan admin Anda -->
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; padding: 40px; color: #334155; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h2 { font-size: 22px; color: #0f172a; margin-bottom: 5px; }
        p { font-size: 13px; color: #64748b; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background-color: #f8fafc; color: #475569; padding: 14px; font-weight: bold; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        td { padding: 14px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        tr:hover { background-color: #f8fafc; }
        .badge { background-color: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 50px; font-size: 12px; font-weight: bold; display: inline-block; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Daftar Pelamar Kerja Masuk</h2>
        <p>Berikut adalah baris data riil pelamar yang mendaftar melalui halaman depan website lowongan Anda.</p>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Pelamar</th>
                    <th>Posisi Lowongan</th>
                    <th>Tanggal Daftar</th>
                    <th>Status Tahap Awal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($row['nama_pelamar']); ?></td>
                        <td><?php echo htmlspecialchars($row['judul_lowongan']); ?></td>
                        <td><?php echo date('d M Y (H:i)', strtotime($row['tanggal_mulai'])); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($row['status_lamaran']); ?></span></td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align: center; color: #94a3b8; padding: 30px;'>Belum ada data pelamar yang masuk ke database.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>
