<?php
require_once __DIR__ . '/auth_check.php';
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/home.php');
    exit;
}
