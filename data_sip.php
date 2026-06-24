<?php
session_start();

// =========================================================================
// 1. PENGATURAN KONEKSI DATABASE
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. QUERY JOIN TABEL PELAMAR DAN PELAMAR_SIP (UNTUK REKAPITULASI DATA)
// =========================================================================
$query_rekap = "
    SELECT 
        ps.id AS sip_id,
        p.nama_pelamar AS nama_pelamar,
        ps.nomor_sip,
        ps.tanggal_terbit,
        ps.tanggal_expired,
        ps.file_sip,
        DATEDIFF(ps.tanggal_expired, NOW()) AS sisa_hari
    FROM pelamar_sip ps
    INNER JOIN pelamar p ON ps.pelamar_id = p.id
    ORDER BY ps.created_at DESC
";

$result_rekap = mysqli_query($conn, $query_rekap);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap SIP Pelamar - Magang ID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f0f2f5; padding: 30px; color: #475569; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; background: #ffffff; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); padding: 35px; border: 1px solid #e2e8f0; }
        
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header-section h2 { font-size: 24px; font-weight: 800; color: #1e293b; }
        .header-section p { font-size: 14px; color: #64748b; margin-top: 2px; }
        
        .btn-add { background: #4f46e5; color: white; border: none; padding: 12px 20px; border-radius: 10px; font-size: 14px; font-weight: 700; text-decoration: none; transition: background 0.2s; }
        .btn-add:hover { background: #4338ca; }
        
        .table-wrapper { width: 100%; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        
        th { background: #f8fafc; color: #1e293b; padding: 18px 16px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 18px 16px; color: #334155; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:hover td { background-color: #f8fafc; }
        
        /* Indikator Status Badge */
        .badge { display: inline-block; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 8px; text-align: center; }
        .badge-aktif { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-expired { background-color: #fee2e2; color: #991b1b; }
        
        .btn-view { color: #4f46e5; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .btn-view:hover { text-decoration: underline; }
        .text-empty { text-align: center; padding: 40px; color: #94a3b8; font-weight: 600; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <div>
            <h2>Data Surat Izin Praktik (SIP)</h2>
            <p>Daftar riwayat verifikasi kelengkapan dokumen berkas medis pelamar rumah sakit.</p>
        </div>
        <a href="upload_sip.php" class="btn-add">+ Daftarkan SIP</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="text-align: center; width: 60px;">No</th>
                    <th>Nama Pelamar</th>
                    <th>Nomor SIP</th>
                    <th>Tanggal Terbit</th>
                    <th>Tanggal Kedaluwarsa</th>
                    <th style="text-align: center;">Masa Berlaku</th>
                    <th style="text-align: center; width: 130px;">Dokumen</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result_rekap && mysqli_num_rows($result_rekap) > 0): 
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result_rekap)):
                        
                        // Logika Otomatis Menghitung Klasifikasi Status Kedaluwarsa Dokumen
                        $sisa_hari = (int)$row['sisa_hari'];
                        if ($sisa_hari < 0) {
                            $status_badge = '<span class="badge badge-expired">Expired</span>';
                            $keterangan_waktu = 'Sudah Mati';
                        } elseif ($sisa_hari <= 90) { // Kurang dari atau sama dengan 3 bulan
                            $status_badge = '<span class="badge badge-warning">Peringatan</span>';
                            $keterangan_waktu = $sisa_hari . ' Hari Lagi';
                        } else {
                            $status_badge = '<span class="badge badge-aktif">Aktif</span>';
                            $keterangan_waktu = $sisa_hari . ' Hari';
                        }

                        // Format Tanggal Indonesia
                        $tgl_terbit  = date('d-m-Y', strtotime($row['tanggal_terbit']));
                        $tgl_expired = date('d-m-Y', strtotime($row['tanggal_expired']));
                ?>
                    <tr>
                        <td style="text-align: center; color: #94a3b8; font-weight: 600;"><?= $no++; ?></td>
                        <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['nama_pelamar']); ?></td>
                        <td style="font-family: monospace; font-size: 13px; color: #4f46e5; font-weight: bold;"><?= htmlspecialchars($row['nomor_sip']); ?></td>
                        <td><?= $tgl_terbit; ?></td>
                        <td><?= $tgl_expired; ?></td>
                        <td style="text-align: center;">
                            <?= $status_badge; ?>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 3px; font-weight: 600;"><?= $keterangan_waktu; ?></div>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($row['file_sip'])): ?>
                                <a href="uploads/sip/<?= $row['file_sip']; ?>" target="_blank" class="btn-view">
                                    👁️ Lihat Berkas
                                </a>
                            <?php else: ?>
                                <span style="color: #cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <tr>
                        <td colspan="7" class="text-empty">Belum ada riwayat berkas SIP yang terdaftar dalam sistem.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
