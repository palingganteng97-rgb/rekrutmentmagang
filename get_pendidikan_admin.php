<?php
// 1. PENGATURAN KONEKSI DATABASE SERVER Anda
$koneksi = mysqli_connect("10.10.6.59", "root_host", "password", "magang_rekrutmen_rs");

if (isset($_GET['pelamar_id']) && $koneksi) {
    $pelamar_id = intval($_GET['pelamar_id']);
    
    // Ambil seluruh data sekolah pelamar secara berurutan
    $q = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
    
    echo '<h4 style="font-size: 12px; font-weight: 800; color: #4f46e5; margin: 25px 0 12px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px; letter-spacing: 0.5px;">RIWAYAT PENDIDIKAN</h4>';
    echo '<div style="display: flex; flex-direction: column; gap: 10px; text-align: left;">';
    
    if (mysqli_num_rows($q) == 0) {
        echo '<p style="font-size: 13px; color: #94a3b8; font-style: italic;">Belum melengkapi data pendidikan.</p>';
    } else {
        $no = 1;
        while ($d = mysqli_fetch_assoc($q)) {
            echo '<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-size: 13px; line-height: 1.5;">';
            echo '<span style="font-size: 11px; font-weight: 700; color: #4f46e5; background: #e0e7ff; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 6px;">Pendidikan #' . $no++ . ' (' . htmlspecialchars($d['jenjang']) . ')</span>';
            echo '<table style="width: 100%; border-collapse: collapse; color: #475569;">';
            echo '<tr><td style="width: 140px; font-weight: 600; padding: 2px 0;">Institusi / Kampus</td><td>: ' . htmlspecialchars($d['institusi']) . '</td></tr>';
            echo '<tr><td style="font-weight: 600; padding: 2px 0;">Jurusan / Prodi</td><td>: ' . htmlspecialchars($d['jurusan']) . '</td></tr>';
            echo '<tr><td style="font-weight: 600; padding: 2px 0;">Tahun Lulus / IPK</td><td>: Lulus Th. ' . htmlspecialchars($d['tahun_lulus']) . ' (IPK/Nilai: ' . htmlspecialchars($d['ipk']) . ')</td></tr>';
            echo '</table>';
            echo '</div>';
        }
    }
    echo '</div>';
}
?>
