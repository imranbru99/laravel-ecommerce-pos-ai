<?php
session_start();
require_once __DIR__ . '/../db.php';
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_GET['id'])) {
    $pdo->prepare("UPDATE Category SET status = NOT status WHERE id = ?")->execute([$_GET['id']]);
}