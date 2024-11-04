<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["name"])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db_host = "localhost";
$db_name = "reconstruct";
$db_user = "root";
$db_pass = "";

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $db->beginTransaction();
    
    // Get user's last spin date and current streak
    $stmt = $db->prepare("
        SELECT streak, last_spin_date 
        FROM user_stats 
        WHERE user_name = ?
    ");
    $stmt->execute([$_SESSION["name"]]);
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $today = date('Y-m-d');
    $lastSpinDate = $userStats['last_spin_date'] ?? null;
    $currentStreak = $userStats['streak'] ?? 0;
    
    // Calculate new streak with more detailed logging
    error_log("Before calculation - Last spin: $lastSpinDate, Current streak: $currentStreak");
    
    if (!$lastSpinDate) {
        // First time spinning
        $newStreak = 1;
        error_log("First time spinning - New streak: 1");
    } else {
        $daysDifference = (strtotime($today) - strtotime($lastSpinDate)) / (60 * 60 * 24);
        error_log("Days difference: $daysDifference");
        
        if ($daysDifference == 0) {
            // Already spun today, keep current streak
            $newStreak = $currentStreak;
            error_log("Same day spin - Keeping streak at: $newStreak");
        } elseif ($daysDifference == 1) {
            // Consecutive day, increment streak
            $newStreak = $currentStreak + 1;
            error_log("Consecutive day - Incrementing streak to: $newStreak");
        } else {
            // More than one day gap, reset streak to 1
            $newStreak = 1;
            error_log("Streak break - Resetting to 1. Days gap: $daysDifference");
        }
    }
    
    // Debug logging
    error_log("Streak Calculation: Last spin date: $lastSpinDate, Days difference: $daysDifference, Current streak: $currentStreak, New streak: $newStreak");
    
    // Update or insert into daily_spins
    $stmt = $db->prepare("
        INSERT INTO daily_spins (user_name, spin_date, spin_count)
        VALUES (?, CURDATE(), 1)
        ON DUPLICATE KEY UPDATE spin_count = spin_count + 1
    ");
    $stmt->execute([$_SESSION["name"]]);
    
    // Update user_stats with new streak and spin count
    $stmt = $db->prepare("
        INSERT INTO user_stats (user_name, streak, spin_count, last_spin_date)
        VALUES (?, ?, 1, CURDATE())
        ON DUPLICATE KEY UPDATE 
            streak = ?,
            spin_count = COALESCE(spin_count, 0) + 1,
            last_spin_date = CURDATE()
    ");
    $stmt->execute([$_SESSION["name"], $newStreak, $newStreak]);
    
    // Get updated counts for response
    $stmt = $db->prepare("
        SELECT us.spin_count as total_spins, 
               us.streak as current_streak,
               ds.spin_count as daily_spins 
        FROM user_stats us 
        LEFT JOIN daily_spins ds ON ds.user_name = us.user_name 
            AND ds.spin_date = CURDATE()
        WHERE us.user_name = ?
    ");
    $stmt->execute([$_SESSION["name"]]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'total_spins' => $result['total_spins'],
        'daily_spins' => $result['daily_spins'],
        'streak' => $result['current_streak'],
        'debug' => [
            'last_spin_date' => $lastSpinDate,
            'days_difference' => $daysDifference ?? null,
            'new_streak' => $newStreak
        ]
    ]);
    
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
;
