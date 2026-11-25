<?php
// Meal prices are maintained date-wise from Mess Menu.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin([ROLE_MESS, ROLE_ADMIN]);
header('Location: menu.php');
exit;
