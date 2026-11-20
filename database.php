<?php
// ===== Connection settings for XAMPP on your own computer =====
// These are the XAMPP defaults. Change them if your setup is different.
// When the project is cloud-hosted, these values will change.
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'fst_club_hub';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // TODO 1: Make PDO throw exceptions when something goes wrong.
    // Replace the blank with PDO::ERRMODE_EXCEPTION
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    // TODO 2: Show the error message. Replace the blank with the method that returns the message.
    die("Could not connect to the database: " . $e->__________());
}
?>