<?php
error_reporting(0);
ini_set('display_errors', 0);

$conn = mysqli_connect("localhost", "root", "", "psi");

if (!$conn) {
    die("Koneksi database gagal untuk ekspor data.");
}

date_default_timezone_set('Asia/Jakarta');

// Ambil data GET parameter filter tanggal
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

if (empty($start_date) || empty($end_date)) {
    die("Error: Rentang tanggal filter tidak valid!");
}

// FORCE DOWNLOAD FILE DENGAN HEADER MS-EXCEL
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Rekap_Omzet_ArenaGym_" . $start_date . "_to_" . $end_date . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$sql_query = "
    (SELECT 'Tamu Harian' as jenis, payment_amount as total, created_at as tanggal FROM daily_guests WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date')
    UNION ALL
    (SELECT 'Member Baru' as jenis, 90000 as total, created_at as tanggal FROM gym_members WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date')
    UNION ALL
    (SELECT 'Perpanjangan Member' as jenis, amount as total, transaction_at as tanggal FROM cashier_transactions WHERE DATE(transaction_at) BETWEEN '$start_date' AND '$end_date')
    ORDER BY tanggal DESC
";

$result = mysqli_query($conn, $sql_query);
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="4" style="background-color: #1A1A1A; color: #FFFFFF; font-weight: bold; height: 35px; text-align: center; font-size: 14pt;">
                LAPORAN REKAPITULASI PENDAPATAN ARENA GYM
            </th>
        </tr>
        <tr>
            <th colspan="4" style="text-align: center; font-style: italic; height: 25px;">
                Periode Terfilter: <?php echo date('d M Y', strtotime($start_date)) . " s.d " . date('d M Y', strtotime($end_date)); ?>
            </th>
        </tr>
        <tr style="background-color: #D2D2D2; font-weight: bold; height: 25px;">
            <th width="60">NO</th>
            <th width="180">WAKTU TRANSAKSI</th>
            <th width="240">DESKRIPSI SUMBER DATA</th>
            <th width="180">NOMINAL OMZET (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        $grand_total = 0;
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $grand_total += (int)$row['total'];
                echo "<tr>";
                echo "<td align='center'>" . $no++ . "</td>";
                echo "<td align='center'>" . date('d-m-Y H:i', strtotime($row['tanggal'])) . "</td>";
                echo "<td>" . $row['jenis'] . "</td>";
                echo "<td align='right'>" . number_format($row['total'], 0, ',', '.') . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4' align='center' style='color: red; font-weight: bold;'>Tidak ditemukan data transaksi untuk periode rentang tanggal ini.</td></tr>";
        }
        ?>
        <tr style="background-color: #C1FFC0; font-weight: bold; height: 30px;">
            <td colspan="3" align="right">TOTAL AKUMULASI OMZET TERFILTER:</td>
            <td align="right">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></td>
        </tr>
    </tbody>
</table>

<?php 
mysqli_close($conn);
?>