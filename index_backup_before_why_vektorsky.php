<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| AJAX: AIRPORT SEARCH + FLIGHT CALENDAR
|--------------------------------------------------------------------------
*/

if (isset($_GET['ajax'])) {

    header('Content-Type: application/json; charset=utf-8');

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    try {

        /*
        |--------------------------------------------------------------------------
        | Airport autocomplete
        |--------------------------------------------------------------------------
        */

        if ($_GET['ajax'] === 'airport_search') {

            $q = trim(
                $_GET['q'] ?? ''
            );

            if ($q === '') {

                echo json_encode([
                    'success' => true,
                    'airports' => []
                ]);

                exit;
            }

            $like =
                '%' . $q . '%';

            $stmt = $pdo->prepare("

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

                        WHEN UPPER(airport_code)
                             = UPPER(:exact_code)
                        THEN 0

                        WHEN UPPER(city)
                             = UPPER(:exact_city)
                        THEN 1

                        WHEN UPPER(city)
                             LIKE UPPER(:city_prefix)
                        THEN 2

                        WHEN UPPER(airport_name)
                             LIKE UPPER(:name_prefix)
                        THEN 3

                        ELSE 4

                    END,

                    airport_name ASC

                LIMIT 10

            ");

            $stmt->execute([

                ':code' =>
                    $like,

                ':name' =>
                    $like,

                ':city' =>
                    $like,

                ':country' =>
                    $like,

                ':keywords' =>
                    $like,

                ':icao' =>
                    $like,

                ':exact_code' =>
                    $q,

                ':exact_city' =>
                    $q,

                ':city_prefix' =>
                    $q . '%',

                ':name_prefix' =>
                    $q . '%'

            ]);

            echo json_encode([

                'success' =>
                    true,

                'airports' =>
                    $stmt->fetchAll(
                        PDO::FETCH_ASSOC
                    )

            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Flight calendar
        |--------------------------------------------------------------------------
        */

        if ($_GET['ajax'] === 'flight_calendar') {

            $from =
                strtoupper(
                    trim(
                        $_GET['from'] ?? ''
                    )
                );

            $to =
                strtoupper(
                    trim(
                        $_GET['to'] ?? ''
                    )
                );

            $start =
                trim(
                    $_GET['start'] ?? ''
                );

            $end =
                trim(
                    $_GET['end'] ?? ''
                );


            if (
                $from === '' ||
                $to === '' ||
                $from === $to
            ) {

                echo json_encode([

                    'success' =>
                        false,

                    'dates' =>
                        [],

                    'message' =>
                        'Valid airports are required.'

                ]);

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | Get lowest fare for every date
            |--------------------------------------------------------------------------
            */

            $sql = "

                SELECT

                    DATE(f.departure_time)
                        AS flight_date,

                    MIN(f.fare)
                        AS lowest_fare,

                    COUNT(*)
                        AS flight_count

                FROM flight f

                INNER JOIN airport src

                    ON src.airport_id =
                       f.source_airport_id

                INNER JOIN airport dst

                    ON dst.airport_id =
                       f.destination_airport_id

                WHERE

                    UPPER(src.airport_code)
                        = :from_code

                    AND

                    UPPER(dst.airport_code)
                        = :to_code

            ";


            $params = [

                ':from_code' =>
                    $from,

                ':to_code' =>
                    $to

            ];


            if (

                preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $start
                )

                &&

                preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $end
                )

            ) {

                $sql .= "

                    AND DATE(f.departure_time)
                    BETWEEN :start_date
                    AND :end_date

                ";

                $params[':start_date'] =
                    $start;

                $params[':end_date'] =
                    $end;
            }


            $sql .= "

                GROUP BY

                    DATE(f.departure_time)

                ORDER BY

                    flight_date ASC

            ";


            $stmt =
                $pdo->prepare(
                    $sql
                );


            $stmt->execute(
                $params
            );


            $dates = [];


            foreach (

                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )

                as $row

            ) {

                $dates[
                    $row['flight_date']
                ] = [

                    'price' =>
                        (float)
                        $row['lowest_fare'],

                    'flight_count' =>
                        (int)
                        $row['flight_count']

                ];

            }


            echo json_encode([

                'success' =>
                    true,

                'from' =>
                    $from,

                'to' =>
                    $to,

                'dates' =>
                    $dates

            ]);

            exit;
        }


        echo json_encode([

            'success' =>
                false,

            'message' =>
                'Unknown AJAX request.'

        ]);

        exit;


    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([

            'success' =>
                false,

            'message' =>
                $e->getMessage(),

            'airports' =>
                [],

            'dates' =>
                []

        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Normal page values
|--------------------------------------------------------------------------
*/

$from =
    trim(
        $_GET['from'] ?? ''
    );

$to =
    trim(
        $_GET['to'] ?? ''
    );

$departure =
    trim(
        $_GET['departure'] ?? ''
    );

$return =
    trim(
        $_GET['return'] ?? ''
    );


$tripType =
    ($_GET['trip_type'] ?? 'round')
        === 'oneway'
        ? 'oneway'
        : 'round';


$travellers =
    (int)(
        $_GET['travellers'] ?? 1
    );


if ($travellers < 1) {
    $travellers = 1;
}


if ($travellers > 9) {
    $travellers = 9;
}


$travelClass =
    $_GET['class'] ??
    'Economy';


$userName =
    $_SESSION['user_name'] ??
    $_SESSION['name'] ??
    'Traveler';


$isLoggedIn =
    isset($_SESSION['user_id']) ||
    isset($_SESSION['passenger_id']);

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


body {

    margin: 0;

    font-family:
        Inter,
        Arial,
        sans-serif;

    background:
        #f4f7fb;

    color:
        #0d2346;
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

    height: 88px;

    background:
        #ffffff;

    border-bottom:
        1px solid #e5eaf2;

    display:
        flex;

    align-items:
        center;
}


.nav-inner {

    width:
        min(1320px, 92%);

    margin:
        auto;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}


.brand {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

    font-size:
        26px;

    font-weight:
        900;
}


.logo {

    width:
        50px;

    height:
        50px;

    border-radius:
        16px;

    background:
        linear-gradient(
            135deg,
            #3157ef,
            #08a7e8
        );

    display:
        grid;

    place-items:
        center;

    color:
        white;

    font-size:
        27px;

    box-shadow:
        0 10px 25px
        rgba(35,91,232,.23);
}


.nav-links {

    display:
        flex;

    align-items:
        center;

    gap:
        30px;
}


.nav-links a {

    color:
        #263b5a;

    text-decoration:
        none;

    font-weight:
        700;
}


.logout {

    border:
        0;

    background:
        #2563eb;

    color:
        white !important;

    padding:
        13px 22px;

    border-radius:
        14px;

    font-weight:
        800;
}


/* =========================================================
   HERO
========================================================= */

.hero {

    min-height:
        435px;

    background:
        linear-gradient(
            120deg,
            #101c3d,
            #234dcc
        );

    position:
        relative;

    overflow:
        visible;

    color:
        white;

    padding:
        40px 20px 0;
}


.hero:after {

    content:
        "";

    position:
        absolute;

    width:
        430px;

    height:
        430px;

    border-radius:
        50%;

    right:
        -70px;

    top:
        -270px;

    background:
        rgba(255,255,255,.07);
}


.hero h1 {

    text-align:
        center;

    font-size:
        clamp(40px, 5vw, 72px);

    margin:
        0;

    font-weight:
        900;

    letter-spacing:
        -3px;

    position:
        relative;

    z-index:
        1;
}


.hero h1 span {

    color:
        #b8d9ff;
}


.hero p {

    text-align:
        center;

    font-size:
        18px;

    line-height:
        1.7;

    max-width:
        760px;

    margin:
        20px auto;
}


/* =========================================================
   SEARCH CARD
========================================================= */

.search-card {

    position:
        relative;

    z-index:
        20;

    width:
        min(1350px, 94%);

    margin:
        -98px auto 70px;

    background:
        #ffffff;

    border-radius:
        28px;

    padding:
        28px;

    box-shadow:
        0 25px 60px
        rgba(21,42,76,.13);
}


.tabs {

    display:
        flex;

    gap:
        8px;

    margin-bottom:
        24px;
}


.tab {

    border:
        0;

    background:
        #eef4ff;

    color:
        #0754d8;

    padding:
        11px 18px;

    border-radius:
        12px;

    font-weight:
        800;
}


.tab.active {

    background:
        #2563eb;

    color:
        #ffffff;
}


/* =========================================================
   FORM
========================================================= */

.grid {

    display:
        grid;

    grid-template-columns:
        1.1fr
        1.1fr
        1fr
        1fr
        .9fr
        .9fr;

    gap:
        12px;

    align-items:
        end;
}


.field {

    min-width:
        0;
}


.field label {

    display:
        block;

    font-size:
        12px;

    font-weight:
        900;

    letter-spacing:
        .7px;

    margin-bottom:
        8px;

    color:
        #344a68;
}


.input-wrap {

    position:
        relative;
}


.input,
.select {

    width:
        100%;

    height:
        62px;

    border:
        1px solid #cfd9e8;

    border-radius:
        15px;

    background:
        #ffffff;

    padding:
        0 16px;

    color:
        #12294b;

    font-weight:
        650;

    outline:
        none;
}


.input:focus,
.select:focus {

    border-color:
        #3774ee;

    box-shadow:
        0 0 0 4px
        rgba(55,116,238,.08);
}


.input::placeholder {

    color:
        #8a9ab1;
}


.calendar-input {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    cursor:
        pointer;

    text-align:
        left;
}


.calendar-input span {

    color:
        #8190a7;
}


.swap {

    position:
        absolute;

    right:
        -15px;

    top:
        50%;

    transform:
        translateY(-50%);

    z-index:
        50;

    width:
        38px;

    height:
        38px;

    border-radius:
        50%;

    border:
        1px solid #d9e2ef;

    background:
        #ffffff;

    color:
        #1e64e9;

    font-size:
        20px;

    box-shadow:
        0 5px 12px
        rgba(0,0,0,.08);
}


/* =========================================================
   AIRPORT SUGGESTIONS
========================================================= */

.suggestions {

    position:
        absolute;

    left:
        0;

    right:
        0;

    top:
        68px;

    background:
        #ffffff;

    border:
        1px solid #d8e1ef;

    border-radius:
        14px;

    box-shadow:
        0 18px 40px
        rgba(23,42,75,.13);

    z-index:
        5000;

    overflow:
        hidden;

    display:
        none;

    max-height:
        360px;

    overflow-y:
        auto;
}


.suggestions.show {

    display:
        block;
}


.suggestion {

    padding:
        13px 15px;

    border-bottom:
        1px solid #edf1f6;

    cursor:
        pointer;

    background:
        #ffffff;
}


.suggestion:hover {

    background:
        #f4f8ff;
}


.suggestion strong {

    display:
        block;

    color:
        #10294c;
}


.suggestion small {

    display:
        block;

    color:
        #6c7e98;

    margin-top:
        3px;
}


/* =========================================================
   SEARCH BUTTON
========================================================= */

.search-btn {

    height:
        62px;

    border:
        0;

    border-radius:
        15px;

    background:
        linear-gradient(
            135deg,
            #2861ed,
            #08a6e8
        );

    color:
        #ffffff;

    font-weight:
        900;

    padding:
        0 28px;

    box-shadow:
        0 12px 25px
        rgba(33,103,237,.21);
}


/* =========================================================
   CALENDAR
========================================================= */

.calendar {

    position:
        absolute;

    z-index:
        4000;

    left:
        50%;

    transform:
        translateX(-50%);

    top:
        calc(100% - 5px);

    width:
        min(780px, 96vw);

    background:
        #ffffff;

    border:
        1px solid #dbe3ef;

    border-radius:
        20px;

    box-shadow:
        0 30px 70px
        rgba(13,33,64,.22);

    padding:
        20px;

    display:
        none;

    color:
        #10284b;
}


.calendar.open {

    display:
        block;
}


.cal-head {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    margin-bottom:
        15px;
}


.cal-head h3 {

    margin:
        0;

    font-size:
        18px;
}


.cal-nav {

    display:
        flex;

    gap:
        8px;
}


.cal-nav button {

    width:
        38px;

    height:
        38px;

    border:
        1px solid #dbe3ef;

    background:
        #ffffff;

    border-radius:
        10px;

    font-size:
        20px;
}


.calendar-route {

    font-size:
        13px;

    color:
        #64748b;

    margin-bottom:
        10px;
}


.months {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        22px;
}


.month-title {

    text-align:
        center;

    font-weight:
        900;

    margin-bottom:
        12px;
}


.week {

    display:
        grid;

    grid-template-columns:
        repeat(7, 1fr);

    gap:
        4px;
}


.dow {

    font-size:
        11px;

    text-align:
        center;

    color:
        #8390a3;

    font-weight:
        800;

    padding:
        5px 0;
}


.day {

    min-height:
        62px;

    border:
        1px solid transparent;

    background:
        #ffffff;

    border-radius:
        10px;

    text-align:
        center;

    padding:
        7px 3px;

    cursor:
        pointer;
}


.day:hover:not(.disabled) {

    background:
        #f1f6ff;

    border-color:
        #bfd3fb;
}


.day.empty {

    cursor:
        default;
}


.day.disabled {

    opacity:
        .35;

    cursor:
        not-allowed;
}


.day .num {

    font-weight:
        800;

    font-size:
        13px;
}


.day .price {

    font-size:
        10px;

    color:
        #067647;

    font-weight:
        900;

    margin-top:
        4px;
}


.day .count {

    font-size:
        9px;

    color:
        #73839a;

    margin-top:
        2px;
}


.day.selected {

    background:
        #2563eb;

    color:
        #ffffff;
}


.day.selected .price,
.day.selected .count {

    color:
        #ffffff;
}


.calendar-loading {

    text-align:
        center;

    padding:
        30px;

    color:
        #65758d;
}


.calendar-error {

    text-align:
        center;

    padding:
        20px;

    color:
        #b42318;

    background:
        #fff4f2;

    border-radius:
        10px;
}


/* =========================================================
   LOWER SECTION
========================================================= */

.below {

    width:
        min(1350px, 94%);

    margin:
        0 auto 70px;
}


.info {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        18px;
}


.info-card {

    background:
        #ffffff;

    border:
        1px solid #e5eaf1;

    border-radius:
        18px;

    padding:
        22px;
}


.info-card h3 {

    margin:
        0 0 8px;
}


.info-card p {

    margin:
        0;

    color:
        #687992;

    line-height:
        1.6;
}


.footer {

    padding:
        28px 5%;

    color:
        #6c7c94;

    border-top:
        1px solid #e3e8f0;

    background:
        #ffffff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1050px) {

    .grid {

        grid-template-columns:
            1fr 1fr 1fr;

    }

}


@media(max-width:720px) {

    .nav {

        height:
            auto;

        padding:
            14px 0;

    }


    .nav-links {

        gap:
            10px;

    }


    .nav-links a:not(.logout) {

        display:
            none;

    }


    .hero {

        min-height:
            370px;

    }


    .hero h1 {

        letter-spacing:
            -1px;

    }


    .search-card {

        margin-top:
            -80px;

        padding:
            18px;

    }


    .grid {

        grid-template-columns:
            1fr;

    }


    .swap {

        right:
            12px;

        top:
            50px;

    }


    .months {

        grid-template-columns:
            1fr;

    }


    .calendar {

        max-height:
            80vh;

        overflow-y:
            auto;

    }


    .info {

        grid-template-columns:
            1fr;

    }

}

</style>

</head>


<body>


<!-- =======================================================
     NAVBAR
======================================================== -->

<header class="nav">

<div class="nav-inner">

<div class="brand">

<div class="logo">
✈
</div>

<div>
Vektor<span style="color:#1765ea">
Sky
</span>
</div>

</div>


<nav class="nav-links">

<a href="index.php">
✈ Flights
</a>


<a href="my_bookings.php">
🎫 My Bookings
</a>


<?php if ($isLoggedIn): ?>

<a
    href="logout.php"
    class="logout"
>
Logout
</a>

<?php else: ?>

<a
    href="login.php"
    class="logout"
>
Login
</a>

<?php endif; ?>

</nav>

</div>

</header>


<!-- =======================================================
     HERO
======================================================== -->

<section class="hero">

<h1>

Your journey starts

<span>
here.
</span>

</h1>


<p>

Search thousands of airports,
compare available fares,
and book your next journey
with VektorSky.

</p>

</section>


<!-- =======================================================
     SEARCH CARD
======================================================== -->

<main class="search-card">


<form
    id="flightSearchForm"
    method="GET"
    action="search_results.php"
    autocomplete="off"
>


<input
    type="hidden"
    name="trip_type"
    id="tripType"
    value="<?= htmlspecialchars($tripType) ?>"
>


<input
    type="hidden"
    name="from"
    id="fromAirport"
    value="<?= htmlspecialchars($from) ?>"
>


<input
    type="hidden"
    name="to"
    id="toAirport"
    value="<?= htmlspecialchars($to) ?>"
>


<!-- =====================================================
     TRIP TYPE
===================================================== -->

<div class="tabs">


<button
    type="button"
    class="tab <?= $tripType === 'round' ? 'active' : '' ?>"
    id="roundTripTab"
>

✈ Round Trip

</button>


<button
    type="button"
    class="tab <?= $tripType === 'oneway' ? 'active' : '' ?>"
    id="oneWayTab"
>

One Way

</button>


</div>


<!-- =====================================================
     FORM GRID
===================================================== -->

<div class="grid">


<!-- FROM -->

<div class="field">

<label>
FROM
</label>


<div class="input-wrap">


<input
    class="input airport-input"
    id="fromSearch"
    placeholder="City or airport"
    value="<?= htmlspecialchars($from) ?>"
>


<div
    class="suggestions"
    id="fromSuggestions"
></div>


<button
    class="swap"
    type="button"
    id="swapBtn"
>
⇄
</button>


</div>

</div>


<!-- TO -->

<div class="field">

<label>
TO
</label>


<div class="input-wrap">


<input
    class="input airport-input"
    id="toSearch"
    placeholder="City or airport"
    value="<?= htmlspecialchars($to) ?>"
>


<div
    class="suggestions"
    id="toSuggestions"
></div>


</div>

</div>


<!-- DEPARTURE -->

<div
    class="field"
    style="position:relative"
>

<label>
DEPARTURE
</label>


<button
    type="button"
    class="input calendar-input"
    id="departurePicker"
>

<span>
📅
</span>


<span id="departureText">
Select date
</span>


</button>


<input
    type="hidden"
    name="departure"
    id="departureDate"
    value="<?= htmlspecialchars($departure) ?>"
>


</div>


<!-- RETURN -->

<div
    class="field"
    id="returnField"
    style="position:relative"
>

<label>
RETURN
</label>


<button
    type="button"
    class="input calendar-input"
    id="returnPicker"
>

<span>
📅
</span>


<span id="returnText">
Select return date
</span>


</button>


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
TRAVELLERS
</label>


<select
    class="select"
    name="travellers"
>


<?php for ($i = 1; $i <= 9; $i++): ?>

<option
    value="<?= $i ?>"
    <?= $travellers === $i ? 'selected' : '' ?>
>

<?= $i ?>
<?= $i === 1 ? 'Traveller' : 'Travellers' ?>

</option>

<?php endfor; ?>


</select>

</div>


<!-- CLASS -->

<div class="field">

<label>
CLASS
</label>


<select
    class="select"
    name="class"
>


<option
    <?= strcasecmp($travelClass, 'Economy') === 0
        ? 'selected'
        : '' ?>
>
Economy
</option>


<option
    <?= strcasecmp($travelClass, 'Business') === 0
        ? 'selected'
        : '' ?>
>
Business
</option>


<option
    <?= strcasecmp($travelClass, 'First') === 0
        ? 'selected'
        : '' ?>
>
First
</option>


</select>

</div>


</div>


<div style="margin-top:18px">

<button
    class="search-btn"
    type="submit"
>

🔎 Search Flights

</button>

</div>


</form>


<!-- =====================================================
     CALENDAR
===================================================== -->

<div
    class="calendar"
    id="calendar"
>


<div class="cal-head">


<h3>
Select date
</h3>


<div class="cal-nav">


<button
    type="button"
    id="prevMonth"
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


<div
    class="calendar-route"
    id="calendarRoute"
>
Select departure date
</div>


<div id="calendarBody"></div>


</div>


</main>


<!-- =======================================================
     INFO
======================================================== -->

<section class="below">

<div class="info">


<div class="info-card">

<h3>
🔎 Easy search
</h3>

<p>
Search by airport code, city,
airport name or country.
</p>

</div>


<div class="info-card">

<h3>
💰 Live fares
</h3>

<p>
Calendar prices are read directly
from your flight database.
</p>

</div>


<div class="info-card">

<h3>
🔐 Secure booking
</h3>

<p>
Your selected flight and actual
flight timing continue to the
booking process.
</p>

</div>


</div>

</section>


<footer class="footer">

© <?= date('Y') ?>
Vektor Sky — Flight Booking System
(PHP + MySQL)

</footer>


<script>

/* =========================================================
   VEKTORSKY FLIGHT SEARCH
========================================================= */

(function () {

    'use strict';


    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const form =
        document.getElementById(
            'flightSearchForm'
        );


    const tripType =
        document.getElementById(
            'tripType'
        );


    const roundTripTab =
        document.getElementById(
            'roundTripTab'
        );


    const oneWayTab =
        document.getElementById(
            'oneWayTab'
        );


    const fromAirport =
        document.getElementById(
            'fromAirport'
        );


    const toAirport =
        document.getElementById(
            'toAirport'
        );


    const fromSearch =
        document.getElementById(
            'fromSearch'
        );


    const toSearch =
        document.getElementById(
            'toSearch'
        );


    const fromSuggestions =
        document.getElementById(
            'fromSuggestions'
        );


    const toSuggestions =
        document.getElementById(
            'toSuggestions'
        );


    const swapButton =
        document.getElementById(
            'swapBtn'
        );


    const departurePicker =
        document.getElementById(
            'departurePicker'
        );


    const returnPicker =
        document.getElementById(
            'returnPicker'
        );


    const departureDate =
        document.getElementById(
            'departureDate'
        );


    const returnDate =
        document.getElementById(
            'returnDate'
        );


    const departureText =
        document.getElementById(
            'departureText'
        );


    const returnText =
        document.getElementById(
            'returnText'
        );


    const returnField =
        document.getElementById(
            'returnField'
        );


    const calendar =
        document.getElementById(
            'calendar'
        );


    const calendarBody =
        document.getElementById(
            'calendarBody'
        );


    const calendarRoute =
        document.getElementById(
            'calendarRoute'
        );


    const previousMonth =
        document.getElementById(
            'prevMonth'
        );


    const nextMonth =
        document.getElementById(
            'nextMonth'
        );


    /*
    |--------------------------------------------------------------------------
    | Calendar state
    |--------------------------------------------------------------------------
    */

    let calendarMode =
        'departure';


    let calendarMonth =
        new Date();


    calendarMonth.setDate(1);


    let calendarCache = {};


    let searchTimers = {
        from: null,
        to: null
    };


    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(
            value ?? ''
        ).replace(
            /[&<>"']/g,
            function (character) {

                return {

                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'

                }[character];

            }
        );

    }


    function airportCode(value) {

        return String(
            value || ''
        )
        .trim()
        .toUpperCase();

    }


    function validAirportCode(value) {

        return /^[A-Z0-9]{3,10}$/.test(
            value
        );

    }


    function formatDate(date) {

        return (

            date.getFullYear()
            + '-'
            + String(
                date.getMonth() + 1
            ).padStart(2, '0')
            + '-'
            + String(
                date.getDate()
            ).padStart(2, '0')

        );

    }


    function parseDate(value) {

        if (
            !/^\d{4}-\d{2}-\d{2}$/.test(
                value
            )
        ) {

            return null;
        }


        const parts =
            value.split('-')
                .map(Number);


        return new Date(
            parts[0],
            parts[1] - 1,
            parts[2]
        );

    }


    function displayDate(value) {

        const date =
            parseDate(value);


        if (!date) {

            return 'Select date';
        }


        return date.toLocaleDateString(
            'en-IN',
            {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Trip type
    |--------------------------------------------------------------------------
    */

    function setTripType(type) {

        if (
            type !== 'round' &&
            type !== 'oneway'
        ) {

            type = 'round';
        }


        tripType.value =
            type;


        const round =
            type === 'round';


        roundTripTab.classList.toggle(
            'active',
            round
        );


        oneWayTab.classList.toggle(
            'active',
            !round
        );


        returnField.style.display =
            round
                ? ''
                : 'block';


        if (!round) {

            returnField.style.display =
                'none';


            returnDate.value =
                '';


            returnText.textContent =
                'Select return date';
        }

    }


    setTripType(
        tripType.value
    );


    roundTripTab.addEventListener(
        'click',
        function () {

            setTripType(
                'round'
            );

        }
    );


    oneWayTab.addEventListener(
        'click',
        function () {

            setTripType(
                'oneway'
            );

            calendarMode =
                'departure';

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Airport autocomplete
    |--------------------------------------------------------------------------
    */

    function closeSuggestions() {

        fromSuggestions.classList.remove(
            'show'
        );


        toSuggestions.classList.remove(
            'show'
        );

    }


    function showAirportSuggestions(
        input,
        box,
        hidden,
        airports
    ) {

        box.innerHTML =
            '';


        if (
            !Array.isArray(
                airports
            ) ||
            airports.length === 0
        ) {

            box.classList.remove(
                'show'
            );

            return;
        }


        airports.forEach(
            function (airport) {

                const option =
                    document.createElement(
                        'div'
                    );


                option.className =
                    'suggestion';


                option.innerHTML =

                    '<strong>'
                    + '✈ '
                    + escapeHtml(
                        airport.airport_name
                    )
                    + ' <span style="color:#2563eb">'
                    + escapeHtml(
                        airport.airport_code
                    )
                    + '</span>'
                    + '</strong>'

                    +

                    '<small>'
                    + escapeHtml(
                        airport.city
                    )
                    + ', '
                    + escapeHtml(
                        airport.country
                    )
                    + '</small>';


                option.addEventListener(
                    'mousedown',
                    function (event) {

                        event.preventDefault();

                    }
                );


                option.addEventListener(
                    'click',
                    function () {

                        const code =
                            airportCode(
                                airport.airport_code
                            );


                        hidden.value =
                            code;


                        input.value =

                            code
                            + ' - '
                            + airport.city;


                        box.innerHTML =
                            '';


                        box.classList.remove(
                            'show'
                        );

                    }
                );


                box.appendChild(
                    option
                );

            }
        );


        box.classList.add(
            'show'
        );

    }


    async function searchAirports(
        input,
        box,
        hidden
    ) {

        const query =
            input.value.trim();


        if (
            query.length < 2
        ) {

            box.classList.remove(
                'show'
            );

            return;
        }


        try {

            const response =
                await fetch(

                    'index.php'
                    + '?ajax=airport_search'
                    + '&q='
                    + encodeURIComponent(
                        query
                    ),

                    {
                        cache:
                            'no-store'
                    }

                );


            if (
                !response.ok
            ) {

                throw new Error(
                    'Airport search failed'
                );
            }


            const data =
                await response.json();


            showAirportSuggestions(

                input,
                box,
                hidden,

                data.airports ||
                    []

            );


        } catch (error) {

            console.error(
                error
            );

            box.classList.remove(
                'show'
            );

        }

    }


    function bindAirport(
        input,
        box,
        hidden,
        timerName
    ) {

        input.addEventListener(
            'input',
            function () {

                /*
                | User changed the text,
                | so old selected code is invalid.
                */

                hidden.value =
                    '';


                clearTimeout(
                    searchTimers[
                        timerName
                    ]
                );


                searchTimers[
                    timerName
                ] = setTimeout(

                    function () {

                        searchAirports(
                            input,
                            box,
                            hidden
                        );

                    },

                    180

                );

            }
        );


        input.addEventListener(
            'focus',
            function () {

                if (
                    input.value.trim()
                        .length >= 2
                ) {

                    searchAirports(
                        input,
                        box,
                        hidden
                    );

                }

            }
        );

    }


    bindAirport(
        fromSearch,
        fromSuggestions,
        fromAirport,
        'from'
    );


    bindAirport(
        toSearch,
        toSuggestions,
        toAirport,
        'to'
    );


    document.addEventListener(
        'click',
        function (event) {

            if (
                !event.target.closest(
                    '.input-wrap'
                )
            ) {

                closeSuggestions();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Swap airports
    |--------------------------------------------------------------------------
    */

    swapButton.addEventListener(
        'click',
        function () {

            const visibleFrom =
                fromSearch.value;


            const visibleTo =
                toSearch.value;


            const codeFrom =
                fromAirport.value;


            const codeTo =
                toAirport.value;


            fromSearch.value =
                visibleTo;


            toSearch.value =
                visibleFrom;


            fromAirport.value =
                codeTo;


            toAirport.value =
                codeFrom;


            closeSuggestions();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Route for calendar
    |--------------------------------------------------------------------------
    */

    function getCalendarRoute() {

        if (
            calendarMode ===
            'departure'
        ) {

            return {

                from:
                    airportCode(
                        fromAirport.value
                    ),

                to:
                    airportCode(
                        toAirport.value
                    )

            };

        }


        return {

            from:
                airportCode(
                    toAirport.value
                ),

            to:
                airportCode(
                    fromAirport.value
                )

        };

    }


    /*
    |--------------------------------------------------------------------------
    | Load calendar data
    |--------------------------------------------------------------------------
    */

    async function loadCalendarDates() {

        const route =
            getCalendarRoute();


        if (
            !validAirportCode(
                route.from
            )

            ||

            !validAirportCode(
                route.to
            )

            ||

            route.from ===
                route.to
        ) {

            return {};

        }


        const firstDay =
            new Date(
                calendarMonth.getFullYear(),
                calendarMonth.getMonth(),
                1
            );


        const lastDay =
            new Date(
                calendarMonth.getFullYear(),
                calendarMonth.getMonth() + 2,
                0
            );


        const start =
            formatDate(
                firstDay
            );


        const end =
            formatDate(
                lastDay
            );


        const cacheKey =

            route.from
            + '|'
            + route.to
            + '|'
            + start
            + '|'
            + end;


        if (
            calendarCache[
                cacheKey
            ]
        ) {

            return calendarCache[
                cacheKey
            ];

        }


        const url =

            'index.php'
            + '?ajax=flight_calendar'
            + '&from='
            + encodeURIComponent(
                route.from
            )
            + '&to='
            + encodeURIComponent(
                route.to
            )
            + '&start='
            + start
            + '&end='
            + end;


        const response =
            await fetch(
                url,
                {
                    cache:
                        'no-store'
                }
            );


        if (
            !response.ok
        ) {

            throw new Error(
                'Calendar request failed'
            );

        }


        const data =
            await response.json();


        if (
            !data.success
        ) {

            throw new Error(
                data.message ||
                'Unable to load calendar'
            );

        }


        calendarCache[
            cacheKey
        ] =
            data.dates || {};


        return calendarCache[
            cacheKey
        ];

    }


    /*
    |--------------------------------------------------------------------------
    | Build month
    |--------------------------------------------------------------------------
    */

    function buildMonth(
        year,
        month,
        dates
    ) {

        const first =
            new Date(
                year,
                month,
                1
            );


        const last =
            new Date(
                year,
                month + 1,
                0
            );


        const firstWeekDay =
            first.getDay();


        let html = '';


        html +=

            '<div>'

            +

            '<div class="month-title">'

            +

            first.toLocaleDateString(
                'en-IN',
                {
                    month:
                        'long',

                    year:
                        'numeric'
                }
            )

            +

            '</div>'

            +

            '<div class="week">';


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

                    '<div class="dow">'
                    + day
                    + '</div>';

            }
        );


        for (
            let i = 0;
            i < firstWeekDay;
            i++
        ) {

            html +=
                '<div class="day empty"></div>';

        }


        const today =
            new Date();


        today.setHours(
            0,
            0,
            0,
            0
        );


        for (
            let day = 1;
            day <= last.getDate();
            day++
        ) {

            const date =
                new Date(
                    year,
                    month,
                    day
                );


            const key =
                formatDate(
                    date
                );


            const data =
                dates[key];


            let disabled =
                date < today;


            /*
            | Return must be after departure.
            */

            if (
                calendarMode ===
                    'return'
                &&
                departureDate.value
            ) {

                const departure =
                    parseDate(
                        departureDate.value
                    );


                if (
                    departure &&
                    date <= departure
                ) {

                    disabled =
                        true;

                }

            }


            let selected =

                calendarMode ===
                    'departure'

                    ?

                    departureDate.value
                        === key

                    :

                    returnDate.value
                        === key;


            html +=

                '<button '
                + 'type="button" '
                + 'class="day '
                + (
                    disabled
                        ? 'disabled '
                        : ''
                )
                + (
                    selected
                        ? 'selected'
                        : ''
                )
                + '" '
                + 'data-date="'
                + key
                + '" '
                + (
                    disabled
                        ? 'disabled'
                        : ''
                )
                + '>';



            html +=

                '<div class="num">'
                + day
                + '</div>';


            if (data) {

                html +=

                    '<div class="price">'
                    + '₹'
                    + Number(
                        data.price
                    ).toLocaleString(
                        'en-IN'
                    )
                    + '</div>';


                html +=

                    '<div class="count">'
                    + data.flight_count
                    + ' flight'
                    + (
                        data.flight_count === 1
                            ? ''
                            : 's'
                    )
                    + '</div>';

            } else {

                html +=

                    '<div '
                    + 'class="price" '
                    + 'style="color:#a0aabd"'
                    + '>'
                    + '—'
                    + '</div>';

            }


            html +=
                '</button>';

        }


        html +=
            '</div></div>';


        return html;

    }


    /*
    |--------------------------------------------------------------------------
    | Render calendar
    |--------------------------------------------------------------------------
    */

    async function renderCalendar() {

        calendarBody.innerHTML =

            '<div class="calendar-loading">'
            + 'Loading flight prices…'
            + '</div>';


        try {

            const dates =
                await loadCalendarDates();


            const year =
                calendarMonth.getFullYear();


            const month =
                calendarMonth.getMonth();


            const secondMonth =
                new Date(
                    year,
                    month + 1,
                    1
                );


            calendarBody.innerHTML =

                '<div class="months">'

                +

                buildMonth(
                    year,
                    month,
                    dates
                )

                +

                buildMonth(
                    secondMonth.getFullYear(),
                    secondMonth.getMonth(),
                    dates
                )

                +

                '</div>';


            calendarBody
                .querySelectorAll(
                    '.day[data-date]'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                selectCalendarDate(
                                    button.dataset.date
                                );

                            }
                        );

                    }
                );


        } catch (error) {

            console.error(
                error
            );


            calendarBody.innerHTML =

                '<div class="calendar-error">'
                + escapeHtml(
                    error.message
                )
                + '</div>';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Open calendar
    |--------------------------------------------------------------------------
    */

    function openCalendar(
        mode
    ) {

        calendarMode =
            mode;


        calendar.classList.add(
            'open'
        );


        const route =
            getCalendarRoute();


        if (
            route.from &&
            route.to
        ) {

            calendarRoute.textContent =

                route.from
                + ' → '
                + route.to
                + ' · '
                + (
                    mode ===
                        'departure'

                        ?

                        'Select departure date'

                        :

                        'Select return date'
                );

        } else {

            calendarRoute.textContent =

                'Select airports first';

        }


        renderCalendar();

    }


    /*
    |--------------------------------------------------------------------------
    | Select calendar date
    |--------------------------------------------------------------------------
    */

    function selectCalendarDate(
        value
    ) {

        if (
            calendarMode ===
            'departure'
        ) {

            departureDate.value =
                value;


            departureText.textContent =
                displayDate(
                    value
                );


            if (
                tripType.value ===
                'round'
            ) {

                calendarMode =
                    'return';


                const route =
                    getCalendarRoute();


                calendarRoute.textContent =

                    route.from
                    + ' → '
                    + route.to
                    + ' · '
                    + 'Select return date';


                renderCalendar();


                return;

            }


            calendar.classList.remove(
                'open'
            );


            return;

        }


        if (
            departureDate.value &&
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


        returnText.textContent =
            displayDate(
                value
            );


        calendar.classList.remove(
            'open'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Calendar buttons
    |--------------------------------------------------------------------------
    */

    departurePicker.addEventListener(
        'click',
        function () {

            if (
                !validAirportCode(
                    fromAirport.value
                )

                ||

                !validAirportCode(
                    toAirport.value
                )
            ) {

                alert(
                    'Please select From and To airports first.'
                );

                return;

            }


            openCalendar(
                'departure'
            );

        }
    );


    returnPicker.addEventListener(
        'click',
        function () {

            if (
                tripType.value ===
                'oneway'
            ) {

                return;

            }


            if (
                !validAirportCode(
                    fromAirport.value
                )

                ||

                !validAirportCode(
                    toAirport.value
                )
            ) {

                alert(
                    'Please select From and To airports first.'
                );

                return;

            }


            openCalendar(
                'return'
            );

        }
    );


    previousMonth.addEventListener(
        'click',
        function () {

            calendarMonth.setMonth(
                calendarMonth.getMonth() - 1
            );


            renderCalendar();

        }
    );


    nextMonth.addEventListener(
        'click',
        function () {

            calendarMonth.setMonth(
                calendarMonth.getMonth() + 1
            );


            renderCalendar();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Close calendar outside click
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Form validation
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        function (event) {

            const fromCode =
                airportCode(
                    fromAirport.value
                );


            const toCode =
                airportCode(
                    toAirport.value
                );


            /*
            | Must select actual airports.
            */

            if (
                !validAirportCode(
                    fromCode
                )

                ||

                !validAirportCode(
                    toCode
                )
            ) {

                event.preventDefault();


                alert(
                    'Please select both airports from the suggestions.'
                );


                return;

            }


            if (
                fromCode ===
                toCode
            ) {

                event.preventDefault();


                alert(
                    'Departure and destination airports cannot be the same.'
                );


                return;

            }


            if (
                !departureDate.value
            ) {

                event.preventDefault();


                alert(
                    'Please select a departure date.'
                );


                openCalendar(
                    'departure'
                );


                return;

            }


            if (
                tripType.value ===
                'round'
            ) {

                if (
                    !returnDate.value
                ) {

                    event.preventDefault();


                    alert(
                        'Please select a return date.'
                    );


                    openCalendar(
                        'return'
                    );


                    return;

                }


                if (
                    returnDate.value <=
                    departureDate.value
                ) {

                    event.preventDefault();


                    alert(
                        'Return date must be after departure date.'
                    );


                    openCalendar(
                        'return'
                    );


                    return;

                }

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Existing values
    |--------------------------------------------------------------------------
    */

    if (
        departureDate.value
    ) {

        departureText.textContent =
            displayDate(
                departureDate.value
            );

    }


    if (
        returnDate.value
    ) {

        returnText.textContent =
            displayDate(
                returnDate.value
            );

    }


})();

</script>


</body>

</html>