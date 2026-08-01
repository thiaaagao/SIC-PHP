<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

session_start();

if (Auth::isLoggedIn()) {
    if (Auth::mustChangePassword()) {
        header('Location: change_password.php');
    } elseif (Auth::canResolve()) {
        header('Location: support.php');
    } else {
        header('Location: open_ticket.php');
    }
} else {
    header('Location: login.php');
}
exit;
