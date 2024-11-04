<?php
try {
    $host = "localhost";
    $dbname = "reconstruct";
    $username = "root";
    $password = "";

    $mysqli = new mysqli($host, $username, $password, $dbname);

    if ($mysqli->connect_errno) {
        throw new Exception("Connection error: " . $mysqli->connect_error);
    }

    return $mysqli;
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>
