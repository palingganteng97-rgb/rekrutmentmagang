<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php'; // Pastikan nama file koneksi Anda sudah benar

$pelamar_id = isset($_GET['pelamar_id']) ? (int)$_GET['pelamar_id'] : 0;

if ($pelamar_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID Pelamar tidak valid.']);
    exit;
}

// 🟢 1. AMBIL DATA BERKAS (IJAZAH / LAMPIRAN)
// Deteksi dinamis nama tabel berkas pelamar
$cek_tabel_berkas = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_dokumen'");
$tabel_berkas_aktif = (mysqli_num_rows($cek_tabel_berkas) > 0) ? 'pelamar_dokumen' : 'pelamar_berkas';

$query_berkas = mysqli_query($koneksi, "SELECT * FROM $tabel_berkas_aktif WHERE pelamar_id = $pelamar_id");
$data_berkas = [];

if ($query_berkas && mysqli_num_rows($query_berkas) > 0) {
    while ($bk = mysqli_fetch_assoc($query_berkas)) {
        // 🔥 MENYELARASKAN DENGAN JAVASCRIPT: Menggunakan properti 'jenis_berkas' dan 'nama_file'
        $jenis = $bk['jenis_berkas'] ?? $bk['nama_berkas'] ?? $bk['nama'] ?? 'Berkas Lampiran';
        $file  = $bk['nama_file'] ?? $bk['nama_berkas'] ?? $bk['file_berkas'] ?? $bk['file_dokumen'] ?? $bk['berkas'] ?? $bk['file'] ?? '';
        
        $data_berkas[] = [
            'jenis_berkas' => $jenis,
            'nama_file'    => $file
        ];
    }
}

// 🟢 2. AMBIL DATA SURAT TANDA REGISTRASI (STR)
$query_str = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
$data_str = [];

if ($query_str && mysqli_num_rows($query_str) > 0) {
    while ($s = mysqli_fetch_assoc($query_str)) {
        // 🔥 MENYELARASKAN DENGAN JAVASCRIPT: Menggunakan properti 'nomor_str', 'tanggal_terbit', 'tanggal_expired', 'file_str'
        $file_str_aktif = $s['file_str'] ?? $s['berkas_str'] ?? $s['file'] ?? '';
        
        $data_str[] = [
            'nomor_str'       => $s['nomor_str'] ?? '-',
            'tanggal_terbit'  => !empty($s['tanggal_terbit']) ? date('d/m/Y', strtotime($s['tanggal_terbit'])) : '-',
            'tanggal_expired' => !empty($s['tanggal_expired']) ? date('d/m/Y', strtotime($s['tanggal_expired'])) : '-',
            'file_str'        => $file_str_aktif
        ];
    }
}

// 🟢 3. KIRIM RESPON GABUNGAN DALAM SATU PAKET JSON (Sesuai Kebutuhan Fetch JavaScript Anda)
echo json_encode([
    'status'  => 'success',
    'berkas'  => $data_berkas,
    'str'     => $data_str
]);
exit;
