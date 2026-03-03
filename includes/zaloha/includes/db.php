<?php
$host = "uvds59.active24.cz";
$user = "cmsvzpproj";       // tvůj DB uživatel
$pass = "U6BDK4VM";           // tvé heslo
$dbname = "cmsvzpproj";   // tvá databáze

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
