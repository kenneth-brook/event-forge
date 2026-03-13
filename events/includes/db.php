<?php
declare(strict_types=1);

$host = '';
$username = '';
$password = '';
$dbname = '';

$connection = mysqli_connect($host, $username, $password, $dbname);

if (!$connection) {
    http_response_code(500);
    exit('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($connection, 'utf8mb4');