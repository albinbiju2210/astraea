<?php
// cron_penalty.php - Run this via Task Scheduler or manually
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/db.php';

echo "Running Penalty Check at " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------\n";

function calculatePenalty($exit_time_str) {
    $exit_time = strtotime($exit_time_str);
    $now = time();
    $hours_since_exit = ($now - $exit_time) / 3600;
    
    $penalty = 0;
    $is_blacklisted = false;

    // Grace Period: 2 Hours
    if ($hours_since_exit > 2) {
        // Penalty starts AFTER 2 hours? Or total hours?
        // Requirement: "if the user doesint pay his fee within 2 hrs of exit add penalty of 50 per hour"
        // Interpretation: Grace period of 2 hours. If exceeded, charged for hours AFTER grace.
        // Let's stick to (Hours - 2) * 50 as planned.
        
        $penalty_hours = ceil($hours_since_exit - 2);
        $penalty = $penalty_hours * 50.00;

        // Blacklist Condition: > 24 hours
        if ($hours_since_exit > 24) {
            $is_blacklisted = true;
            $penalty += 1000.00; // Super Fine
        }
    }
    
    return ['penalty' => $penalty, 'is_blacklisted' => $is_blacklisted, 'hours' => $hours_since_exit];
}

try {
    // 1. Fetch COMPLETED bookings that are UNPAID
    $stmt = $pdo->query("
        SELECT id, user_id, exit_time, total_amount, penalty 
        FROM bookings 
        WHERE status = 'completed' AND payment_status != 'paid'
    ");
    $bookings = $stmt->fetchAll();
    
    $count = 0;
    $blacklisted_count = 0;

    foreach ($bookings as $b) {
        $result = calculatePenalty($b['exit_time']);
        $new_penalty = $result['penalty'];
        $should_blacklist = $result['is_blacklisted'];

        // Update if penalty changed
        if ($new_penalty > $b['penalty']) {
            $update = $pdo->prepare("UPDATE bookings SET penalty = ? WHERE id = ?");
            $update->execute([$new_penalty, $b['id']]);
            echo "[Booking #{$b['id']}] Penalty updated to ₹{$new_penalty} (Hours overdue: " . round($result['hours'], 2) . ")\n";
            $count++;
        }

        // Handle Blacklisting
        if ($should_blacklist) {
            // Check if already blacklisted
            $u_check = $pdo->prepare("SELECT is_blacklisted FROM users WHERE id = ?");
            $u_check->execute([$b['user_id']]);
            $is_bl = $u_check->fetchColumn();

            if (!$is_bl) {
                $pdo->prepare("UPDATE users SET is_blacklisted = 1 WHERE id = ?")->execute([$b['user_id']]);
                echo "[User #{$b['user_id']}] BLACKLISTED due to Booking #{$b['id']}\n";
                $blacklisted_count++;
            }
        }
    }

    echo "--------------------------------------------------\n";
    echo "Processed " . count($bookings) . " unpaid bookings.\n";
    echo "Updated penalties for $count bookings.\n";
    echo "Blacklisted $blacklisted_count users.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
