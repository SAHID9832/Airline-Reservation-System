<?php

/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| HTML Escape
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {

    function h($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (!function_exists('is_logged_in')) {

    function is_logged_in()
    {
        return isset($_SESSION['passenger_id'])
            || isset($_SESSION['user_id']);
    }
}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

if (!function_exists('require_login')) {

    function require_login()
    {
        if (!is_logged_in()) {

            header(
                'Location: login.php'
            );

            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Current Passenger ID
|--------------------------------------------------------------------------
*/

if (!function_exists('current_passenger_id')) {

    function current_passenger_id()
    {
        return
            $_SESSION['passenger_id']
            ?? $_SESSION['user_id']
            ?? null;
    }
}


/*
|--------------------------------------------------------------------------
| Current Passenger
|--------------------------------------------------------------------------
*/

if (!function_exists('current_passenger')) {

    function current_passenger($pdo)
    {
        $id =
            current_passenger_id();

        if (!$id) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM passenger
            WHERE passenger_id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect')) {

    function redirect($url)
    {
        header(
            'Location: ' . $url
        );

        exit;
    }
}