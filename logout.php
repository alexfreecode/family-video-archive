<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
delete_remember_token();
session_destroy();
header('Location: login.php');
exit;
