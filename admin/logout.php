<?php
require '../config.php';
unset($_SESSION['is_admin']);
header('Location: login.php');
exit;
