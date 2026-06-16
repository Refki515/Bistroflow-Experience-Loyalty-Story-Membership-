<?php
require_once __DIR__ . '/../includes/functions.php';

destroySession();
header('Location: ' . url('auth/login.php'));
exit;
