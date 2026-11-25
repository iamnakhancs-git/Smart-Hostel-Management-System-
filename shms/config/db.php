<?php
// ============================================================
// Database Configuration
// Smart Hostel Management System (SHMS)
// ============================================================

define('DB_HOST',     getenv('SHMS_DB_HOST') ?: 'localhost');
define('DB_USER',     getenv('SHMS_DB_USER') ?: 'root');
define('DB_PASS',     getenv('SHMS_DB_PASS') ?: '');
define('DB_NAME',     getenv('SHMS_DB_NAME') ?: 'centralized_hostel_db');
define('DB_PORT',     (int)(getenv('SHMS_DB_PORT') ?: 3306));
define('DB_CHARSET',  'utf8mb4');

function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset(DB_CHARSET);
        $conn->query("SET time_zone = '+05:00'");
    }
    return $conn;
}
