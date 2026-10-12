<?php

session_start();

/*
|--------------------------------------------------------------------------
| ADMIN ENTRY POINT
|--------------------------------------------------------------------------
|
| If admin is already logged in,
| send them directly to the dashboard.
|
*/

if (
    isset($_SESSION['admin_id']) ||
    isset($_SESSION['admin_logged_in'])
) {

    header('Location: dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ADMIN LOGIN
|--------------------------------------------------------------------------
|
| Change this filename if your actual
| admin login page has another name.
|
*/

header('Location: login.php');
exit;