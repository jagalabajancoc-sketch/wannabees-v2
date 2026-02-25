<?php

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'wb_main';

date_default_timezone_set('Asia/Manila');

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_errno) {
    die("Database connection failed ({$mysqli->connect_errno}): {$mysqli->connect_error}");
}

$mysqli->query("SET time_zone = '+08:00'");

$mysqli->set_charset("utf8mb4");
