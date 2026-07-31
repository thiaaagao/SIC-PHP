<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Auth.php';

session_start();
logAccess();
Auth::logout();
header('Location: login.php');
exit;
