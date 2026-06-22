<?php
session_start();
$conn = mysqli_connect("10.10.6.59", "root_host", "password", "magang_rekrutmen_rs");

$id_lamaran = $_GET['id_lamaran'] ?? 0;
$id_mst_thp = $_GET['id_mst_tahapan'] ?? 0;

$data_response = ['nilai' => '', 'catatan' => ''];

if ($id_lamaran && $id_mst_thp) {
    // SINKRON: Cari nilai berdasarkan lamaran_tahapan_id DAN tipe mst_tahapan_id nya masing-masing
    $query = mysqli_query($conn, "SELECT nilai, catatan FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$id_lamaran' AND mst_tahapan_id = '$id_mst_thp' LIMIT 1");
    if ($row = mysqli_fetch_assoc($query)) {
        $data_response['nilai']   = $row['nilai'];
        $data_response['catatan'] = $row['catatan'];
    }
}

header('Content-Type: application/json');
echo json_encode($data_response);
exit;
