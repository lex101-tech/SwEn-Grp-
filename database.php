<?php
// ===== Connection settings for XAMPP on your own computer =====
// These are the XAMPP defaults. Change them if your setup is different.
// When the project is cloud-hosted, these values will change.
// $host = 'sql112.infinityfree.com';
// $username = 'if0_43519393';
// $password = 'Fredriqa7425';
// $dbname = 'if0_43519393_fstclubhub';

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'fst_club_hub';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // PDO throws exceptions when something goes wrong.
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    // Shows the error message. Replace the blank with the method that returns the message.
    die("Could not connect to the database: " . $e->getMessage());
}
?>