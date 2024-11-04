<?php
session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["name"])) {
    header("Location: login.php");
    exit;
}

// Initialize variables
$user = null;
$spinsByDate = [];
$todaySpins = 0;
$error = null;

require_once 'database.php';
$mysqli = require __DIR__ . "/database.php";

try {
    // Get user data including stats
    $stmt = $mysqli->prepare("
        SELECT u.name, u.email, us.streak, us.spin_count
        FROM user u
        LEFT JOIN user_stats us ON u.name = us.user_name
        WHERE u.name = ?
    ");
    
    $stmt->bind_param("s", $_SESSION["name"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get spin history for the last 365 days
    $stmt = $mysqli->prepare("
        SELECT spin_date, spin_count
        FROM daily_spins
        WHERE user_name = ?
        AND spin_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
    ");
    
    $stmt->bind_param("s", $_SESSION["name"]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $spinsByDate = [];
    while ($row = $result->fetch_assoc()) {
        $spinsByDate[$row['spin_date']] = $row['spin_count'];
    }

    // Get today's spins
    $stmt = $mysqli->prepare("
        SELECT spin_count
        FROM daily_spins
        WHERE user_name = ? AND spin_date = CURDATE()
    ");
    
    $stmt->bind_param("s", $_SESSION["name"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $todaySpins = $result->fetch_assoc()['spin_count'] ?? 0;

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "An error occurred. Please try again later.";
}

// If user is not found, redirect to login
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Dashboard</title>
    <style>
        /* Base styles (Desktop/Laptop - above 1024px) */
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
            color: #24292e;
            font-family: Arial, sans-serif;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 80px auto 20px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f6f8fa;
            border-radius: 6px;
            border: 1px solid #e1e4e8;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover; /* Ensures the image covers the area properly */
            border: 2px solid #e1e4e8;
            background-color: #f6f8fa; /* Fallback background color */
        }

        .user-details h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }

        .user-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #586069;
        }

        .contribution-section {
            margin-top: 20px;
            background: #ffffff;
            border-radius: 6px;
            padding: 10px 5px;
            overflow: hidden; /* Prevent content overflow */
        }

        .contribution-header {
            font-size: 16px;
            font-weight: normal;
            margin-bottom: 10px;
            text-align: left;
        }

        .contribution-calendar {
            padding: 15px;
            width: 100%;
           
           
        }

        .month-labels {
            display: flex;
            padding-left: 45px;
            margin-bottom: 8px;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .month-label {
            font-size: 14px;
            color: #57606a;
            width: 80px;
            text-align: left;
            padding-left: 8px;
            flex-shrink: 0;
        }

        .calendar-body {
            display: flex;
            gap: 4px;
        }

        .weekday-labels {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding-right: 8px;
            background: #fff;
            position: sticky;
            left: 0;
            z-index: 1;
        }

        .weekday-label {
            font-size: 14px;
            height: 20px;
            line-height: 20px;
            width: 40px;
            flex-shrink: 0;
            text-align: right;
            color: #57606a;
        }

        .calendar-grid {
            display: flex;
            gap: 2px;
        }

        .week-column {
            display: flex;
            flex-direction: column;
            gap: 9px;
            width: 17px;
            flex-shrink: 0;
        }

        .day {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            transition: all 0.2s ease;
        }

        .day.empty {
            background-color: transparent;
            border: none;
        }

        .day.level-0 { background-color: #ebedf0; }
        .day.level-1 { background-color: #9be9a8; }
        .day.level-2 { background-color: #40c463; }
        .day.level-3 { background-color: #30a14e; }
        .day.level-4 { background-color: #216e39; }

        .contribution-legend {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            margin-top: 16px;
            font-size: 12px;
            color: #57606a;
        }

        .contribution-legend .contribution-box {
            width: 8px;
            height: 8px;
        }

        .stats-container {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1a73e8;
        }

        /* Hover effect */
        .contribution-box:hover {
            cursor: pointer;
            filter: brightness(1.3);
        }

        /* Tablet (768px to 1024px) */
        @media screen and (max-width: 1024px) {
            .contribution-calendar {
                padding: 12px;
                overflow-x: auto;
            }

            .month-label {
                width: 70px;
                font-size: 12px;
            }

            .weekday-label {
                width: 35px;
                font-size: 12px;
            }
            .week-column {
                width: 15px;
            }

            .day {
                width: 15px;
                height: 15px;
            }

            .dashboard-container {
                margin-top: 70px;
                padding: 15px;
            }

            .user-avatar {
                width: 70px;
                height: 70px;
            }

            .contribution-header {
                font-size: 15px;
            }
        }

        /* Mobile (below 768px) */
        @media screen and (max-width: 768px) {
            .contribution-calendar {
                padding: 8px;
                min-width: 300px;
                overflow-x: auto;
            }

            .month-label {
                width: 68px;
                font-size: 10px;
                padding-left: 2px;
            }
            .weekday-labels{
                gap: 1px;
            }
            .weekday-label {
                width: 25px;
                font-size: 10px;
            }

            .week-column {
                width: 12px;
            }

            .day {
                width: 12px;
                height: 12px;
            }

            .dashboard-container {
                margin-top: 90px;
                padding: 10px;
            }

            .user-avatar {
                width: 50px;
                height: 50px;
            }

            .contribution-legend {
                font-size: 9px;
                justify-content: center;
                gap: 4px;
                margin-top: 12px;
            }

            .stats-container {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
                padding: 0.5rem;
            }

            .stat-item {
                padding: 0.5rem;
            }

            .stat-label {
                font-size: 0.8rem;
            }

            .stat-value {
                font-size: 1.2rem;
            }

            .calendar-grid {
                padding-top: 6px;
                gap: 3px;
                padding-left: 8px;
                min-width: 280px; /* Ensure minimum width for readability */
            }

            .contribution-header {
                font-size: 14px;
                margin-bottom: 15px;
                text-align: center;
            }

            .weekdays {
                gap: 5px;
                font-size: 8px;
                margin-right: 4px;
                min-width: 20px;
            }

            .weekdays span {
                height: 8px;
                line-height: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header3.php'; ?>

    <div class="dashboard-container">
        <div class="user-info">
            <img src="images/fevicon.png" alt="User Profile" class="user-avatar">
            <div class="user-details">
                <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                <div class="user-stats">
                    <span class="stat-item"> <?php echo htmlspecialchars($user['email']); ?></span>
                </div>
            </div>
        </div>
        <!-- Contribution Section -->
        <div class="contribution-section">
            <h2 class="contribution-header">
                <?php 
                // Get spins for last 365 days
                $yearSpins = array_filter($spinsByDate, function($date) {
                    return strtotime($date) >= strtotime('365 days ago');
                }, ARRAY_FILTER_USE_KEY);
                echo number_format(array_sum($yearSpins)); 
                ?> spins in the last year
            </h2>

            <div class="contribution-calendar">
                <div class="calendar-header">
                    <?php
                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    echo '<div class="month-labels">';
                    foreach ($months as $month) {
                        echo "<div class='month-label'>$month</div>";
                    }
                    echo '</div>';
                    ?>
                </div>

                <div class="calendar-body">
                    <div class="weekday-labels">
                        <?php
                        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($weekdays as $day) {
                            echo "<div class='weekday-label'>$day</div>";
                        }
                        ?>
                    </div>

                    <div class="calendar-grid">
                        <?php
                        $year = date('Y');
                        
                        // Initialize the first week
                        $currentDate = new DateTime("$year-01-01");
                        $weekOffset = $currentDate->format('w'); // Get the day of week (0-6)
                        
                        // Create vertical columns for each week
                        for ($week = 0; $week < 53; $week++) {
                            echo "<div class='week-column'>";
                            
                            for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
                                $currentDate = new DateTime("$year-01-01 +$week weeks +$dayOfWeek days -$weekOffset days");
                                
                                if ($currentDate->format('Y') == $year) {
                                    $dateKey = $currentDate->format('Y-m-d');
                                    $spinCount = $spinsByDate[$dateKey] ?? 0;
                                    
                                    $level = 0;
                                    if ($spinCount > 0) {
                                        if ($spinCount <= 3) $level = 1;
                                        else if ($spinCount <= 5) $level = 2;
                                        else if ($spinCount <= 8) $level = 3;
                                        else $level = 4;
                                    }

                                    echo "<div class='day level-$level' 
                                              data-date='$dateKey' 
                                              data-count='$spinCount' 
                                              title='{$currentDate->format('F j, Y')}: $spinCount contributions'></div>";
                                } else {
                                    echo "<div class='day empty'></div>";
                                }
                            }
                            
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>

                <div class="contribution-legend">
                    <span>Less</span>
                    <?php for ($i = 0; $i <= 4; $i++): ?>
                        <div class='day level-<?php echo $i; ?>'></div>
                    <?php endfor; ?>
                    <span>More</span>
                </div>
            </div>
        </div>

        <!-- Stats Container -->
        <div class="stats-container">
            <div class="stat-item">
                <span class="stat-label">Current Streak</span>
                <span class="stat-value">🔥 <?php 
                    echo isset($user['streak']) ? htmlspecialchars($user['streak']) : '0'; 
                ?> days</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Today's Spins</span>
                <span class="stat-value">🎯 <?php echo htmlspecialchars($todaySpins); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Spins</span>
                <span class="stat-value">🎯 <?php 
                    echo isset($user['spin_count']) ? htmlspecialchars($user['spin_count']) : '0'; 
                ?></span>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <footer id="footer">
        <script>
            // Load the footer content dynamically
            fetch('footer.html')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('footer').innerHTML = html;
                })
                .catch(error => console.error('Error loading footer:', error));
        </script>
    </footer>
</body>
</html>

