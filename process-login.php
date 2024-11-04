<?php

// After successful login validation
session_start();
$_SESSION["name"] = $user["name"];  // Store username instead of user_id
header("Location: index.php");
exit;
?> 