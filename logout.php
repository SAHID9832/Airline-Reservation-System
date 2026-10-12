<?php
require 'config.php';
session_unset();
session_destroy();
session_start();
$_SESSION['flash'] = ['type' => 'success', 'msg' => 'You have been logged out.'];
header('Location: index.php');
exit;
