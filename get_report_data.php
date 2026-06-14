<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$conn = mysqli_connect("localhost", "root", "", "psi");

if (!$conn) {
    echo json_encode(array("status" => "error", "message" => "Koneksi database gagal"));
    exit();
}

date_default_timezone_set('Asia/Jakarta');

// Ambil data POST filter range tanggal dari Android
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date'] ?? '';

// LOGIKA PENYARINGAN KLAUSA WHERE BERDASARKAN FILTER TANGGAL
if (!empty($start_date) && !empty($end_date)) {
    $cond_created_at   = "WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $cond_transaction  = "WHERE DATE(transaction_at) BETWEEN '$start_date' AND '$end_date'";
    $cond_checked_in   = "WHERE DATE(checked_in_at) BETWEEN '$start_date' AND '$end_date'";
} else {
    // Default: jika filter kosong, tampilkan statistik khusus Hari Ini
    $cond_created_at   = "WHERE DATE(created_at) = CURDATE()";
    $cond_transaction  = "WHERE DATE(transaction_at) = CURDATE()";
    $cond_checked_in   = "WHERE DATE(checked_in_at) = CURDATE()";
}

// 1. Total Seluruh Anggota Member (Aktif)
$q_member = mysqli_query($conn, "SELECT COUNT(*) as total FROM gym_members WHERE status IN ('member', 'active', 'aktif')");
$r_member = mysqli_fetch_assoc($q_member);
$total_member = (int)$r_member['total'];

// 2. Total Check-In Sesuai Periode Filter
$q_ci_m = mysqli_query($conn, "SELECT COUNT(*) as total FROM gym_checkins " . $cond_checked_in);
$r_ci_m = mysqli_fetch_assoc($q_ci_m);
$q_ci_n = mysqli_query($conn, "SELECT COUNT(*) as total FROM daily_guests " . $cond_created_at);
$r_ci_n = mysqli_fetch_assoc($q_ci_n);
$total_checkin_periode = (int)$r_ci_m['total'] + (int)$r_ci_n['total'];

// 3. Pemasukan Sesuai Periode Filter
$r_income_guest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(payment_amount) as total FROM daily_guests " . $cond_created_at));
$r_income_new = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM gym_members " . $cond_created_at));
$r_income_renew = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM cashier_transactions " . $cond_transaction));

$pemasukan_periode = (int)$r_income_guest['total'] + ((int)$r_income_new['total'] * 90000) + (int)$r_income_renew['total'];

// 4. Total Seluruh Pemasukan Kas Akumulatif Sepanjang Waktu
$r_all_guest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(payment_amount) as total FROM daily_guests"));
$r_all_new = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM gym_members"));
$r_all_renew = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM cashier_transactions"));

$total_pemasukan_all = (int)$r_all_guest['total'] + ((int)$r_all_new['total'] * 90000) + (int)$r_all_renew['total'];

// 5. AMBIL DATA GRAFIK BULANAN (6 Bulan Terakhir)
$grafik_data = array();
for ($i = 5; $i >= 0; $i--) {
    $months_ago = date('Y-m', strtotime("-$i month"));
    $month_name = date('M', strtotime("-$i month"));
    
    $g_guest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(payment_amount) as total FROM daily_guests WHERE DATE_FORMAT(created_at, '%Y-%m') = '$months_ago'"));
    $g_new = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM gym_members WHERE DATE_FORMAT(created_at, '%Y-%m') = '$months_ago'"));
    $g_renew = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM cashier_transactions WHERE DATE_FORMAT(transaction_at, '%Y-%m') = '$months_ago'"));
    
    $total_bulan_ini = (int)$g_guest['total'] + ((int)$g_new['total'] * 90000) + (int)$g_renew['total'];
    
    $grafik_data[] = array(
        "bulan" => $month_name,
        "total" => $total_bulan_ini
    );
}

// Kirimkan satu paket data laporan
echo json_encode(array(
    "total_member" => $total_member,
    "total_checkin" => $total_checkin_periode,
    "pemasukan_hari_ini" => $pemasukan_periode,
    "total_pemasukan" => $total_pemasukan_all,
    "grafik" => $grafik_data
));

mysqli_close($conn);
?>