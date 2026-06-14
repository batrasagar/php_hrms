<?php
$conn = new mysqli('srv2204.hstgr.io', 'u988681325_attnlog', '1707Sp%%', 'u988681325_attnlog', 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
echo "Connected OK\n";

$sql = file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^CREATE DATABASE.*?;\s*/im', '', $sql);
$sql = preg_replace('/^USE.*?;\s*/im', '', $sql);

$conn->multi_query($sql);
do {
    echo "OK\n";
} while ($conn->next_result());

if ($conn->errno) {
    echo "Error: " . $conn->error . "\n";
} else {
    echo "Schema applied successfully.\n";
}
$conn->close();
