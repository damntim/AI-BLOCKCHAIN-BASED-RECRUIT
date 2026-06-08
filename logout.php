<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';

session_destroy();
header('Location: /login.php');
exit;
