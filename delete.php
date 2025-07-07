<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$app_id = $_GET['id'];

// Get application details to delete image file
$app_query = "SELECT image_dir FROM applications WHERE id = ?";
$app_stmt = mysqli_prepare($conn, $app_query);
mysqli_stmt_bind_param($app_stmt, 'i', $app_id);
mysqli_stmt_execute($app_stmt);
$app_result = mysqli_stmt_get_result($app_stmt);
$application = mysqli_fetch_assoc($app_result);

// Delete application and related comments
mysqli_begin_transaction($conn);

try {
    // Delete comments