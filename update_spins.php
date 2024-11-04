<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION["name"])) {
    die("Not logged in");
}

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $db->beginTransaction();

    // Update or insert today's spin count
    $stmt = $db->prepare("
        INSERT INTO daily_spins (user_name, spin_date, spin_count)
        VALUES (?, CURDATE(), 1)
        ON DUPLICATE KEY UPDATE spin_count = spin_count + 1
    ");
    
    $stmt->execute([$_SESSION["name"]]);
    
    // Update total spin count in user_stats table
    $stmt = $db->prepare("
        INSERT INTO user_stats (user_name, spin_count, last_spin_date)
        VALUES (?, 1, CURDATE())
        ON DUPLICATE KEY UPDATE 
        spin_count = spin_count + 1,
        last_spin_date = CURDATE()
    ");
    
    $stmt->execute([$_SESSION["name"]]);
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 
