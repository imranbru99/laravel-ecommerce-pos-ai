<?php
session_start();

// Unset admin session variables
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);

header("Location: login");
exit;