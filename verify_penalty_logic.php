<?php
date_default_timezone_set('Asia/Kolkata');

function calculatePenalty($exit_time_str) {
    $exit_time = strtotime($exit_time_str);
    $now = time();
    $hours_since_exit = ($now - $exit_time) / 3600;
    
    $penalty = 0;
    $is_blacklisted = false;

    // Grace Period: 2 Hours
    if ($hours_since_exit > 2) {
        // Requirement: "if the user doesint pay his fee within 2 hrs of exit add penalty of 50 per hour"
        // Grace period of 2 hours. If exceeded, charged for hours AFTER grace.
        
        $penalty_hours = ceil($hours_since_exit - 2);
        $penalty = $penalty_hours * 50.00;

        // Blacklist Condition: > 24 hours (doesnt pay within a day)
        if ($hours_since_exit > 24) {
            $is_blacklisted = true;
            $penalty += 1000.00; // Super Fine
        }
    }
    
    return ['penalty' => $penalty, 'is_blacklisted' => $is_blacklisted, 'hours' => $hours_since_exit];
}

echo "=== Penalty Logic Verification ===\n";

$tests = [
    ['exit' => '-1 hour', 'expected_penalty' => 0, 'blacklist' => false, 'desc' => '1 Hour (Grace)'],
    ['exit' => '-2 hours', 'expected_penalty' => 0, 'blacklist' => false, 'desc' => '2 Hours (Grace Limit)'],
    ['exit' => '-2 hours 1 minute', 'expected_penalty' => 50, 'blacklist' => false, 'desc' => '2 Hrs 1 Min (1 Hr Penalty)'],
    ['exit' => '-3 hours', 'expected_penalty' => 50, 'blacklist' => false, 'desc' => '3 Hours (1 Hr Penalty)'],
    ['exit' => '-4 hours', 'expected_penalty' => 100, 'blacklist' => false, 'desc' => '4 Hours (2 Hrs Penalty)'],
    ['exit' => '-24 hours', 'expected_penalty' => 1100, 'blacklist' => false, 'desc' => '24 Hours (22 Hrs Penalty)'], // 22 * 50 = 1100. Is it > 24? No, exact 24. Blacklist?
    ['exit' => '-24 hours 1 minute', 'expected_penalty' => 2150, 'blacklist' => true, 'desc' => '24 Hrs 1 Min (Super Fine)'], 
];
// Case 6 check: 24 hours. hours_since_exit = 24.
// > 2 check: 24 > 2. penalty_hours = ceil(22) = 22. penalty = 1100.
// > 24 check: 24 > 24 is FALSE. So no blacklist.

// Case 7 check: 24h 1m. hours_since_exit > 24.
// > 2 check: 24.01 > 2. penalty_hours = ceil(22.01) = 23. penalty = 1150.
// > 24 check: 24.01 > 24 is TRUE. blacklist = true. penalty += 1000 => 2150.

foreach ($tests as $t) {
    $exit_str = date('Y-m-d H:i:s', strtotime($t['exit']));
    $result = calculatePenalty($exit_str);
    
    $pass = ($result['penalty'] == $t['expected_penalty'] && $result['is_blacklisted'] == $t['blacklist']);
    
    echo str_pad($t['desc'], 30) . " | Expected: " . str_pad($t['expected_penalty'], 5) . " | Actual: " . str_pad($result['penalty'], 5) . " | BL: " . ($result['is_blacklisted']?'Y':'N') . " | " . ($pass ? "PASS" : "FAIL") . "\n";
}
