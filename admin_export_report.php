<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_reports.php');
    exit;
}

$month_str = $_POST['report_month'] ?? ''; // Format: 2026-01
$password = $_POST['password'] ?? '';

if (!$month_str || !$password) {
    header('Location: admin_reports.php?error=Missing required fields');
    exit;
}

try {
    // 1. Authenticate Admin Password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND is_admin = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        header('Location: admin_reports.php?error=Incorrect Admin Password');
        exit;
    }

    // 2. Prepare Date Range
    $start_date = $month_str . '-01 00:00:00';
    $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
    
    // 3. Gather Data
    
    // 3. Gather Data
    // Filter by Lot if applicable
    $lot_filter = "";
    $params = [$start_date, $end_date];
    
    if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
        $lot_filter = " AND s.lot_id = ?";
        $params[] = $_SESSION['admin_lot_id'];
    }

    // Summary Stats
    // Total Revenue (Only completed bookings)
    $sql = "SELECT SUM(total_amount), SUM(penalty) FROM bookings b JOIN parking_slots s ON b.slot_id = s.id WHERE b.status='completed' AND b.created_at BETWEEN ? AND ?" . $lot_filter;
    $rev_stmt = $pdo->prepare($sql);
    $rev_stmt->execute($params);
    $rev_res = $rev_stmt->fetch();
    $total_revenue = $rev_res[0] ?? 0;
    $total_penalty_paid = $rev_res[1] ?? 0;

    $sql = "SELECT COUNT(*) FROM bookings b JOIN parking_slots s ON b.slot_id = s.id WHERE b.created_at BETWEEN ? AND ?" . $lot_filter;
    $total_bookings = $pdo->prepare($sql);
    $total_bookings->execute($params);
    $count_bookings = $total_bookings->fetchColumn();

    $sql = "SELECT COUNT(DISTINCT b.user_id) FROM bookings b JOIN parking_slots s ON b.slot_id = s.id WHERE b.created_at BETWEEN ? AND ?" . $lot_filter;
    $unique_users = $pdo->prepare($sql);
    $unique_users->execute($params);
    $count_users = $unique_users->fetchColumn();

    // Daily Breakdown (Activity + Revenue)
    $sql = "
        SELECT DATE(b.created_at) as log_date, COUNT(*) as b_count, SUM(COALESCE(b.total_amount, 0)) as day_rev, SUM(COALESCE(b.penalty, 0)) as day_pen
        FROM bookings b 
        JOIN parking_slots s ON b.slot_id = s.id
        WHERE b.created_at BETWEEN ? AND ? 
        $lot_filter
        GROUP BY DATE(b.created_at) 
        ORDER BY log_date ASC
    ";
    $daily = $pdo->prepare($sql);
    $daily->execute($params);
    $daily_data = $daily->fetchAll();

    // Peak Hours Analysis
    $sql = "
        SELECT HOUR(b.created_at) as hr, COUNT(*) as c 
        FROM bookings b 
        JOIN parking_slots s ON b.slot_id = s.id
        WHERE b.created_at BETWEEN ? AND ? 
        $lot_filter
        GROUP BY hr ORDER BY c DESC LIMIT 5
    ";
    $peak_stmt = $pdo->prepare($sql);
    $peak_stmt->execute($params);
    $peaks = $peak_stmt->fetchAll();

    // 4. Generate HTML Report
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Astraea Report - <?php echo $month_str; ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap');

            :root {
                --bg-gradient: linear-gradient(180deg, #fdfbfb 0%, #ebedee 100%);
                --bg-color: #fdfbfb;
                --card-bg: rgba(255, 255, 255, 0.7);
                --card-border: 1px solid rgba(255, 255, 255, 0.9);
                --shadow: 0 10px 40px -10px rgba(180, 190, 200, 0.4);
                --text: #5e5e6e;
                --heading-text: #2c2c36;
                --muted: #9fa0a8;
                --accent: #a6a6b8;
                --accent-gradient: linear-gradient(135deg, #e0e0e0 0%, #ffffff 100%);
                --border: #e6e6e9;
            }
            body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg-gradient); color: var(--text); padding: 40px; margin: 0; font-weight: 300; min-height: 100vh; }
            .report-container { max-width: 900px; margin: 0 auto; background: var(--card-bg); padding: 50px; box-shadow: var(--shadow); border-radius: 20px; border: var(--card-border); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
            .header-info { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; }
            h1 { margin: 0; color: var(--heading-text); font-size: 2.2rem; font-family: 'Playfair Display', serif; font-weight: 600; }
            h2 { color: var(--heading-text); border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-top: 40px; font-size: 1.6rem; font-family: 'Playfair Display', serif; font-weight: 600; }
            .meta-text { font-family: 'Inter', sans-serif; font-size: 0.95rem; color: var(--muted); line-height: 1.6; }
            
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: 'Inter', sans-serif; background: rgba(255,255,255,0.4); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
            th, td { padding: 15px 20px; text-align: left; }
            th { background: rgba(255,255,255,0.8); color: var(--heading-text); font-weight: 500; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
            td { border-bottom: 1px solid rgba(0,0,0,0.03); }
            tr:last-child td { border-bottom: none; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            
            .summary-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 24px; }
            .card { background: #ffffff; padding: 24px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.03); box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05); transition: transform 0.3s ease; }
            .card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -5px rgba(0,0,0,0.08); }
            .card h3 { margin: 0 0 12px 0; color: var(--heading-text); font-size: 1rem; text-transform: uppercase; font-family: 'Inter', sans-serif; font-weight: 500; }
            .card .value { font-size: 2.4rem; color: var(--heading-text); font-weight: 400; font-family: 'Playfair Display', serif; }
            
            .print-btn-container { text-align: center; margin-bottom: 30px; }
            .btn-print { display: inline-flex; justify-content: center; align-items: center; background: var(--accent-gradient); color: var(--text); border: 1px solid #ffffff; padding: 12px 24px; font-size: 1rem; cursor: pointer; border-radius: 12px; font-family: 'Inter', sans-serif; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-decoration: none; }
            .btn-print:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.08); background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%); }
            .btn-back { display: inline-flex; justify-content: center; align-items: center; background: transparent; color: var(--text); border: 1px solid var(--accent); padding: 12px 24px; font-size: 1rem; font-family: 'Inter', sans-serif; font-weight: 500; border-radius: 12px; margin-left: 15px; text-decoration: none; transition: all 0.3s ease; }
            .btn-back:hover { background: rgba(255,255,255,0.5); border-color: var(--text); }

            @media print {
                .print-btn-container { display: none; }
                body { padding: 0; background: #fff; min-height: auto; }
                .report-container { box-shadow: none; border: none; padding: 0; filter: none; max-width: 100%; border-radius: 0; }
                .card { box-shadow: none; border: 1px solid #ddd; padding: 20px; }
                table { border: 1px solid #ddd; border-radius: 0; }
                th { background: #f9f9f9; border-bottom: 2px solid #ddd; color: #333; }
                td { border-bottom: 1px solid #eee; color: #333; }
                h1, h2, .card .value, .card h3 { color: #333; }
            }
        </style>
    </head>
    <body>
        <div class="print-btn-container">
            <button class="btn-print" onclick="window.print()">🖨️ Print Report</button>
            <a href="admin_reports.php" class="btn-back">&larr; Back to Dashboard</a>
        </div>
        <div class="report-container">
            <div class="header-info">
                <div>
                    <h1>ASTRAEA PARKING</h1>
                    <div style="font-size: 1.1rem; color: var(--muted); margin-top: 5px; font-family: 'Inter', sans-serif;">Comprehensive Analysis Report</div>
                </div>
                <div class="meta-text text-right">
                    <strong>Report Month:</strong> <?php echo date('F Y', strtotime($month_str . '-01')); ?><br>
                    <strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['admin_name']); ?><br>
                    <strong>Exported On:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                </div>
            </div>

            <h2>I. Executive Summary</h2>
            <div class="summary-cards">
                <div class="card">
                    <h3>Total Revenue</h3>
                    <div class="value">₹<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="card">
                    <h3>Penalty Collected</h3>
                    <div class="value">₹<?php echo number_format($total_penalty_paid, 2); ?></div>
                </div>
                <div class="card">
                    <h3>Total Bookings</h3>
                    <div class="value"><?php echo $count_bookings; ?></div>
                </div>
                <div class="card">
                    <h3>Unique Active Customers</h3>
                    <div class="value"><?php echo $count_users; ?></div>
                </div>
            </div>

            <h2>II. Daily Activity & Revenue Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-center">Bookings</th>
                        <th class="text-right">Revenue (₹)</th>
                        <th class="text-right">Penalties (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($daily_data) > 0): ?>
                        <?php foreach ($daily_data as $row): ?>
                            <tr>
                                <td><?php echo $row['log_date']; ?></td>
                                <td class="text-center"><?php echo $row['b_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['day_rev'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['day_pen'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No data for this month.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>III. Peak Operational Hours</h2>
            <table>
                <thead>
                    <tr>
                        <th>Hour Slot</th>
                        <th class="text-center">Booking Volume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($peaks) > 0): ?>
                        <?php foreach ($peaks as $p): ?>
                            <tr>
                                <td><?php echo date('g A', strtotime($p['hr'].":00")); ?></td>
                                <td class="text-center"><?php echo $p['c']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center">No data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Section 4: Customer Satisfaction
            $sql = "
                SELECT r.rating, r.review_text, b.created_at, u.name 
                FROM reviews r
                JOIN bookings b ON r.booking_id = b.id
                JOIN users u ON b.user_id = u.id
                JOIN parking_slots s ON b.slot_id = s.id
                WHERE b.created_at BETWEEN ? AND ?
                $lot_filter
                ORDER BY r.created_at DESC
            ";
            $reviews_stmt = $pdo->prepare($sql);
            $reviews_stmt->execute($params);
            $reviews_data = $reviews_stmt->fetchAll();
            ?>

            <?php if (count($reviews_data) > 0): ?>
                <h2>IV. Customer Reviews & Feedback</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th class="text-center">Rating</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_stars = 0;
                        foreach ($reviews_data as $rev): 
                            $total_stars += $rev['rating'];
                        ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($rev['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($rev['name']); ?></td>
                                <td class="text-center"><?php echo $rev['rating']; ?> / 5</td>
                                <td><?php echo htmlspecialchars($rev['review_text']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-right"><strong>Average Satisfaction Score:</strong></td>
                            <td colspan="2" class="text-center"><strong><?php echo round($total_stars / count($reviews_data), 2); ?> / 5</strong></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
            
            <div style="margin-top: 50px; text-align: center; color: #999; font-size: 0.85rem; font-family: sans-serif;">
                &copy; <?php echo date('Y'); ?> Astraea Parking System. System Generated Report.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;

} catch (Exception $e) {
    header('Location: admin_reports.php?error=Report Generation Failed: ' . urlencode($e->getMessage()));
    exit;
}
