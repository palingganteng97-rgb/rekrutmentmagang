<?php
session_start();
header('Content-Type: application/json');
include 'koneksi.php'; 

$pelamar_id = $_SESSION['pelamar_id'] ?? null;

if (!$pelamar_id) {
    echo json_encode(['status' => 'belum_login']);
    exit;
}

// 1. AMBIL DATA BIODATA UTAMA
$query_profil = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = '$pelamar_id'");
$data_db = mysqli_fetch_assoc($query_profil);

if (!$data_db) {
    echo json_encode(['status' => 'belum_lengkap']);
    exit;
}

// =========================================================================
// 2. AMBIL DATA PENDIDIKAN TERAKHIR (LENGKAP DENGAN KAMPUS & JURUSAN)
// =========================================================================
$query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = '$pelamar_id' ORDER BY id DESC LIMIT 1");
$data_pend = mysqli_fetch_assoc($query_pend);

$pendidikan_terakhir = 'Belum diisi';

if ($data_pend) {
    // A. Deteksi nama kolom jenjang (S1/D3/SMA)
    $jenjang = $data_pend['tingkat_pendidikan'] ?? $data_pend['pendidikan'] ?? $data_pend['jenjang'] ?? '';
    
    // B. Deteksi nama kolom institusi (Nama Universitas / Sekolah)
    $institusi = $data_pend['nama_institusi'] ?? $data_pend['institusi'] ?? $data_pend['sekolah'] ?? $data_pend['kampus'] ?? '';
    
    // C. Ambil kolom jurusan
    $jurusan = $data_pend['jurusan'] ?? '';

    // Gabungkan data menjadi format teks yang informatif dan lengkap
    if (!empty($jenjang)) {
        $pendidikan_terakhir = "<strong>" . htmlspecialchars($jenjang) . "</strong>";
        
        if (!empty($institusi)) {
            $pendidikan_terakhir .= " - " . htmlspecialchars($institusi);
        }
        if (!empty($jurusan)) {
            $pendidikan_terakhir .= "<br><span class='text-xs text-gray-500'>Jurusan: " . htmlspecialchars($jurusan) . "</span>";
        }
    }
}

// 3. AMBIL DATA RIWAYAT PENGALAMAN KERJA
$query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = '$pelamar_id'");
$html_pengalaman = "";
if ($query_exp && mysqli_num_rows($query_exp) > 0) {
    while ($exp = mysqli_fetch_assoc($query_exp)) {
        $html_pengalaman .= "<div class='mb-2 pb-1 border-b border-dashed border-gray-200 last:border-0 last:pb-0'>
            <strong>" . htmlspecialchars($exp['perusahaan']) . "</strong><br>
            <span class='text-xs text-gray-500'>" . htmlspecialchars($exp['jabatan']) . " (" . htmlspecialchars($exp['mulai_kerja']) . " s/d " . htmlspecialchars($exp['selesai_kerja']) . ")</span><br>
            <span class='text-xs italic text-gray-400'>Alasan: " . htmlspecialchars($exp['alasan_keluar'] ?? '-') . "</span>
        </div>";
    }
} else {
    $html_pengalaman = "<span class='text-red-500 italic'>Belum diisi / Tidak ada</span>";
}

// 4. AMBIL DATA STR DAN FILE GAMBARNYA
$query_str = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = '$pelamar_id'");
$html_str = "";
if ($query_str && mysqli_num_rows($query_str) > 0) {
    while ($str = mysqli_fetch_assoc($query_str)) {
        $nama_file_str = $str['file_str'] ?? $str['berkas_str'] ?? '';
        $link_berkas_str = "-";
        if (!empty($nama_file_str)) {
            $link_berkas_str = "<a href='uploads/" . htmlspecialchars($nama_file_str) . "' target='_blank' class='inline-flex items-center gap-1 text-xs text-blue-600 hover:underline font-semibold mt-1'>
                <span class='material-symbols-outlined text-[14px]'>visibility</span> Lihat Berkas STR
            </a>";
        }

        $html_str .= "<div class='mb-2 pb-1 border-b border-dashed border-gray-200 last:border-0 last:pb-0'>
            <strong>No: " . htmlspecialchars($str['nomor_str']) . "</strong><br>
            <span class='text-xs text-gray-500'>Berlaku s/d: " . htmlspecialchars($str['tanggal_expired'] ?? '-') . "</span><br>
            $link_berkas_str
        </div>";
    }
} else {
    $html_str = "<span class='text-red-500 italic'>Belum diisi / Tidak ada</span>";
}

// 🔥 5. AMBIL DATA BERKAS DOKUMEN LAIN (SISTEM TOLERANSI STRUKTUR DATABASES)
// Otomatis mencari nama tabel yang aktif di database Anda
$cek_tabel_berkas = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_dokumen'");
$nama_tabel_aktif = (mysqli_num_rows($cek_tabel_berkas) > 0) ? 'pelamar_dokumen' : 'pelamar_berkas';

$query_berkas = mysqli_query($koneksi, "SELECT * FROM $nama_tabel_aktif WHERE pelamar_id = '$pelamar_id'");
$html_berkas = "";

if ($query_berkas && mysqli_num_rows($query_berkas) > 0) {
    while ($berkas = mysqli_fetch_assoc($query_berkas)) {
        // Otomatis mendeteksi nama kolom file dokumen yang tersedia di tabel Anda
        $nama_file_dokumen = $berkas['nama_berkas'] ?? $berkas['file_berkas'] ?? $berkas['file_dokumen'] ?? $berkas['berkas'] ?? $berkas['file'] ?? $berkas['nama_file'] ?? '';
        
        // Otomatis mendeteksi jenis atau nama dokumen (Misal: Ijazah)
        $jenis_berkas = $berkas['jenis_berkas'] ?? $berkas['nama_berkas_pilihan'] ?? $berkas['nama'] ?? 'Ijazah';
        
        $link_dokumen = "<span class='text-red-500 italic text-xs'>Berkas tidak terbaca</span>";
        if (!empty($nama_file_dokumen)) {
            $link_dokumen = "<a href='uploads/" . htmlspecialchars($nama_file_dokumen) . "' target='_blank' class='inline-flex items-center gap-1 text-xs text-blue-600 hover:underline font-semibold mt-1'>
                <span class='material-symbols-outlined text-[14px]'>visibility</span> Lihat Berkas " . htmlspecialchars($jenis_berkas) . "
            </a>";
        }

        $html_berkas .= "<div class='mb-2 pb-1 border-b border-dashed border-gray-200 last:border-0 last:pb-0 text-left'>
            <strong>📌 " . htmlspecialchars($jenis_berkas) . "</strong><br>
            $link_dokumen
        </div>";
    }
} else {
    // Cadangan fallback terakhir: Jika data ijazah ternyata digabung langsung di tabel induk 'pelamar'
    if (!empty($data_db['ijazah']) || !empty($data_db['file_ijazah'])) {
        $file_ijazah_induk = $data_db['ijazah'] ?? $data_db['file_ijazah'];
        $html_berkas = "<div class='text-left'>
            <strong>📌 Ijazah</strong><br>
            <a href='uploads/" . htmlspecialchars($file_ijazah_induk) . "' target='_blank' class='inline-flex items-center gap-1 text-xs text-blue-600 hover:underline font-semibold mt-1'>
                <span class='material-symbols-outlined text-[14px]'>visibility</span> Lihat Berkas Ijazah
            </a>
        </div>";
    } else {
        $html_berkas = "<span class='text-red-500 italic text-xs'>Belum ada berkas dokumen yang diunggah</span>";
    }
}


// 6. SUSUN STRUKTUR TAMPILAN FINAL UNTUK JAVASCRIPT MODAL
$data_kirim = [
    'Foto_Pelamar'      => !empty($data_db['foto']) ? "<img src='uploads/" . htmlspecialchars($data_db['foto']) . "' class='w-16 h-16 rounded-full object-cover border shadow-sm'>" : null,
    'Nama_Lengkap'      => $data_db['nama_lengkap'] ?? $data_db['nama'] ?? null,
    'NIK'               => $data_db['nik'] ?? null,
    'Alamat_Rumah'      => $data_db['alamat'] ?? null,
    'Kota_/_Provinsi'   => ($data_db['kota'] ?? '') . " / " . ($data_db['provinsi'] ?? ''),
    'Telepon_/_WhatsApp'=> $data_db['no_telepon'] ?? $data_db['telepon'] ?? null,
    'Pendidikan'        => $pendidikan_terakhir,
    'Pengalaman_Kerja'  => $html_pengalaman,
    'Surat_Tanda_Registrasi_(STR)' => $html_str,
    'Berkas_Lampiran_(Ijazah)'     => $html_berkas // 🔥 Menyuntikkan HTML list Ijazah/Dokumen
];

echo json_encode([
    'status' => 'siap_lamar',
    'data'   => $data_kirim
]);
exit;
