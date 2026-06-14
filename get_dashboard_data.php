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

date_default_timezone_set('Asia/Jakarta');

$filter_range = $_POST['filter_range'] ?? 'hari_ini';

if ($filter_range === 'minggu_ini') {
    $cond_created_at   = "WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $cond_transaction  = "WHERE YEARWEEK(transaction_at, 1) = YEARWEEK(CURDATE(), 1)";
    $cond_checked_in   = "WHERE YEARWEEK(checked_in_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter_range === 'bulan_ini') {
    $cond_created_at   = "WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    $cond_transaction  = "WHERE MONTH(transaction_at) = MONTH(CURDATE()) AND YEAR(transaction_at) = YEAR(CURDATE())";
    $cond_checked_in   = "WHERE MONTH(checked_in_at) = MONTH(CURDATE()) AND YEAR(checked_in_at) = YEAR(CURDATE())";
} else {
    // Default: hari_ini
    $cond_created_at   = "WHERE DATE(created_at) = CURDATE()";
    $cond_transaction  = "WHERE DATE(transaction_at) = CURDATE()";
    $cond_checked_in   = "WHERE DATE(checked_in_at) = CURDATE()";
}

// 1. Total Member Aktif keseluruhan
$query_member = "SELECT COUNT(*) as total FROM gym_members WHERE status IN ('member', 'active', 'aktif')";
$res_member = mysqli_query($conn, $query_member);
$row_member = mysqli_fetch_assoc($res_member);
$total_member = (int)$row_member['total'];

// 2. NEW LOGIC: Total Check-In Periode Terpilih (Gabungan Checkin Member + Tamu Daily)
$q_checkin_member = mysqli_query($conn, "SELECT COUNT(*) as total FROM gym_checkins " . $cond_checked_in);
$r_checkin_member = mysqli_fetch_assoc($q_checkin_member);

$q_checkin_guest = mysqli_query($conn, "SELECT COUNT(*) as total FROM daily_guests " . $cond_created_at);
$r_checkin_guest = mysqli_fetch_assoc($q_checkin_guest);

$total_checkin_periode = (int)$r_checkin_member['total'] + (int)$r_checkin_guest['total'];

// 3. Hitung Omzet Pendapatan Berdasarkan Filter Range
$query_non_member = "SELECT SUM(payment_amount) as total_tamu FROM daily_guests " . $cond_created_at;
$res_non_member = mysqli_query($conn, $query_non_member);
$row_non_member = mysqli_fetch_assoc($res_non_member);
$pemasukan_non_member = (int)$row_non_member['total_tamu'];

$query_member_baru = "SELECT COUNT(*) as jumlah_member_baru FROM gym_members " . $cond_created_at;
$res_member_baru = mysqli_query($conn, $query_member_baru);
$row_member_baru = mysqli_fetch_assoc($res_member_baru);
$pemasukan_member_baru = (int)$row_member_baru['jumlah_member_baru'] * 90000;

$query_perpanjangan = "SELECT SUM(amount) as total_perpanjangan FROM cashier_transactions " . $cond_transaction;
$res_perpanjangan = mysqli_query($conn, $query_perpanjangan);
$row_perpanjangan = mysqli_fetch_assoc($res_perpanjangan);
$pemasukan_perpanjangan = (int)$row_perpanjangan['total_perpanjangan'];

$pemasukan_total_periode = $pemasukan_non_member + $pemasukan_member_baru + $pemasukan_perpanjangan;

// 4. Query Member Terbaru (Limit 5)
$query_recent = "SELECT full_name, phone, DATE_FORMAT(created_at, '%d %b') as tanggal_daftar FROM gym_members ORDER BY id DESC LIMIT 5";
$res_recent = mysqli_query($conn, $query_recent);
$recent_members = array();
if ($res_recent) {
    while ($row = mysqli_fetch_assoc($res_recent)) {
        array_push($recent_members, $row);
    }
}

// 5. Query Check-In Terbaru (Limit 5)
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

// BUNDLING RESPONSE JSON
$response = array(
    "total_member_aktif" => $total_member,
    "total_checkin_periode" => $total_checkin_periode, // NEW OUTPUT
    "pemasukan_hari_ini" => $pemasukan_total_periode,
    "recent_members" => $recent_members,
    "recent_checkins" => $recent_checkins
);

echo json_encode($response);
mysqli_close($conn);
?>