<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$DB_NAME = "psi"; 
$DB_USER = "root";
$DB_PASS = "";
$DB_SERVER_LOC = "localhost";

$conn = mysqli_connect($DB_SERVER_LOC, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    echo json_encode(array("status" => "error", "message" => "Koneksi database gagal"));
    exit();
}

// 1. Total Member Aktif
$query_member = "SELECT COUNT(*) as total FROM gym_members WHERE status IN ('member', 'active', 'aktif')";
$res_member = mysqli_query($conn, $query_member);
$row_member = mysqli_fetch_assoc($res_member);
$total_member = (int)$row_member['total'];

// 2. Pendapatan Tamu Harian
$query_non_member = "SELECT SUM(payment_amount) as total_tamu FROM daily_guests WHERE DATE(created_at) = CURDATE()";
$res_non_member = mysqli_query($conn, $query_non_member);
$row_non_member = mysqli_fetch_assoc($res_non_member);
$pemasukan_non_member = (int)$row_non_member['total_tamu'];

// 3. Member Baru Hari Ini
$query_member_baru = "SELECT COUNT(*) as jumlah_member_baru FROM gym_members WHERE DATE(created_at) = CURDATE()";
$res_member_baru = mysqli_query($conn, $query_member_baru);
$row_member_baru = mysqli_fetch_assoc($res_member_baru);
$pemasukan_member_baru = (int)$row_member_baru['jumlah_member_baru'] * 90000;

// 4. Perpanjangan Member
$query_perpanjangan = "SELECT SUM(amount) as total_perpanjangan FROM cashier_transactions WHERE DATE(transaction_at) = CURDATE()";
$res_perpanjangan = mysqli_query($conn, $query_perpanjangan);
$row_perpanjangan = mysqli_fetch_assoc($res_perpanjangan);
$pemasukan_perpanjangan = (int)$row_perpanjangan['total_perpanjangan'];

$pemasukan_hari_ini = $pemasukan_non_member + $pemasukan_member_baru + $pemasukan_perpanjangan;

// 5. Query Member Terbaru (FIXED: Menggunakan %d %b untuk menghasilkan format tanggal seperti 13 Jun)
$query_recent = "SELECT full_name, phone, DATE_FORMAT(created_at, '%d %b') as tanggal_daftar FROM gym_members ORDER BY id DESC LIMIT 5";
$res_recent = mysqli_query($conn, $query_recent);
$recent_members = array();
if ($res_recent) {
    while ($row = mysqli_fetch_assoc($res_recent)) {
        array_push($recent_members, $row);
    }
}

// 6. Query Check-In Terbaru
$query_checkin = "SELECT m.full_name, m.status as status_tipe, DATE_FORMAT(c.checked_in_at, '%H:%i') as jam_masuk 
                  FROM gym_checkins c 
                  JOIN gym_members m ON c.gym_member_id = m.id 
                  ORDER BY c.id DESC LIMIT 5";
$res_checkin = mysqli_query($conn, $query_checkin);
$recent_checkins = array();
if ($res_checkin) {
    while ($row = mysqli_fetch_assoc($res_checkin)) {
        array_push($recent_checkins, $row);
    }
}

$response = array(
    "total_member_aktif" => $total_member,
    "pemasukan_hari_ini" => $pemasukan_hari_ini,
    "recent_members" => $recent_members,
    "recent_checkins" => $recent_checkins
);

echo json_encode($response);
mysqli_close($conn);
?>