<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| AJAX
|--------------------------------------------------------------------------
*/

if (isset($_GET['ajax'])) {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {

        /*
        |--------------------------------------------------------------------------
        | AIRPORT SEARCH
        |--------------------------------------------------------------------------
        */

        if ($_GET['ajax'] === 'airport_search') {

            $q = trim($_GET['q'] ?? '');

            if ($q === '') {
                echo json_encode([
                    'success' => true,
                    'airports' => []
                ]);
                exit;
            }

            $like = '%' . $q . '%';

            $sql = "
                SELECT
                    airport_id,
                    airport_code,
                    airport_name,
                    city,
                    country,
                    icao_code
                FROM airport
                WHERE
                    airport_code LIKE :code
                    OR airport_name LIKE :name
                    OR city LIKE :city
                    OR country LIKE :country
                    OR keywords LIKE :keywords
                    OR icao_code LIKE :icao
                ORDER BY
                    CASE
                        WHEN UPPER(airport_code) = UPPER(:exact_code)
                            THEN 0
                        WHEN UPPER(city) = UPPER(:exact_city)
                            THEN 1
                        WHEN UPPER(city) LIKE UPPER(:city_prefix)
                            THEN 2
                        WHEN UPPER(airport_name) LIKE UPPER(:name_prefix)
                            THEN 3
                        ELSE 4
                    END,
                    airport_name ASC
                LIMIT 10
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':code' => $like,
                ':name' => $like,
                ':city' => $like,
                ':country' => $like,
                ':keywords' => $like,
                ':icao' => $like,
                ':exact_code' => $q,
                ':exact_city' => $q,
                ':city_prefix' => $q . '%',
                ':name_prefix' => $q . '%'
            ]);

            echo json_encode([
                'success' => true,
                'airports' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | FLIGHT CALENDAR
        |--------------------------------------------------------------------------
        */

        if ($_GET['ajax'] === 'flight_calendar') {

            $from = strtoupper(trim($_GET['from'] ?? ''));
            $to = strtoupper(trim($_GET['to'] ?? ''));

            $start = trim($_GET['start'] ?? '');
            $end = trim($_GET['end'] ?? '');

            if (
                $from === '' ||
                $to === '' ||
                $from === $to
            ) {
                echo json_encode([
                    'success' => false,
                    'dates' => [],
                    'message' => 'Valid airports are required.'
                ]);
                exit;
            }

            $sql = "
                SELECT
                    DATE(f.departure_time) AS flight_date,
                    MIN(f.fare) AS lowest_fare,
                    COUNT(*) AS flight_count
                FROM flight f
                INNER JOIN airport src
                    ON src.airport_id = f.source_airport_id
                INNER JOIN airport dst
                    ON dst.airport_id = f.destination_airport_id
                WHERE
                    UPPER(src.airport_code) = :from_code
                    AND UPPER(dst.airport_code) = :to_code
            ";

            $params = [
                ':from_code' => $from,
                ':to_code' => $to
            ];

            if (
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
            ) {

                $sql .= "
                    AND DATE(f.departure_time)
                    BETWEEN :start_date AND :end_date
                ";

                $params[':start_date'] = $start;
                $params[':end_date'] = $end;
            }

            $sql .= "
                GROUP BY DATE(f.departure_time)
                ORDER BY flight_date ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $dates = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

                $dates[$row['flight_date']] = [
                    'price' => (float)$row['lowest_fare'],
                    'flight_count' => (int)$row['flight_count']
                ];
            }

            echo json_encode([
                'success' => true,
                'from' => $from,
                'to' => $to,
                'dates' => $dates
            ]);

            exit;
        }


        echo json_encode([
            'success' => false,
            'message' => 'Unknown AJAX request.'
        ]);

        exit;

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'airports' => [],
            'dates' => []
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| NORMAL VALUES
|--------------------------------------------------------------------------
*/

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$departure = trim($_GET['departure'] ?? '');
$return = trim($_GET['return'] ?? '');

$tripType =
    ($_GET['trip_type'] ?? 'round') === 'oneway'
        ? 'oneway'
        : 'round';

$travellers = (int)($_GET['travellers'] ?? 1);

if ($travellers < 1) {
    $travellers = 1;
}

if ($travellers > 9) {
    $travellers = 9;
}

$travelClass = $_GET['class'] ?? 'Economy';

$userName =
    $_SESSION['user_name']
    ?? $_SESSION['name']
    ?? 'Traveler';

$isLoggedIn =
    isset($_SESSION['user_id'])
    || isset($_SESSION['passenger_id']);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    VektorSky — Smart Flight Booking
</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: Inter, Arial, sans-serif;
    background: #f4f7fb;
    color: #0d2346;
}

button,
input,
select {
    font: inherit;
}

button {
    cursor: pointer;
}


/* =========================================================
   NAVBAR
========================================================= */

.nav {
    min-height: 82px;
    background: #ffffff;
    border-bottom: 1px solid #e4eaf3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 7%;
    position: sticky;
    top: 0;
    z-index: 1000;
}

/* =========================================================
   VEKTORSKY LOGO
========================================================= */

.vektorsky-logo {

    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: #092b68;

    flex-shrink: 0;

    cursor: pointer;

}


/* =========================================================
   LOGO SYMBOL
========================================================= */

.vektorsky-logo-mark {

    width: 62px;

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

}


.vektorsky-logo-mark svg {

    width: 100%;

    height: 100%;

    display: block;

}


/* =========================================================
   LOGO TEXT
========================================================= */

.vektorsky-wordmark {

    display: flex;

    flex-direction: column;

    justify-content: center;

}


/* VEKTORSKY */

.vektorsky-name {

    display: flex;

    align-items: center;

    line-height: 1;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 25px;

    font-weight: 900;

    letter-spacing: 1px;

}


.vektor-text {

    color: #092b68;

}


.sky-text {

    color: #109ee8;

}


/* =========================================================
   TAGLINE
========================================================= */

.vektorsky-tagline {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    margin-top: 6px;

    color: #092b68;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 6px;

    font-weight: 800;

    letter-spacing: 1.6px;

    white-space: nowrap;

}


.tag-line {

    width: 18px;

    height: 2px;

    background: #1769e8;

    display: block;

}


/* =========================================================
   HOVER
========================================================= */

.vektorsky-logo:hover {

    text-decoration: none;

}


.vektorsky-logo-mark {

    transition:
        transform .25s ease;

}


.vektorsky-logo:hover
.vektorsky-logo-mark {

    transform: translateY(-2px);

}

.nav-links {
    display: flex;
    align-items: center;
    gap: 28px;
}

.nav-links a {
    color: #17284b;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
}

.nav-links a:hover {
    color: #1769e8;
}

.logout-btn {
    padding: 12px 20px;
    border-radius: 12px;
    color: white !important;
    background: #155fe8;
}


/* =========================================================
   HERO
========================================================= */

.hero {
    position: relative;
    min-height: 650px;
    padding: 65px 20px 120px;
    overflow: visible;
    background:
        radial-gradient(
            circle at 85% 10%,
            rgba(58,120,255,.45),
            transparent 25%
        ),
        linear-gradient(
            115deg,
            #111b3b,
            #17347f 55%,
            #164dc5
        );
}

.hero::after {
    content: "";
    position: absolute;
    width: 400px;
    height: 400px;
    right: -100px;
    top: -180px;
    border-radius: 50%;
    background: rgba(87,145,255,.2);
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    color: white;
}

.hero-title {
    margin: 0;
    font-size: clamp(48px, 7vw, 78px);
    line-height: 1;
    font-weight: 900;
    letter-spacing: -4px;
}

.hero-title span {
    color: #9fc7ff;
}

.hero-subtitle {
    max-width: 800px;
    margin: 25px auto 0;
    font-size: 18px;
    line-height: 1.6;
    color: #e6efff;
}


/* =========================================================
   SEARCH BOX
========================================================= */

.search-box {
    position: relative;
    z-index: 10;
    max-width: 1350px;
    margin: 185px auto 0;
    background: white;
    border-radius: 28px;
    padding: 28px;
    box-shadow:
        0 25px 65px rgba(8,25,65,.20);
}

.trip-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
}

.trip-btn {
    border: 0;
    padding: 12px 20px;
    border-radius: 12px;
    background: #edf4ff;
    color: #1759ce;
    font-weight: 800;
}

.trip-btn.active {
    color: white;
    background:
        linear-gradient(
            135deg,
            #1769e8,
            #3047e9
        );
}

.search-grid {
    display: grid;
    grid-template-columns:
        1.35fr
        1.35fr
        1fr
        1fr
        .9fr
        .9fr;
    gap: 12px;
    align-items: end;
}

.field {
    position: relative;
}

.field label {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 900;
    color: #46617f;
    text-transform: uppercase;
}

.field input,
.field select {
    width: 100%;
    min-height: 62px;
    border: 1px solid #d7e2f1;
    border-radius: 15px;
    background: white;
    padding: 0 16px;
    outline: none;
    color: #14294d;
    font-weight: 700;
}

.field input:focus,
.field select:focus {
    border-color: #2469e8;
    box-shadow:
        0 0 0 4px rgba(37,99,235,.08);
}

.airport-field {
    position: relative;
}

.swap-btn {
    position: absolute;
    z-index: 20;
    right: -23px;
    bottom: 10px;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 1px solid #d7e2f1;
    background: white;
    color: #1769e8;
    font-size: 20px;
    box-shadow:
        0 5px 15px rgba(0,0,0,.08);
}

.search-btn {
    min-height: 62px;
    border: 0;
    border-radius: 15px;
    color: white;
    font-weight: 900;
    background:
        linear-gradient(
            135deg,
            #1769e8,
            #3348e8
        );
    box-shadow:
        0 10px 25px rgba(37,99,235,.25);
}

.search-btn:hover {
    transform: translateY(-2px);
}


/* =========================================================
   AUTOCOMPLETE
========================================================= */

.autocomplete {
    position: absolute;
    top: calc(100% + 7px);
    left: 0;
    right: 0;
    z-index: 500;
    display: none;
    background: white;
    border: 1px solid #dce6f2;
    border-radius: 15px;
    overflow: hidden;
    box-shadow:
        0 15px 40px rgba(10,40,80,.18);
}

.autocomplete.open {
    display: block;
}

.airport-option {
    padding: 13px 15px;
    cursor: pointer;
    border-bottom: 1px solid #eef2f7;
}

.airport-option:last-child {
    border-bottom: 0;
}

.airport-option:hover {
    background: #f2f7ff;
}

.airport-main {
    display: flex;
    align-items: center;
    gap: 10px;
}

.airport-code {
    min-width: 46px;
    padding: 5px 7px;
    border-radius: 7px;
    background: #e8f1ff;
    color: #145dcc;
    text-align: center;
    font-size: 12px;
    font-weight: 900;
}

.airport-name {
    font-size: 13px;
    font-weight: 800;
    color: #14294d;
}

.airport-sub {
    margin-top: 4px;
    margin-left: 56px;
    color: #70809a;
    font-size: 11px;
}


/* =========================================================
   CALENDAR
========================================================= */

.calendar {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    top: calc(100% + 18px);
    z-index: 200;
    width: min(930px, 94vw);
    display: none;
    padding: 22px;
    border-radius: 22px;
    background: white;
    border: 1px solid #dce6f2;
    box-shadow:
        0 25px 60px rgba(10,35,70,.20);
}

.calendar.open {
    display: block;
}

.calendar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.calendar-route {
    font-weight: 900;
    color: #143566;
}

.calendar-nav {
    display: flex;
    gap: 8px;
}

.calendar-nav button {
    width: 38px;
    height: 38px;
    border: 1px solid #dce6f2;
    background: white;
    border-radius: 10px;
    color: #145dcc;
}

.months {
    display: block;
    width: 100%;
}

.single-month-container {
    display: block;
    width: 100%;
}

.month.single-month {
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
}

.month-title {
    display: flex;
    justify-content: space-between;
    margin-bottom: 13px;
    color: #123260;
    font-weight: 900;
}

.weekdays,
.days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}

.weekday {
    text-align: center;
    color: #7c8ba0;
    font-size: 10px;
    font-weight: 800;
    padding: 5px;
}

.day {
    min-height: 57px;
    border: 1px solid #edf1f6;
    border-radius: 9px;
    background: white;
    text-align: center;
    padding: 6px 2px;
    color: #14294d;
}

.day.empty {
    border: 0;
    background: transparent;
}

.day:not(.empty):not(.disabled):hover {
    border-color: #2469e8;
    background: #eef5ff;
}

.day.disabled {
    opacity: .35;
    cursor: not-allowed;
}

.day-number {
    display: block;
    font-weight: 800;
    font-size: 12px;
}

.day-price {
    display: block;
    margin-top: 4px;
    color: #0b8b53;
    font-size: 9px;
    font-weight: 900;
}


/* =========================================================
   WHY VEKTORSKY
========================================================= */

#why-us {
    position: relative;
    overflow: hidden;
    padding: 100px 25px 90px;
    background:
        linear-gradient(
            180deg,
            #eef6ff 0%,
            #f8fbff 50%,
            #edf6ff 100%
        );
    scroll-margin-top: 80px;
}

.why-watermark-map {
    position: absolute;
    inset: 0;
    opacity: .08;
    pointer-events: none;
    background-image:
        radial-gradient(
            circle,
            #2874e8 1px,
            transparent 1.5px
        );
    background-size: 13px 13px;
    mask-image:
        linear-gradient(
            to right,
            transparent,
            black 18%,
            black 82%,
            transparent
        );
    -webkit-mask-image:
        linear-gradient(
            to right,
            transparent,
            black 18%,
            black 82%,
            transparent
        );
}

.why-plane-left {
    position: absolute;
    left: -50px;
    top: 45px;
    font-size: 220px;
    opacity: .08;
    transform: rotate(-13deg);
}

.why-plane-right {
    position: absolute;
    right: -90px;
    top: 120px;
    font-size: 310px;
    opacity: .06;
    transform: rotate(15deg);
}

.why-cloud {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,.85);
    filter: blur(4px);
}

.why-cloud.one {
    width: 300px;
    height: 110px;
    left: -70px;
    bottom: 120px;
}

.why-cloud.two {
    width: 350px;
    height: 130px;
    right: -90px;
    bottom: 180px;
}

.why-content {
    position: relative;
    z-index: 2;
    max-width: 1350px;
    margin: auto;
}

.why-title {
    text-align: center;
    max-width: 950px;
    margin: auto;
}

.why-title h2 {
    margin: 0;
    font-size: clamp(45px, 5vw, 72px);
    line-height: 1;
    letter-spacing: -3px;
    font-weight: 900;
    color: #0b1f44;
}

.why-title h2 span {
    color: #1769e8;
}

.why-tagline {
    margin-top: 18px;
    color: #1769e8;
    font-size: clamp(20px, 2.5vw, 31px);
    font-weight: 600;
    font-style: italic;
}

.why-flight-path-top {
    width: 300px;
    height: 35px;
    margin: 0 auto 10px;
    border-bottom: 2px dashed rgba(37,99,235,.35);
    border-radius: 50%;
    position: relative;
}

.why-flight-path-top::after {
    content: "✈";
    position: absolute;
    right: -5px;
    bottom: -11px;
    color: #1769e8;
    font-size: 20px;
}

.why-description {
    max-width: 920px;
    margin: 25px auto 0;
    color: #243b64;
    font-size: 17px;
    line-height: 1.75;
}

.why-description strong {
    color: #1260dc;
}

.why-feature-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    margin-top: 45px;
}

.why-feature-card {
    position: relative;
    min-height: 315px;
    padding: 25px 16px 30px;
    text-align: center;
    border-radius: 23px;
    border: 1px solid rgba(37,99,235,.14);
    background: rgba(255,255,255,.84);
    backdrop-filter: blur(10px);
    box-shadow:
        0 15px 40px rgba(15,48,90,.09);
    transition:
        transform .25s ease,
        box-shadow .25s ease;
}

.why-feature-card:hover {
    transform: translateY(-10px);
    box-shadow:
        0 25px 55px rgba(37,99,235,.17);
}

.why-feature-icon {
    width: 105px;
    height: 105px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 30px;
    background:
        linear-gradient(
            145deg,
            #e5f2ff,
            #ffffff
        );
    box-shadow:
        0 10px 25px rgba(37,99,235,.10);
    font-size: 55px;
}

.why-feature-card h3 {
    margin: 0 0 10px;
    color: #063dc4;
    font-size: 18px;
    font-weight: 900;
}

.why-feature-card p {
    margin: 0;
    color: #344767;
    font-size: 13px;
    line-height: 1.65;
}

.why-card-path {
    position: absolute;
    left: 17px;
    right: 17px;
    bottom: 14px;
    height: 22px;
    border-bottom: 2px dashed rgba(37,99,235,.28);
    border-radius: 50%;
}

.why-card-path::after {
    content: "✈";
    position: absolute;
    right: 0;
    bottom: -10px;
    color: #1769e8;
    font-size: 17px;
}

.why-bottom-banner {
    max-width: 1050px;
    min-height: 145px;
    margin: 30px auto 0;
    padding: 20px 35px;
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    align-items: center;
    gap: 25px;
    border-radius: 28px;
    border: 1px solid rgba(37,99,235,.14);
    background:
        linear-gradient(
            110deg,
            rgba(225,241,255,.96),
            rgba(250,253,255,.98)
        );
    box-shadow:
        0 18px 50px rgba(16,45,90,.11);
}

.why-bottom-airplane {
    text-align: center;
    font-size: 85px;
    transform: rotate(-5deg);
}

.why-bottom-text {
    padding: 10px 25px;
    border-left: 2px solid rgba(37,99,235,.20);
    border-right: 2px solid rgba(37,99,235,.20);
}

.why-bottom-text h3 {
    margin: 0;
    color: #0b1f44;
    font-size: 23px;
    line-height: 1.4;
}

.why-bottom-text strong {
    color: #1769e8;
}

.why-lets-fly {
    text-align: center;
    color: #1769e8;
    font-size: 32px;
    font-weight: 700;
    font-style: italic;
    transform: rotate(-4deg);
}

.why-lets-fly-path {
    width: 120px;
    height: 35px;
    margin: auto;
    border-bottom: 2px dashed #1769e8;
    border-radius: 50%;
}


/* =========================================================
   INFO SECTION
========================================================= */

.info-section {
    padding: 80px 25px;
    background: white;
}

.info-container {
    max-width: 1100px;
    margin: auto;
    text-align: center;
}

.info-container h2 {
    font-size: 42px;
    margin-bottom: 20px;
    color: #0b1f44;
}

.info-container p {
    max-width: 800px;
    margin: auto;
    line-height: 1.8;
    color: #566983;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    padding: 35px 25px;
    text-align: center;
    background: #0c1c3d;
    color: #dbe7ff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .search-grid {
        grid-template-columns:
            repeat(3, 1fr);
    }

    .why-feature-grid {
        grid-template-columns:
            repeat(3, 1fr);
    }
}

@media (max-width: 800px) {

    .nav {
        padding: 0 20px;
    }

    .nav-links {
        gap: 12px;
    }

    .nav-links a {
        font-size: 11px;
    }

    .hero {
        min-height: 850px;
    }

    .search-box {
        margin-top: 120px;
    }

    .search-grid {
        grid-template-columns: 1fr;
    }

    .swap-btn {
    right: 10px;
    bottom: -22px;
}

.why-feature-grid {

    .why-feature-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .why-bottom-banner {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .why-bottom-text {
        border-left: 0;
        border-right: 0;
        border-top: 1px solid #dce7f5;
        border-bottom: 1px solid #dce7f5;
    }
}

@media (max-width: 520px) {

    .nav {

        min-height: 70px;

        padding: 0 15px;

    }


    .vektorsky-logo {

        gap: 6px;

    }


    .vektorsky-logo-mark {

        width: 48px;

        height: 43px;

    }


    .vektorsky-name {

        font-size: 18px;

        letter-spacing: .5px;

    }


    .vektorsky-tagline {

        font-size: 5px;

        letter-spacing: 1px;

        gap: 4px;

        margin-top: 4px;

    }


    .tag-line {

        width: 12px;

        height: 1.5px;

    }


    .nav-links {

        gap: 8px;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="nav">

    <!-- VEKTORSKY LOGO -->

    <a href="index.php" class="vektorsky-logo">

        <div class="vektorsky-logo-mark">

            <svg
                viewBox="0 0 220 170"
                xmlns="http://www.w3.org/2000/svg"
                aria-label="VektorSky"
            >

                <defs>

                    <!-- Dark blue V -->
                    <linearGradient
                        id="logoVBlue"
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="100%"
                    >
                        <stop
                            offset="0%"
                            stop-color="#092b68"
                        />

                        <stop
                            offset="100%"
                            stop-color="#1554df"
                        />
                    </linearGradient>


                    <!-- Blue flight path -->
                    <linearGradient
                        id="logoSkyBlue"
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="0%"
                    >
                        <stop
                            offset="0%"
                            stop-color="#0879e8"
                        />

                        <stop
                            offset="100%"
                            stop-color="#18a9ef"
                        />
                    </linearGradient>

                </defs>


                <!-- V SYMBOL -->

                <path
                    d="
                        M 42 18
                        L 78 18
                        L 111 91
                        L 145 18
                        L 180 18
                        L 128 136
                        Q 112 165 94 136
                        Z
                    "
                    fill="url(#logoVBlue)"
                />


                <!-- BLUE AIRLINE SWOOSH -->

                <path
                    d="
                        M 28 83
                        C 38 112,
                          67 119,
                          105 101
                        C 132 88,
                          157 67,
                          183 45
                    "
                    fill="none"
                    stroke="url(#logoSkyBlue)"
                    stroke-width="17"
                    stroke-linecap="round"
                />


                <!-- WHITE INNER SWOOSH -->

                <path
                    d="
                        M 32 83
                        C 47 99,
                          69 103,
                          96 94
                        C 124 85,
                          151 64,
                          177 45
                    "
                    fill="none"
                    stroke="white"
                    stroke-width="7"
                    stroke-linecap="round"
                />


                <!-- AIRPLANE -->

                <g
                    transform="
                        translate(164 19)
                        rotate(-18)
                    "
                    fill="#092b68"
                >

                    <!-- Body -->

                    <path
                        d="
                            M 0 15
                            L 43 15
                            L 49 20
                            L 43 25
                            L 0 25
                            Z
                        "
                    />

                    <!-- Top wing -->

                    <path
                        d="
                            M 18 16
                            L 28 -2
                            L 35 -2
                            L 31 17
                            Z
                        "
                    />

                    <!-- Bottom wing -->

                    <path
                        d="
                            M 19 23
                            L 29 41
                            L 35 41
                            L 31 22
                            Z
                        "
                    />

                    <!-- Tail -->

                    <path
                        d="
                            M 5 16
                            L -4 5
                            L 2 5
                            L 12 16
                            Z
                        "
                    />

                </g>

            </svg>

        </div>


        <!-- TEXT -->

        <div class="vektorsky-wordmark">

            <div class="vektorsky-name">

                <span class="vektor-text">
                    VEKTOR
                </span>

                <span class="sky-text">
                    SKY
                </span>

            </div>


            <div class="vektorsky-tagline">

                <span class="tag-line"></span>

                <span>
                    FLY MORE, PAY LESS
                </span>

                <span class="tag-line"></span>

            </div>

        </div>

    </a>


    <!-- NAVIGATION -->

    <nav class="nav-links">

        <a href="index.php">
            ✈ Flights
        </a>


        <a href="my_bookings.php">
            🎫 My Bookings
        </a>


        <a href="#why-us">
            ✈ Why VektorSky
        </a>


        <?php if ($isLoggedIn): ?>

            <a
                href="logout.php"
                class="logout-btn"
            >
                Logout
            </a>

        <?php else: ?>

            <a
                href="login.php"
                class="logout-btn"
            >
                Login
            </a>

        <?php endif; ?>

    </nav>

</header>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="hero-content">

        <h1 class="hero-title">

            Your journey starts
            <span>here.</span>

        </h1>

        <p class="hero-subtitle">

            Search thousands of airports, discover available
            fares, compare flight options, select your seat,
            and book your next journey with VektorSky.

        </p>

    </div>


    <!-- SEARCH -->

    <div class="search-box">

        <div class="trip-buttons">

            <button
                type="button"
                class="trip-btn active"
                id="roundTripBtn"
            >
                ✈ Round Trip
            </button>

            <button
                type="button"
                class="trip-btn"
                id="oneWayBtn"
            >
                One Way
            </button>

        </div>


        <form
            id="flightSearchForm"
            method="GET"
            action="search_results.php"
        >

            <input
                type="hidden"
                name="trip_type"
                id="tripType"
                value="round"
            >


            <div class="search-grid">


                <!-- FROM -->

                <div class="field airport-field">

                    <label>
                        From
                    </label>

                    <input
                        type="text"
                        id="fromAirport"
                        name="from"
                        autocomplete="off"
                        placeholder="City or airport"
                        value="<?= htmlspecialchars($from) ?>"
                    >

                    <div
                        class="autocomplete"
                        id="fromSuggestions"
                    ></div>

                    <button
                        type="button"
                        class="swap-btn"
                        id="swapBtn"
                        title="Swap airports"
                    >
                        ⇄
                    </button>

                </div>


                <!-- TO -->

                <div class="field airport-field">

                    <label>
                        To
                    </label>

                    <input
                        type="text"
                        id="toAirport"
                        name="to"
                        autocomplete="off"
                        placeholder="City or airport"
                        value="<?= htmlspecialchars($to) ?>"
                    >

                    <div
                        class="autocomplete"
                        id="toSuggestions"
                    ></div>

                </div>


                <!-- DEPARTURE -->

                <div class="field">

                    <label>
                        Departure
                    </label>

                    <input
                        type="text"
                        id="departurePicker"
                        readonly
                        placeholder="Select date"
                    >

                    <input
                        type="hidden"
                        name="departure"
                        id="departureDate"
                        value="<?= htmlspecialchars($departure) ?>"
                    >

                </div>


                <!-- RETURN -->

                <div class="field">

                    <label>
                        Return
                    </label>

                    <input
                        type="text"
                        id="returnPicker"
                        readonly
                        placeholder="Select return date"
                    >

                    <input
                        type="hidden"
                        name="return"
                        id="returnDate"
                        value="<?= htmlspecialchars($return) ?>"
                    >

                </div>


                <!-- TRAVELLERS -->

                <div class="field">

                    <label>
                        Travellers
                    </label>

                    <select
                        name="travellers"
                        id="travellers"
                    >

                        <?php for ($i = 1; $i <= 9; $i++): ?>

                            <option
                                value="<?= $i ?>"
                                <?= $travellers === $i ? 'selected' : '' ?>
                            >
                                <?= $i ?>
                                Traveller<?= $i > 1 ? 's' : '' ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <!-- CLASS -->

                <div class="field">

                    <label>
                        Class
                    </label>

                    <select
                        name="class"
                        id="travelClass"
                    >

                        <option
                            value="Economy"
                            <?= $travelClass === 'Economy' ? 'selected' : '' ?>
                        >
                            Economy
                        </option>

                        <option
                            value="Business"
                            <?= $travelClass === 'Business' ? 'selected' : '' ?>
                        >
                            Business
                        </option>

                        <option
                            value="First"
                            <?= $travelClass === 'First' ? 'selected' : '' ?>
                        >
                            First
                        </option>

                    </select>

                </div>


            </div>


            <div style="margin-top:18px;">

                <button
                    type="submit"
                    class="search-btn"
                    style="width:220px;"
                >
                    🔎 Search Flights
                </button>

            </div>


        </form>


        <!-- CALENDAR -->

        <div
            class="calendar"
            id="calendar"
        >

            <div class="calendar-head">

                <div
                    class="calendar-route"
                    id="calendarRoute"
                >
                    Select airports first
                </div>

                <div class="calendar-nav">

                    <button
                        type="button"
                        id="previousMonth"
                    >
                        ‹
                    </button>

                    <button
                        type="button"
                        id="nextMonth"
                    >
                        ›
                    </button>

                </div>

            </div>

            <div id="calendarBody"></div>

        </div>

    </div>

</section>


<!-- =========================================================
     WHY VEKTORSKY
========================================================= -->

<section id="why-us">

    <div class="why-watermark-map"></div>

    <div class="why-plane-left">
        ✈
    </div>

    <div class="why-plane-right">
        ✈
    </div>

    <div class="why-cloud one"></div>
    <div class="why-cloud two"></div>


    <div class="why-content">


        <div class="why-title">

            <h2>
                Why <span>VektorSky</span>
            </h2>

            <div class="why-tagline">
                Fly Smart, Book Easy, Travel Happy! ✈
            </div>

            <div class="why-flight-path-top"></div>

            <p class="why-description">

                <strong>VektorSky</strong>
                is an Airline Reservation System designed to make
                flight searching and booking simple, fast, and convenient.

                Users can search flights by airport and date,
                compare different flight options, view departure
                and arrival times, check fares, select seats,
                and complete their booking.

                The system also manages passengers, airports,
                aircraft, seats, flights, bookings, payments,
                and seat availability through a structured
                relational database.

                It supports one-way and round-trip searches
                and provides different flight options including
                non-stop and connecting flights.

            </p>

        </div>


        <div class="why-feature-grid">


            <div class="why-feature-card">

                <div class="why-feature-icon">
                    🔎✈️
                </div>

                <h3>
                    Easy Flight Search
                </h3>

                <p>
                    Search flights by airport,
                    city, date, and travel type
                    in just a few clicks.
                </p>

                <div class="why-card-path"></div>

            </div>


            <div class="why-feature-card">

                <div class="why-feature-icon">
                    🎟️✈️
                </div>

                <h3>
                    Compare &amp; Choose
                </h3>

                <p>
                    Compare different flight options,
                    timings, stops, and fares to find
                    the best journey for you.
                </p>

                <div class="why-card-path"></div>

            </div>


            <div class="why-feature-card">

                <div class="why-feature-icon">
                    💺
                </div>

                <h3>
                    Select Your Seat
                </h3>

                <p>
                    Choose your preferred seat and check
                    which seats are already booked before
                    confirming your journey.
                </p>

                <div class="why-card-path"></div>

            </div>


            <div class="why-feature-card">

                <div class="why-feature-icon">
                    🧳✈️
                </div>

                <h3>
                    Smooth Booking
                </h3>

                <p>
                    Complete your flight booking quickly
                    with passenger, seat, and payment
                    details connected together.
                </p>

                <div class="why-card-path"></div>

            </div>


            <div class="why-feature-card">

                <div class="why-feature-icon">
                    🛡️
                </div>

                <h3>
                    Secure &amp; Reliable
                </h3>

                <p>
                    Passenger, booking, seat, and payment
                    information is managed through a
                    structured database.
                </p>

                <div class="why-card-path"></div>

            </div>


            <div class="why-feature-card">

                <div class="why-feature-icon">
                    🎧
                </div>

                <h3>
                    24/7 Support
                </h3>

                <p>
                    VektorSky is designed to provide a
                    simple and convenient experience
                    throughout your journey.
                </p>

                <div class="why-card-path"></div>

            </div>


        </div>


        <div class="why-bottom-banner">

            <div class="why-bottom-airplane">
                ✈️
            </div>

            <div class="why-bottom-text">

                <h3>

                    At <strong>VektorSky</strong>,
                    we don't just book tickets,

                    <br>

                    we create
                    <strong>journeys</strong>
                    and
                    <strong>memories.</strong>

                </h3>

            </div>

            <div>

                <div class="why-lets-fly">
                    Let's Fly ✈
                </div>

                <div class="why-lets-fly-path"></div>

            </div>

        </div>


    </div>

</section>


<!-- =========================================================
     INFO
========================================================= -->

<section class="info-section">

    <div class="info-container">

        <h2>
            Your Complete Flight Journey
        </h2>

        <p>

            VektorSky connects flight search,
            airport selection, fare comparison,
            seat selection, booking, and payment
            into one simple airline reservation
            experience.

            From choosing your destination to
            selecting your seat, every step is
            connected to the airline database.

        </p>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    © 2026 VektorSky —
    Airline Reservation System
    (PHP + MySQL)

</footer>

<script>

(function () {

    'use strict';

    /* =========================================================
       ELEMENTS
    ========================================================= */

    const form =
        document.getElementById('flightSearchForm');

    const fromAirport =
        document.getElementById('fromAirport');

    const toAirport =
        document.getElementById('toAirport');

    const fromSuggestions =
        document.getElementById('fromSuggestions');

    const toSuggestions =
        document.getElementById('toSuggestions');

    const swapBtn =
        document.getElementById('swapBtn');

    const roundTripBtn =
        document.getElementById('roundTripBtn');

    const oneWayBtn =
        document.getElementById('oneWayBtn');

    const tripType =
        document.getElementById('tripType');

    const departurePicker =
        document.getElementById('departurePicker');

    const returnPicker =
        document.getElementById('returnPicker');

    const departureDate =
        document.getElementById('departureDate');

    const returnDate =
        document.getElementById('returnDate');

    const calendar =
        document.getElementById('calendar');

    const calendarBody =
        document.getElementById('calendarBody');

    const calendarRoute =
        document.getElementById('calendarRoute');

    const previousMonth =
        document.getElementById('previousMonth');

    const nextMonth =
        document.getElementById('nextMonth');


    /* =========================================================
       CALENDAR STATE
    ========================================================= */

    let calendarMode = 'departure';

    /*
     * IMPORTANT:
     * Only ONE month is displayed.
     */
    let calendarMonth = new Date();

    /*
     * Prevent showing months before the current month.
     */
    const today = new Date();

    const minimumCalendarMonth =
        new Date(
            today.getFullYear(),
            today.getMonth(),
            1
        );

    let calendarDates = {};

    let autocompleteTimer = null;


    /* =========================================================
       HELPERS
    ========================================================= */

    function escapeHtml(value) {

        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    }


    /*
     * Get airport code from selected dataset first.
     *
     * Example:
     * BLR
     * BOM
     * DEL
     */
    function airportCode(value) {

        if (!value) {
            return '';
        }

        const match =
            String(value).match(
                /\(([A-Z]{3})\)/
            );

        if (match) {
            return match[1].toUpperCase();
        }

        if (
            /^[A-Z]{3}$/i.test(
                String(value).trim()
            )
        ) {
            return String(value)
                .trim()
                .toUpperCase();
        }

        return '';

    }


    /*
     * Get actual selected airport code.
     */
    function getAirportCode(input) {

        return (
            input.dataset.code ||
            airportCode(input.value)
        ).trim().toUpperCase();

    }


    function todayString() {

        const d = new Date();

        const year =
            d.getFullYear();

        const month =
            String(
                d.getMonth() + 1
            ).padStart(2, '0');

        const day =
            String(
                d.getDate()
            ).padStart(2, '0');

        return (
            year +
            '-' +
            month +
            '-' +
            day
        );

    }


    function monthString(date) {

        return (
            date.getFullYear() +
            '-' +
            String(
                date.getMonth() + 1
            ).padStart(2, '0')
        );

    }


    function displayDate(value) {

        if (!value) {
            return '';
        }

        const date =
            new Date(
                value + 'T00:00:00'
            );

        return date.toLocaleDateString(
            'en-IN',
            {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }
        );

    }


    function formatPrice(value) {

        return '₹' +
            Number(value).toLocaleString(
                'en-IN'
            );

    }


    function isBeforeMinimumMonth(date) {

        return (
            date.getFullYear() <
                minimumCalendarMonth.getFullYear()
            ||
            (
                date.getFullYear() ===
                    minimumCalendarMonth.getFullYear()
                &&
                date.getMonth() <
                    minimumCalendarMonth.getMonth()
            )
        );

    }


    /* =========================================================
       AUTOCOMPLETE
    ========================================================= */

    function setupAutocomplete(
        input,
        dropdown
    ) {

        input.addEventListener(
            'input',
            function () {

                /*
                 * User typed manually.
                 * Remove old selected code.
                 */
                delete input.dataset.code;

                clearTimeout(
                    autocompleteTimer
                );

                const q =
                    input.value.trim();

                if (q.length < 2) {

                    dropdown.innerHTML = '';

                    dropdown.classList.remove(
                        'open'
                    );

                    return;

                }


                autocompleteTimer =
                    setTimeout(
                        async function () {

                            try {

                                const response =
                                    await fetch(
                                        'index.php?ajax=airport_search&q='
                                        +
                                        encodeURIComponent(q)
                                    );

                                const data =
                                    await response.json();

                                dropdown.innerHTML = '';


                                if (
                                    !data.success ||
                                    !data.airports ||
                                    data.airports.length === 0
                                ) {

                                    dropdown.innerHTML =
                                        '<div class="airport-option">'
                                        +
                                        '<div class="airport-name">'
                                        +
                                        'No airport found'
                                        +
                                        '</div>'
                                        +
                                        '</div>';

                                    dropdown.classList.add(
                                        'open'
                                    );

                                    return;

                                }


                                data.airports.forEach(
                                    function (airport) {

                                        const item =
                                            document.createElement(
                                                'div'
                                            );

                                        item.className =
                                            'airport-option';


                                        item.innerHTML =

                                            '<div class="airport-main">'
                                            +

                                            '<span class="airport-code">'
                                            +
                                            escapeHtml(
                                                airport.airport_code
                                            )
                                            +
                                            '</span>'

                                            +

                                            '<span class="airport-name">'
                                            +
                                            escapeHtml(
                                                airport.airport_name
                                            )
                                            +
                                            '</span>'

                                            +

                                            '</div>'

                                            +

                                            '<div class="airport-sub">'
                                            +
                                            escapeHtml(
                                                airport.city
                                            )
                                            +
                                            ', '
                                            +
                                            escapeHtml(
                                                airport.country
                                            )
                                            +
                                            '</div>';


                                        item.addEventListener(
                                            'click',
                                            function () {

                                                input.value =
                                                    airport.airport_code;

                                                input.dataset.code =
                                                    airport.airport_code;

                                                input.dataset.city =
                                                    airport.city;

                                                input.dataset.name =
                                                    airport.airport_name;

                                                dropdown.innerHTML = '';

                                                dropdown.classList.remove(
                                                    'open'
                                                );

                                                /*
                                                 * Reset calendar month
                                                 * when airport changes.
                                                 */
                                                calendarMonth =
                                                    new Date();

                                                if (
                                                    calendarMonth <
                                                    minimumCalendarMonth
                                                ) {
                                                    calendarMonth =
                                                        new Date(
                                                            minimumCalendarMonth
                                                        );
                                                }

                                                loadCalendarDates();

                                            }
                                        );


                                        dropdown.appendChild(
                                            item
                                        );

                                    }
                                );


                                dropdown.classList.add(
                                    'open'
                                );

                            } catch (error) {

                                console.error(
                                    'Airport search failed:',
                                    error
                                );

                            }

                        },
                        250
                    );

            }
        );


        input.addEventListener(
            'focus',
            function () {

                if (
                    input.value.trim().length >= 2
                ) {

                    input.dispatchEvent(
                        new Event('input')
                    );

                }

            }
        );

    }


    setupAutocomplete(
        fromAirport,
        fromSuggestions
    );


    setupAutocomplete(
        toAirport,
        toSuggestions
    );


    /* =========================================================
       SWAP AIRPORTS
    ========================================================= */

    swapBtn.addEventListener(
        'click',
        function () {

            const fromValue =
                fromAirport.value;

            const toValue =
                toAirport.value;


            const fromCode =
                fromAirport.dataset.code || '';

            const toCode =
                toAirport.dataset.code || '';


            fromAirport.value =
                toValue;

            toAirport.value =
                fromValue;


            fromAirport.dataset.code =
                toCode;

            toAirport.dataset.code =
                fromCode;


            /*
             * Clear selected dates because route changed.
             */
            departureDate.value = '';
            returnDate.value = '';

            departurePicker.value = '';
            returnPicker.value = '';


            calendarMonth =
                new Date();

            loadCalendarDates();

        }
    );


    /* =========================================================
       TRIP TYPE
    ========================================================= */

    function setTripType(type) {

        tripType.value =
            type;


        if (type === 'oneway') {

            oneWayBtn.classList.add(
                'active'
            );

            roundTripBtn.classList.remove(
                'active'
            );


            returnPicker.disabled =
                true;

            returnPicker.style.opacity =
                '.5';


            returnPicker.value =
                '';

            returnDate.value =
                '';

        } else {

            roundTripBtn.classList.add(
                'active'
            );

            oneWayBtn.classList.remove(
                'active'
            );


            returnPicker.disabled =
                false;

            returnPicker.style.opacity =
                '1';

        }

    }


    roundTripBtn.addEventListener(
        'click',
        function () {

            setTripType(
                'round'
            );

        }
    );


    oneWayBtn.addEventListener(
        'click',
        function () {

            setTripType(
                'oneway'
            );

        }
    );


    setTripType(
        '<?= $tripType ?>'
    );


    /* =========================================================
   LOAD FLIGHT CALENDAR DATA
   ========================================================= */

async function loadCalendarDates() {

    /*
    |--------------------------------------------------------------------------
    | Get airport codes
    |--------------------------------------------------------------------------
    */

    const fromCode =
        fromAirport.dataset.code ||
        airportCode(
            fromAirport.value
        );

    const toCode =
        toAirport.dataset.code ||
        airportCode(
            toAirport.value
        );


    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if (
        !fromCode ||
        !toCode
    ) {

        calendarDates = {};

        return;

    }


    if (
        fromCode ===
        toCode
    ) {

        calendarDates = {};

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Current calendar month
    |--------------------------------------------------------------------------
    */

    const year =
        calendarMonth.getFullYear();

    const month =
        calendarMonth.getMonth();


    /*
    |--------------------------------------------------------------------------
    | First day
    |--------------------------------------------------------------------------
    */

    const firstDay =
        new Date(
            year,
            month,
            1
        );


    /*
    |--------------------------------------------------------------------------
    | Last day
    |--------------------------------------------------------------------------
    */

    const lastDay =
        new Date(
            year,
            month + 1,
            0
        );


    /*
    |--------------------------------------------------------------------------
    | Date formatter
    |--------------------------------------------------------------------------
    */

    function formatDate(date) {

        return (

            date.getFullYear()

            +

            '-'

            +

            String(
                date.getMonth() + 1
            ).padStart(
                2,
                '0'
            )

            +

            '-'

            +

            String(
                date.getDate()
            ).padStart(
                2,
                '0'
            )

        );

    }


    const start =
        formatDate(
            firstDay
        );


    const end =
        formatDate(
            lastDay
        );


    /*
    |--------------------------------------------------------------------------
    | Existing PHP endpoint
    |--------------------------------------------------------------------------
    */

    const url =
        'index.php'
        +
        '?ajax=flight_calendar'
        +
        '&from='
        +
        encodeURIComponent(
            fromCode
        )
        +
        '&to='
        +
        encodeURIComponent(
            toCode
        )
        +
        '&start='
        +
        encodeURIComponent(
            start
        )
        +
        '&end='
        +
        encodeURIComponent(
            end
        );


    console.log(
        'Loading flight calendar:',
        url
    );


    try {

        const response =
            await fetch(
                url,
                {
                    method: 'GET',
                    cache: 'no-store'
                }
            );


        if (
            !response.ok
        ) {

            throw new Error(
                'HTTP ' +
                response.status
            );

        }


        const data =
            await response.json();


        console.log(
            'Flight calendar response:',
            data
        );


        /*
        |--------------------------------------------------------------------------
        | Clear previous month
        |--------------------------------------------------------------------------
        */

        calendarDates = {};


        if (
            !data.success
        ) {

            console.warn(
                data.message ||
                'No flight data found.'
            );

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | Convert API response
        |--------------------------------------------------------------------------
        |
        | Your PHP endpoint returns:
        |
        | dates: {
        |     "2026-08-26": {
        |         price: 4250,
        |         flight_count: 1
        |     }
        | }
        |
        |--------------------------------------------------------------------------
        */

        if (
            data.dates &&
            typeof data.dates ===
                'object'
        ) {

            Object.keys(
                data.dates
            ).forEach(
                function(date) {

                    const item =
                        data.dates[
                            date
                        ];


                    calendarDates[
                        date
                    ] = {

                        lowest_fare:
                            Number(
                                item.price ||
                                item.lowest_fare ||
                                0
                            ),

                        flight_count:
                            Number(
                                item.flight_count ||
                                0
                            )

                    };

                }
            );

        }


        console.log(
            'Calendar dates loaded:',
            calendarDates
        );


    } catch (error) {

        console.error(
            'Calendar error:',
            error
        );


        calendarDates = {};

    }

}


    /* =========================================================
       BUILD ONE MONTH
       IMPORTANT:
       ONLY ONE MONTH IS CREATED.
    ========================================================= */

    function buildMonth(
        year,
        month
    ) {

        const firstDay =
            new Date(
                year,
                month,
                1
            );


        const lastDay =
            new Date(
                year,
                month + 1,
                0
            );


        const monthName =
            firstDay.toLocaleDateString(
                'en-IN',
                {
                    month: 'long',
                    year: 'numeric'
                }
            );


        const firstWeekday =
            firstDay.getDay();


        const daysInMonth =
            lastDay.getDate();


        const today =
            todayString();


        let html = '';


        /*
         * MONTH
         */
        html +=
            '<div class="month single-month">';


        /*
         * MONTH TITLE
         */
        html +=
            '<div class="month-title">'
            +
            escapeHtml(
                monthName
            )
            +
            '</div>';


        /*
         * WEEKDAYS
         */
        html +=
            '<div class="weekdays">';


        const weekdays = [
            'Sun',
            'Mon',
            'Tue',
            'Wed',
            'Thu',
            'Fri',
            'Sat'
        ];


        weekdays.forEach(
            function (day) {

                html +=
                    '<div class="weekday">'
                    +
                    day
                    +
                    '</div>';

            }
        );


        html +=
            '</div>';


        /*
         * DAYS
         */
        html +=
            '<div class="days">';


        /*
         * Empty cells before first day.
         */
        for (
            let i = 0;
            i < firstWeekday;
            i++
        ) {

            html +=
                '<div class="day empty"></div>';

        }


        /*
         * CREATE EACH DAY.
         */
        for (
            let day = 1;
            day <= daysInMonth;
            day++
        ) {

            const date =
                new Date(
                    year,
                    month,
                    day
                );


            const dateString =
                year +
                '-' +
                String(
                    month + 1
                ).padStart(2, '0') +
                '-' +
                String(
                    day
                ).padStart(2, '0');


            const info =
                calendarDates[
                    dateString
                ];


            /*
             * Disable dates before today.
             */
            const past =
                dateString < today;


            /*
             * Return date must be after departure.
             */
            let returnTooEarly =
                false;


            if (
                calendarMode === 'return' &&
                departureDate.value
            ) {

                returnTooEarly =
                    dateString <=
                    departureDate.value;

            }


            const disabled =
                past ||
                returnTooEarly ||
                !info;


            let classes =
                'day';


            if (disabled) {

                classes +=
                    ' disabled';

            }


            if (
                dateString ===
                departureDate.value
            ) {

                classes +=
                    ' selected-departure';

            }


            if (
                dateString ===
                returnDate.value
            ) {

                classes +=
                    ' selected-return';

            }


            /*
             * DAY BUTTON
             */
            html +=
                '<button'
                +
                ' type="button"'
                +
                ' class="'
                +
                classes
                +
                '"'
                +
                ' data-date="'
                +
                dateString
                +
                '"'
                +
                (
                    disabled
                        ? ' disabled'
                        : ''
                )
                +
                '>';


            /*
             * NUMBER
             */
            html +=
                '<span class="day-number">'
                +
                day
                +
                '</span>';


            /*
             * FLIGHT DATA
             */
            if (
                info &&
                info.flight_count > 0
            ) {

                html +=
                    '<span class="day-price">'
                    +
                    formatPrice(
                        info.lowest_fare
                    )
                    +
                    '</span>';


                html +=
                    '<span class="day-flights">'
                    +
                    info.flight_count
                    +
                    (
                        info.flight_count === 1
                            ? ' flight'
                            : ' flights'
                    )
                    +
                    '</span>';

            } else {

                /*
                 * IMPORTANT:
                 * No "No flights" for dates that
                 * don't have data.
                 */
                html +=
                    '<span class="day-no-flight">'
                    +
                    'No flights'
                    +
                    '</span>';

            }


            html +=
                '</button>';

        }


        html +=
            '</div>';


        html +=
            '</div>';


        return html;

    }

/* =========================================================
   RENDER CALENDAR
   ONE MONTH ONLY
========================================================= */

async function renderCalendar() {

    /*
    |--------------------------------------------------------------------------
    | Get selected airport codes
    |--------------------------------------------------------------------------
    */

    const fromCode =
        fromAirport.dataset.code ||
        airportCode(
            fromAirport.value
        );

    const toCode =
        toAirport.dataset.code ||
        airportCode(
            toAirport.value
        );


    /*
    |--------------------------------------------------------------------------
    | Check airports
    |--------------------------------------------------------------------------
    */

    if (
        !fromCode ||
        !toCode
    ) {

        calendarBody.innerHTML =

            '<div style="' +
            'padding:35px;' +
            'text-align:center;' +
            'font-weight:800;' +
            'color:#70809a;' +
            '">' +

            'Please select From and To airports first.'

            +

            '</div>';

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Same airport
    |--------------------------------------------------------------------------
    */

    if (
        fromCode ===
        toCode
    ) {

        calendarBody.innerHTML =

            '<div style="' +
            'padding:35px;' +
            'text-align:center;' +
            'font-weight:800;' +
            'color:#d33;' +
            '">' +

            'From and To airports cannot be the same.'

            +

            '</div>';

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Show loading
    |--------------------------------------------------------------------------
    */

    calendarBody.innerHTML =

        '<div style="' +
        'padding:35px;' +
        'text-align:center;' +
        'font-weight:800;' +
        'color:#1769e8;' +
        '">' +

        'Loading flights...'

        +

        '</div>';


    /*
    |--------------------------------------------------------------------------
    | Load flight dates and prices
    |--------------------------------------------------------------------------
    */

    await loadCalendarDates();


    /*
    |--------------------------------------------------------------------------
    | CURRENT MONTH ONLY
    |--------------------------------------------------------------------------
    */

    const year =
        calendarMonth.getFullYear();

    const month =
        calendarMonth.getMonth();


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | Only ONE buildMonth() call.
    |
    | There is NO second month.
    |
    */

    calendarBody.innerHTML =

        '<div class="months single-month-container">'
        +
        buildMonth(
            year,
            month
        )
        +
        '</div>';


    /*
    |--------------------------------------------------------------------------
    | DATE CLICK EVENTS
    |--------------------------------------------------------------------------
    */

    calendarBody
        .querySelectorAll(
            '.day[data-date]'
        )
        .forEach(
            function(button) {

                button.addEventListener(
                    'click',
                    function() {

                        if (
                            button.disabled
                        ) {

                            return;

                        }


                        selectCalendarDate(
                            button.dataset.date
                        );

                    }
                );

            }
        );

}
    


    /* =========================================================
       OPEN CALENDAR
    ========================================================= */

    async function openCalendar(
        mode
    ) {

        calendarMode =
            mode;


        calendar.classList.add(
            'open'
        );


        const fromCode =
            getAirportCode(
                fromAirport
            );

        const toCode =
            getAirportCode(
                toAirport
            );


        if (
            fromCode &&
            toCode
        ) {

            calendarRoute.textContent =
                fromCode
                +
                ' → '
                +
                toCode
                +
                ' · '
                +
                (
                    mode === 'departure'
                        ? 'Select departure date'
                        : 'Select return date'
                );

        } else {

            calendarRoute.textContent =
                'Select airports first';

        }


        await renderCalendar();

    }


    /* =========================================================
       DATE SELECTION
    ========================================================= */

    function selectCalendarDate(
        value
    ) {

        /*
         * DEPARTURE
         */
        if (
            calendarMode ===
            'departure'
        ) {

            departureDate.value =
                value;


            departurePicker.value =
                displayDate(
                    value
                );


            /*
             * ROUND TRIP
             */
            if (
                tripType.value ===
                'round'
            ) {

                calendarMode =
                    'return';


                const fromCode =
                    getAirportCode(
                        fromAirport
                    );

                const toCode =
                    getAirportCode(
                        toAirport
                    );


                calendarRoute.textContent =
                    fromCode
                    +
                    ' → '
                    +
                    toCode
                    +
                    ' · Select return date';


                /*
                 * Re-render the SAME month.
                 */
                renderCalendar();


                return;

            }


            /*
             * ONE WAY
             */
            calendar.classList.remove(
                'open'
            );


            return;

        }


        /*
         * RETURN DATE
         */
        if (
            !departureDate.value
        ) {

            alert(
                'Please select a departure date first.'
            );

            return;

        }


        if (
            value <=
            departureDate.value
        ) {

            alert(
                'Return date must be after departure date.'
            );

            return;

        }


        returnDate.value =
            value;


        returnPicker.value =
            displayDate(
                value
            );


        calendar.classList.remove(
            'open'
        );

    }


    /* =========================================================
       DEPARTURE DATE PICKER
       Uses airport DATASET code.
    ========================================================= */

    departurePicker.addEventListener(
        'click',
        function () {

            const fromCode =
                fromAirport.dataset.code ||
                airportCode(
                    fromAirport.value
                );

            const toCode =
                toAirport.dataset.code ||
                airportCode(
                    toAirport.value
                );


            /*
             * IMPORTANT:
             * We check airport codes, not just
             * visible input text.
             */
            if (
                !fromCode ||
                !toCode
            ) {

                alert(
                    'Please select From and To airports first.'
                );

                return;

            }


            if (
                fromCode ===
                toCode
            ) {

                alert(
                    'From and To airports cannot be the same.'
                );

                return;

            }


            /*
             * If opening departure picker,
             * start from current month unless
             * a departure date already exists.
             */
            if (
                departureDate.value
            ) {

                const selected =
                    new Date(
                        departureDate.value +
                        'T00:00:00'
                    );


                if (
                    !Number.isNaN(
                        selected.getTime()
                    )
                ) {

                    calendarMonth =
                        new Date(
                            selected.getFullYear(),
                            selected.getMonth(),
                            1
                        );

                }

            }


            openCalendar(
                'departure'
            );

        }
    );


    /* =========================================================
       RETURN DATE PICKER
       Uses airport DATASET code.
    ========================================================= */

    returnPicker.addEventListener(
        'click',
        function () {

            if (
                tripType.value ===
                'oneway'
            ) {

                return;

            }


            const fromCode =
                fromAirport.dataset.code ||
                airportCode(
                    fromAirport.value
                );

            const toCode =
                toAirport.dataset.code ||
                airportCode(
                    toAirport.value
                );


            /*
             * IMPORTANT:
             * We check airport codes, not just
             * visible input text.
             */
            if (
                !fromCode ||
                !toCode
            ) {

                alert(
                    'Please select From and To airports first.'
                );

                return;

            }


            if (
                fromCode ===
                toCode
            ) {

                alert(
                    'From and To airports cannot be the same.'
                );

                return;

            }


            if (
                !departureDate.value
            ) {

                alert(
                    'Please select a departure date first.'
                );

                return;

            }


            /*
             * Open the departure month first.
             */
            const selectedDeparture =
                new Date(
                    departureDate.value +
                    'T00:00:00'
                );


            if (
                !Number.isNaN(
                    selectedDeparture.getTime()
                )
            ) {

                calendarMonth =
                    new Date(
                        selectedDeparture.getFullYear(),
                        selectedDeparture.getMonth(),
                        1
                    );

            }


            openCalendar(
                'return'
            );

        }
    );


    /* =========================================================
       PREVIOUS MONTH
    ========================================================= */

    previousMonth.addEventListener(
        'click',
        function () {

            const previous =
                new Date(
                    calendarMonth.getFullYear(),
                    calendarMonth.getMonth() - 1,
                    1
                );


            /*
             * Don't allow going before current month.
             */
            if (
                isBeforeMinimumMonth(
                    previous
                )
            ) {

                return;

            }


            calendarMonth =
                previous;


            renderCalendar();

        }
    );


    /* =========================================================
       NEXT MONTH
    ========================================================= */

    nextMonth.addEventListener(
        'click',
        function () {

            calendarMonth =
                new Date(
                    calendarMonth.getFullYear(),
                    calendarMonth.getMonth() + 1,
                    1
                );


            renderCalendar();

        }
    );


    /* =========================================================
       CLOSE CALENDAR
    ========================================================= */

    document.addEventListener(
        'click',
        function (event) {

            if (

                !calendar.contains(
                    event.target
                )

                &&

                !departurePicker.contains(
                    event.target
                )

                &&

                !returnPicker.contains(
                    event.target
                )

            ) {

                calendar.classList.remove(
                    'open'
                );

            }

        }
    );


    /* =========================================================
       FORM VALIDATION
    ========================================================= */

    form.addEventListener(
        'submit',
        function (event) {

            const fromCode =
                fromAirport.dataset.code ||
                airportCode(
                    fromAirport.value
                );

            const toCode =
                toAirport.dataset.code ||
                airportCode(
                    toAirport.value
                );


            if (
                !fromCode ||
                !toCode
            ) {

                event.preventDefault();

                alert(
                    'Please select valid From and To airports.'
                );

                return;

            }


            if (
                fromCode ===
                toCode
            ) {

                event.preventDefault();

                alert(
                    'From and To airports cannot be the same.'
                );

                return;

            }


            /*
             * Put airport codes into form.
             */
            fromAirport.value =
                fromCode;

            toAirport.value =
                toCode;


            /*
             * Departure required.
             */
            if (
                !departureDate.value
            ) {

                event.preventDefault();

                alert(
                    'Please select a departure date.'
                );

                return;

            }


            /*
             * Return required for round trip.
             */
            if (
                tripType.value ===
                'round'
                &&
                !returnDate.value
            ) {

                event.preventDefault();

                alert(
                    'Please select a return date.'
                );

                return;

            }


            /*
             * Return must be after departure.
             */
            if (
                tripType.value ===
                'round'
                &&
                returnDate.value <=
                departureDate.value
            ) {

                event.preventDefault();

                alert(
                    'Return date must be after departure date.'
                );

                return;

            }

        }
    );


    /* =========================================================
       INITIAL DATE DISPLAY
    ========================================================= */

    if (
        departureDate.value
    ) {

        departurePicker.value =
            displayDate(
                departureDate.value
            );

    }


    if (
        returnDate.value
    ) {

        returnPicker.value =
            displayDate(
                returnDate.value
            );

    }


    /* =========================================================
       INITIAL CALENDAR MONTH
    ========================================================= */

    if (
        departureDate.value
    ) {

        const initialDeparture =
            new Date(
                departureDate.value +
                'T00:00:00'
            );


        if (
            !Number.isNaN(
                initialDeparture.getTime()
            )
        ) {

            calendarMonth =
                new Date(
                    initialDeparture.getFullYear(),
                    initialDeparture.getMonth(),
                    1
                );

        }

    }


})();

</script>


</body>

</html>