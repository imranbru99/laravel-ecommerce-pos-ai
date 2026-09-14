<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Checkbox values (unchecked থাকলে POST এ আসবে না)
    $codEnabled = isset($_POST['codEnabled']) ? 1 : 0;
    $bkashEnabled = isset($_POST['bkashEnabled']) ? 1 : 0;
    
    $bkashAppKey = $_POST['bkashAppKey'] ?? "";
    $bkashAppSecret = $_POST['bkashAppSecret'] ?? "";
    $bkashUsername = $_POST['bkashUsername'] ?? "";
    $bkashPassword = $_POST['bkashPassword'] ?? "";
    $bkashBaseUrl = $_POST['bkashBaseUrl'] ?? "https://tokenized.sandbox.bka.sh/v1.2.0-beta";

    // SQL UPDATE Query এখানে হবে
    // $success = $db->query("UPDATE settings SET codEnabled = $codEnabled, bkashEnabled = $bkashEnabled, ...");

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}