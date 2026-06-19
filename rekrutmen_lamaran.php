<?php 
session_start(); 

// TAMBAHKAN BARIS INI UNTUK MENGUNCI WAKTU INDONESIA BARAT (WIB)
date_default_timezone_set('Asia/Jakarta');

// 1. KONEKSI DATABASE
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
// TAMBAHKAN BARIS INI TEPAT DI BAWAH KONEKSI
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// 2. PROTEKSI HALAMAN (WAJIB LOGIN)
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

if (!$pelamar_id) {
    echo "<script>alert('Anda harus login terlebih dahulu!'); window.location.href='login_pelamar.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Lamaran Saya</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 40px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .header-page { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; }
        .btn-kembali { background: #4338ca; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: bold; transition: 0.2s; }
        .btn-kembali:hover { background: #3730a3; }
        
        /* Style Tabel */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: left; }
        th { background: #f1f5f9; color: #475569; padding: 12px; font-size: 14px; font-weight: bold; border-bottom: 2px solid #cbd5e1; }
        td { padding: 14px 12px; font-size: 14px; color: #1e293b; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        
        /* Style Badge Status Enum */
        .badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; text-align: center; }
        .badge-proses { background: #fef3c7; color: #d97706; } /* Kuning */
        .badge-diterima { background: #dcfce7; color: #15803d; } /* Hijau */
        .badge-ditolak { background: #fee2e2; color: #b91c1c; } /* Merah */
        
        .text-empty { text-align: center; color: #64748b; font-style: italic; padding: 30px 0; }
    </style>
</head>
<body>

    <div class="container">
        <div class="header-page">
            <h2 style="margin: 0; color: #1e293b;">Riwayat Lamaran Magang</h2>
            <a href="lowongan_pelamar.php" class="btn-kembali">← Kembali ke Karir</a>
        </div>
        
        <p style="color: #475569; margin-bottom: 20px;">Halo <strong><?= htmlspecialchars($pelamar_nama); ?></strong>, berikut adalah status berkas pendaftaran magang Anda secara real-time:</p>

        <table>
            <thead>
                <tr>
                    <th style="width: 60px; text-align: center;">No</th>
                    <th>Nama Formasi Lowongan</th>
                    <th>Tanggal Melamar</th>
                    <th style="width: 150px; text-align: center;">Status Seleksi</th>
                    <th style="width: 100px; text-align: center;">Aksi</th>
                </tr>
            </thead>

                        <tbody>
                <?php
                $no = 1;
                
                // PERBAIKAN: Memberikan alias l.created_at AS tanggal_kirim agar tidak tertimpa tabel lowongan
                $query_riwayat = "SELECT l.id AS lamaran_id, l.created_at AS tanggal_kirim, l.status, lw.judul_lowongan, lw.kode_lowongan 
                                  FROM rekrutmen_lamaran l
                                  JOIN rekrutmen_lowongan lw ON l.lowongan_id = lw.id
                                  WHERE l.pelamar_id = $pelamar_id 
                                  ORDER BY l.id DESC";
                
                $tampil_data = mysqli_connect_error() ? null : mysqli_query($koneksi, $query_riwayat);

                if ($tampil_data && mysqli_num_rows($tampil_data) > 0) {
                    while ($row = mysqli_fetch_assoc($tampil_data)) {
                        
                        // Menampilkan judul lowongan
                        $nama_lowongan_tampil = !empty($row['judul_lowongan']) ? $row['judul_lowongan'] : 'Lowongan Magang';
                        $kode_lowongan = !empty($row['kode_lowongan']) ? ' (' . $row['kode_lowongan'] . ')' : '';

                        // Penentuan warna badge status ENUM
                        $status = $row['status'];
                        $badge_class = 'badge-proses';
                        if ($status == 'Diterima' || $status == 'TERIMA') { 
                            $badge_class = 'badge-diterima'; 
                        } elseif ($status == 'Ditolak' || $status == 'TOLAK') { 
                            $badge_class = 'badge-ditolak'; 
                        }
                        
                        // PERBAIKAN: Membaca variabel alias 'tanggal_kirim' yang sudah kita buat di atas
                        $tanggal = !empty($row['tanggal_kirim']) ? date('d M Y - H:i', strtotime($row['tanggal_kirim'])) : 'Sedang diproses';
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold; color: #64748b;"><?= $no++; ?></td>
                            <td style="font-weight: 500; color: #4338ca;"><?= htmlspecialchars($nama_lowongan_tampil . $kode_lowongan); ?></td>
                            <td><?= $tanggal; ?> WIB</td>
                            <td style="text-align: center;">
                                <span class="badge <?= $badge_class; ?>"><?= htmlspecialchars($status); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <a href="detail_lamaran.php?id=<?= $row['lamaran_id']; ?>" style="background: #0284c7; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block;">👁 Detail</a>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-empty' style='text-align:center; padding:20px; color:#64748b; font-style:italic;'>Anda belum pernah mengirimkan lamaran magang apa pun saat ini.</td></tr>";
                }
                ?>
            </tbody>

        </table>
    </div>

</body>
</html>
