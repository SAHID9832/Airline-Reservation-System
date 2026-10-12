<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function esc($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| PASSENGER
|--------------------------------------------------------------------------
*/

$passengerId =
    $_SESSION['passenger_id']
    ?? $_SESSION['user_id']
    ?? null;

if (!$passengerId) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET FLIGHT ID
|--------------------------------------------------------------------------
|
| Priority:
| 1. GET
| 2. POST
| 3. SESSION
|
*/

$flightId = 0;

if (isset($_GET['flight_id'])) {
    $flightId = (int)$_GET['flight_id'];
}

if ($flightId <= 0 && isset($_POST['flight_id'])) {
    $flightId = (int)$_POST['flight_id'];
}

if (
    $flightId <= 0 &&
    isset($_SESSION['booking_flight_id'])
) {
    $flightId =
        (int)$_SESSION['booking_flight_id'];
}


/*
|--------------------------------------------------------------------------
| INVALID FLIGHT
|--------------------------------------------------------------------------
*/

if ($flightId <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET FLIGHT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        f.flight_id,
        f.flight_number,
        f.airline_name,

        f.aircraft_id,

        f.departure_time,
        f.arrival_time,

        f.fare,

        src.airport_id AS source_airport_id,
        src.airport_code AS source_code,
        src.city AS source_city,

        dst.airport_id AS destination_airport_id,
        dst.airport_code AS destination_code,
        dst.city AS destination_city,

        ac.aircraft_id AS actual_aircraft_id,
        ac.aircraft_no,
        ac.model,
        ac.total_seats

    FROM flight f

    INNER JOIN airport src
        ON f.source_airport_id =
           src.airport_id

    INNER JOIN airport dst
        ON f.destination_airport_id =
           dst.airport_id

    INNER JOIN aircraft ac
        ON f.aircraft_id =
           ac.aircraft_id

    WHERE f.flight_id = ?

    LIMIT 1
");

$stmt->execute([
    $flightId
]);

$flight =
    $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FLIGHT NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$flight) {

    die('
        <div style="
            font-family:Arial,sans-serif;
            max-width:600px;
            margin:100px auto;
            text-align:center;
            padding:40px;
        ">

            <h2 style="color:#dc2626;">
                Flight not found
            </h2>

            <p style="color:#64748b;">
                The selected flight does not exist.
            </p>

            <a
                href="index.php"
                style="
                    display:inline-block;
                    margin-top:20px;
                    padding:12px 22px;
                    background:#2563eb;
                    color:white;
                    text-decoration:none;
                    border-radius:10px;
                "
            >
                ← Back to Flight Search
            </a>

        </div>
    ');
}


/*
|--------------------------------------------------------------------------
| SAVE FLIGHT IN SESSION
|--------------------------------------------------------------------------
*/

$_SESSION['booking_flight_id'] =
    $flightId;


/*
|--------------------------------------------------------------------------
| VERIFY AIRCRAFT SEAT COUNT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM seat
    WHERE aircraft_id = ?
");

$stmt->execute([
    $flight['aircraft_id']
]);

$actualAircraftSeats =
    (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| GET FLIGHT SEATS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        fs.flight_seat_id,
        fs.flight_id,
        fs.seat_id,
        fs.status,
        fs.booked_by,
        fs.booked_at,

        s.aircraft_id,
        s.seat_number,
        s.seat_class,
        s.seat_type,
        s.seat_price

    FROM flight_seat fs

    INNER JOIN seat s
        ON fs.seat_id =
           s.seat_id

    WHERE fs.flight_id = ?

      AND s.aircraft_id = ?

    ORDER BY

        CAST(
            REGEXP_REPLACE(
                s.seat_number,
                '[^0-9]',
                ''
            ) AS UNSIGNED
        ),

        s.seat_number
");

$stmt->execute([
    $flightId,
    $flight['aircraft_id']
]);

$seats =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GROUP SEATS BY ROW
|--------------------------------------------------------------------------
*/

$seatRows = [];

$seatLetters = [];


foreach ($seats as $seat) {

    if (
        preg_match(
            '/^([0-9]+)([A-Za-z]+)$/',
            $seat['seat_number'],
            $matches
        )
    ) {

        $row =
            $matches[1];

        $letter =
            strtoupper(
                $matches[2]
            );

    } else {

        $row = '0';

        $letter =
            strtoupper(
                $seat['seat_number']
            );
    }


    if (
        !isset(
            $seatRows[$row]
        )
    ) {

        $seatRows[$row] = [];
    }


    $seatRows[$row][$letter] =
        $seat;


    $seatLetters[$letter] =
        $letter;
}


/*
|--------------------------------------------------------------------------
| SORT ROWS
|--------------------------------------------------------------------------
*/

uksort(
    $seatRows,
    function ($a, $b) {

        return
            (int)$a
            <=>
            (int)$b;
    }
);


/*
|--------------------------------------------------------------------------
| SORT SEAT LETTERS
|--------------------------------------------------------------------------
*/

$seatLetters =
    array_values(
        $seatLetters
    );

usort(
    $seatLetters,
    function ($a, $b) {

        return strcmp(
            $a,
            $b
        );
    }
);


/*
|--------------------------------------------------------------------------
| FLIGHT DATE / TIME
|--------------------------------------------------------------------------
*/

$departure =
    new DateTime(
        $flight['departure_time']
    );

$arrival =
    new DateTime(
        $flight['arrival_time']
    );

$flightDate =
    $departure->format(
        'd M Y'
    );

$departureTime =
    $departure->format(
        'h:i A'
    );

$arrivalTime =
    $arrival->format(
        'h:i A'
    );


/*
|--------------------------------------------------------------------------
| PASSENGER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        name,
        email,
        phone,
        passport_no,
        date_of_birth,
        gender
    FROM passenger
    WHERE passenger_id = ?
    LIMIT 1
");

$stmt->execute([
    $passengerId
]);

$passenger =
    $stmt->fetch(PDO::FETCH_ASSOC);


$passengerName =
    $passenger['name']
    ?? 'Passenger 1';


/*
|--------------------------------------------------------------------------
| SAVE PASSENGER BOOKING DATA
|--------------------------------------------------------------------------
*/

$_SESSION['booking_passenger'] = [

    'name' =>
        $passenger['name']
        ?? '',

    'email' =>
        $passenger['email']
        ?? '',

    'phone' =>
        $passenger['phone']
        ?? '',

    'passport_no' =>
        $passenger['passport_no']
        ?? '',

    'date_of_birth' =>
        $passenger['date_of_birth']
        ?? '',

    'gender' =>
        $passenger['gender']
        ?? ''
];

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1.0"
>

<title>
    VektorSky | Select Seat
</title>


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
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #eef5ff 0%,
            #f8fbff 50%,
            #eefafc 100%
        );

    color: #101828;

    min-height: 100vh;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    height: 72px;

    background:
        rgba(255,255,255,.88);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 5%;

    position: sticky;

    top: 0;

    z-index: 100;
}


.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: #101828;

    font-size: 22px;

    font-weight: 900;
}


.logo-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.25);
}


.logo span {

    color: #2563eb;
}


.back {

    color: #475467;

    text-decoration: none;

    font-size: 12px;

    font-weight: 800;

    transition: .2s;
}


.back:hover {

    color: #2563eb;

    text-decoration: none;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width: 1250px;

    margin:
        30px auto;

    padding:
        0 20px 60px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    margin-bottom: 20px;
}


.page-header h1 {

    margin: 0;

    font-size: 32px;

    font-weight: 900;

    letter-spacing: -.7px;
}


.page-header p {

    margin:
        7px 0 0;

    color: #667085;

    font-size: 13px;
}


/* =========================================================
   FLIGHT BAR
========================================================= */

.flight-bar {

    background:
        rgba(255,255,255,.88);

    backdrop-filter:
        blur(14px);

    border:
        1px solid #e4e7ec;

    border-radius: 20px;

    padding:
        20px 24px;

    display: grid;

    grid-template-columns:
        1fr 1.5fr 1fr;

    align-items: center;

    gap: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 10px 35px
        rgba(16,24,40,.06);
}


.airline-name {

    font-size: 15px;

    font-weight: 900;
}


.flight-no {

    color: #667085;

    font-size: 10px;

    margin-top: 5px;
}


.route {

    text-align: center;
}


.route-codes {

    font-size: 22px;

    font-weight: 900;
}


.route-codes span {

    color: #2563eb;

    margin:
        0 13px;
}


.route-info {

    color: #667085;

    font-size: 10px;

    margin-top: 6px;
}


.base-fare {

    text-align: right;

    color: #2563eb;

    font-size: 24px;

    font-weight: 900;
}


.base-fare small {

    display: block;

    color: #98a2b3;

    font-size: 9px;

    font-weight: 600;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    display: grid;

    grid-template-columns:
        minmax(600px, 1fr)
        350px;

    gap: 20px;

    align-items: start;
}


/* =========================================================
   PANELS
========================================================= */

.seat-panel,
.summary {

    background:
        rgba(255,255,255,.92);

    backdrop-filter:
        blur(15px);

    border:
        1px solid #e4e7ec;

    border-radius: 22px;

    box-shadow:
        0 12px 40px
        rgba(16,24,40,.06);
}


/* =========================================================
   SEAT PANEL
========================================================= */

.seat-panel {

    padding: 22px;
}


.passenger {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding:
        13px 16px;

    background:
        #f8fafc;

    border:
        1px solid #eaecf0;

    border-radius: 13px;

    margin-bottom: 20px;
}


.passenger-label {

    color: #667085;

    font-size: 9px;

    font-weight: 800;
}


.passenger-name {

    margin-top: 4px;

    font-size: 14px;

    font-weight: 900;
}


.passenger-number {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-weight: 900;
}


/* =========================================================
   INVENTORY NOTICE
========================================================= */

.inventory-notice {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 10px;

    padding:
        10px 13px;

    margin-bottom: 15px;

    border-radius: 10px;

    background: #eff6ff;

    border: 1px solid #bfdbfe;

    color: #1e40af;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   LEGEND
========================================================= */

.legend {

    display: flex;

    justify-content: center;

    gap: 16px;

    flex-wrap: wrap;

    margin-bottom: 20px;
}


.legend-item {

    display: flex;

    align-items: center;

    gap: 6px;

    color: #667085;

    font-size: 9px;

    font-weight: 700;
}


.legend-box {

    width: 17px;

    height: 17px;

    border-radius: 5px;

    border: 1px solid #d0d5dd;
}


.standard {

    background: white;
}


.preferred {

    background: #eff6ff;

    border-color: #60a5fa;
}


.extra {

    background: #ecfdf3;

    border-color: #34d399;
}


.business {

    background: #fff7ed;

    border-color: #fb923c;
}


.occupied {

    background: #e5e7eb;

    border-color: #d1d5db;
}


/* =========================================================
   AIRCRAFT
========================================================= */

.aircraft {

    max-width: 650px;

    margin: auto;

    background:
        linear-gradient(
            180deg,
            #f8fafc,
            #eef2f7
        );

    border:
        1px solid #dbe2ea;

    border-radius:
        100px 100px 40px 40px;

    padding:
        32px 22px 38px;

    overflow-x: auto;

    box-shadow:
        inset 0 0 35px
        rgba(15,23,42,.04);
}


/* =========================================================
   COCKPIT
========================================================= */

.cockpit {

    width: 210px;

    height: 60px;

    margin:
        0 auto 25px;

    border-radius:
        55% 55% 15px 15px;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #e0e7ff
        );

    display: flex;

    align-items: center;

    justify-content: center;

    color: #3730a3;

    font-size: 10px;

    font-weight: 900;
}


/* =========================================================
   SEAT GRID
========================================================= */

.seat-grid {

    width: max-content;

    margin: auto;
}


.column-labels,
.seat-row {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 8px;
}


.column-label {

    width: 48px;

    text-align: center;

    color: #98a2b3;

    font-size: 9px;

    font-weight: 900;
}


.row-number {

    width: 25px;

    text-align: center;

    color: #98a2b3;

    font-size: 8px;

    font-weight: 900;
}


.aisle {

    width: 28px;

    flex-shrink: 0;
}


/* =========================================================
   SEATS
========================================================= */

.seat {

    width: 48px;

    height: 43px;

    border-radius:
        9px 9px 6px 6px;

    border:
        1px solid #cbd5e1;

    cursor: pointer;

    background: white;

    color: #344054;

    font-size: 8px;

    font-weight: 900;

    transition:
        .18s ease;

    position: relative;
}


.seat:hover:not(:disabled) {

    transform:
        translateY(-3px);

    box-shadow:
        0 7px 18px
        rgba(37,99,235,.20);

    border-color:
        #2563eb;
}


.seat.standard {

    background: white;
}


.seat.preferred {

    background: #eff6ff;

    border-color: #60a5fa;
}


.seat.extra-legroom {

    background: #ecfdf3;

    border-color: #34d399;
}


.seat.business {

    background: #fff7ed;

    border-color: #fb923c;
}


.seat.occupied {

    background: #e5e7eb;

    border-color: #d1d5db;

    color: #9ca3af;

    cursor: not-allowed;
}


.seat.selected {

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        ) !important;

    border-color:
        #1d4ed8 !important;

    color: white !important;

    transform:
        scale(1.08);

    box-shadow:
        0 9px 22px
        rgba(37,99,235,.35);
}


.seat-price {

    display: block;

    font-size: 6px;

    margin-top: 2px;

    opacity: .8;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary {

    padding: 22px;

    position: sticky;

    top: 92px;
}


.summary-title {

    font-size: 18px;

    font-weight: 900;

    margin-bottom: 18px;
}


.summary-flight {

    padding: 14px;

    border-radius: 13px;

    background: #f8fafc;

    border: 1px solid #eaecf0;

    margin-bottom: 18px;
}


.summary-flight-number {

    font-size: 10px;

    font-weight: 900;
}


.summary-route {

    margin-top: 7px;

    font-size: 15px;

    font-weight: 900;
}


.summary-date {

    margin-top: 4px;

    color: #667085;

    font-size: 9px;
}


/* =========================================================
   SELECTION
========================================================= */

.selection-box {

    padding: 16px;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #eef2ff
        );

    border:
        1px solid #dbeafe;

    margin-bottom: 18px;
}


.selection-label {

    color: #667085;

    font-size: 9px;

    font-weight: 800;
}


.selection-seat {

    margin-top: 5px;

    color: #2563eb;

    font-size: 28px;

    font-weight: 900;
}


.selection-type {

    color: #475467;

    font-size: 9px;

    margin-top: 3px;
}


/* =========================================================
   PRICE
========================================================= */

.price-title {

    font-size: 12px;

    font-weight: 900;

    margin-bottom: 10px;
}


.price-row {

    display: flex;

    justify-content: space-between;

    padding:
        9px 0;

    border-bottom:
        1px solid #f2f4f7;

    font-size: 10px;
}


.price-row span:first-child {

    color: #667085;
}


.price-row span:last-child {

    font-weight: 800;
}


.total {

    display: flex;

    justify-content: space-between;

    padding:
        17px 0;

    font-size: 17px;

    font-weight: 900;
}


.total-value {

    color: #2563eb;
}


/* =========================================================
   CONTINUE BUTTON
========================================================= */

.continue {

    width: 100%;

    height: 53px;

    border: none;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 12px;

    font-weight: 900;

    cursor: pointer;

    transition:
        .2s ease;
}


.continue:hover:not(:disabled) {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.25);
}


.continue:disabled {

    background: #cbd5e1;

    cursor: not-allowed;

    box-shadow: none;
}


/* =========================================================
   SECURITY
========================================================= */

.secure-note {

    text-align: center;

    margin-top: 12px;

    color: #98a2b3;

    font-size: 8px;

    font-weight: 700;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:950px) {

    .main {

        grid-template-columns:
            1fr;
    }


    .summary {

        position: static;
    }

}


@media(max-width:700px) {

    .flight-bar {

        grid-template-columns:
            1fr;

        text-align: center;
    }


    .base-fare {

        text-align: center;
    }


    .container {

        padding:
            0 12px 40px;
    }


    .seat-panel {

        padding: 14px;
    }


    .aircraft {

        border-radius:
            60px 60px 25px 25px;
    }

}

</style>

</head>


<body>


<header class="navbar">

    <a
        href="index.php"
        class="logo"
    >

        <span class="logo-icon">
            ✈
        </span>

        Vektor<span>Sky</span>

    </a>


    <a
        href="book.php?flight_id=<?= esc($flightId) ?>"
        class="back"
    >
        ← Passenger Details
    </a>

</header>


<main class="container">


    <div class="page-header">

        <h1>
            Select Your Seat
        </h1>

        <p>
            Choose your preferred seat for
            <?= esc($flight['flight_number']) ?>.
        </p>

    </div>


    <!-- FLIGHT INFORMATION -->

    <section class="flight-bar">

        <div>

            <div class="airline-name">
                <?= esc($flight['airline_name']) ?>
            </div>

            <div class="flight-no">

                Flight
                <?= esc($flight['flight_number']) ?>

                ·

                <?= esc($flight['model']) ?>

                ·

                <?= esc($flight['aircraft_no']) ?>

            </div>

        </div>


        <div class="route">

            <div class="route-codes">

                <?= esc($flight['source_code']) ?>

                <span>→</span>

                <?= esc($flight['destination_code']) ?>

            </div>

            <div class="route-info">

                <?= esc($flight['source_city']) ?>

                →

                <?= esc($flight['destination_city']) ?>

                ·

                <?= esc($flightDate) ?>

                ·

                <?= esc($departureTime) ?>

                -

                <?= esc($arrivalTime) ?>

            </div>

        </div>


        <div class="base-fare">

            ₹<?= number_format(
                (float)$flight['fare'],
                2
            ) ?>

            <small>
                base fare / passenger
            </small>

        </div>

    </section>


    <div class="main">


        <!-- =================================================
             SEAT PANEL
        ================================================= -->

        <section class="seat-panel">


            <div class="passenger">

                <div>

                    <div class="passenger-label">
                        PASSENGER 1
                    </div>

                    <div class="passenger-name">
                        <?= esc($passengerName) ?>
                    </div>

                </div>


                <div class="passenger-number">
                    1
                </div>

            </div>


            <div class="inventory-notice">

                <span>
                    <?= number_format($actualAircraftSeats) ?>
                    aircraft seats
                </span>

                <span>
                    <?= number_format(count($seats)) ?>
                    seats loaded
                </span>

            </div>


            <div class="legend">

                <div class="legend-item">

                    <span class="legend-box standard"></span>

                    Standard

                </div>


                <div class="legend-item">

                    <span class="legend-box preferred"></span>

                    Preferred

                </div>


                <div class="legend-item">

                    <span class="legend-box extra"></span>

                    Extra Legroom

                </div>


                <div class="legend-item">

                    <span class="legend-box business"></span>

                    Business

                </div>


                <div class="legend-item">

                    <span class="legend-box occupied"></span>

                    Occupied

                </div>

            </div>


            <div class="aircraft">


                <div class="cockpit">
                    FRONT · COCKPIT
                </div>


                <div class="seat-grid">


                    <!-- COLUMN LABELS -->

                    <div class="column-labels">

                        <div class="row-number"></div>


                        <?php foreach (
                            $seatLetters
                            as $letter
                        ): ?>

                            <?php

                            $showAisle =
                                (
                                    $letter === 'D'
                                    &&
                                    in_array(
                                        'C',
                                        $seatLetters,
                                        true
                                    )
                                );

                            ?>

                            <?php if (
                                $showAisle
                            ): ?>

                                <div
                                    class="aisle"
                                ></div>

                            <?php endif; ?>


                            <div
                                class="column-label"
                            >

                                <?= esc($letter) ?>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- SEAT ROWS -->

                    <?php foreach (
                        $seatRows
                        as $row => $rowSeats
                    ): ?>


                        <div class="seat-row">


                            <div class="row-number">

                                <?= esc($row) ?>

                            </div>


                            <?php foreach (
                                $seatLetters
                                as $letter
                            ): ?>


                                <?php

                                $showAisle =
                                    (
                                        $letter === 'D'
                                        &&
                                        in_array(
                                            'C',
                                            $seatLetters,
                                            true
                                        )
                                    );

                                ?>


                                <?php if (
                                    $showAisle
                                ): ?>

                                    <div
                                        class="aisle"
                                    ></div>

                                <?php endif; ?>


                                <?php

                                if (
                                    !isset(
                                        $rowSeats[$letter]
                                    )
                                ):

                                ?>

                                    <div
                                        style="
                                            width:48px;
                                            height:43px;
                                        "
                                    ></div>

                                    <?php
                                    continue;
                                    ?>

                                <?php endif; ?>


                                <?php

                                $seat =
                                    $rowSeats[$letter];


                                $status =
                                    strtolower(
                                        trim(
                                            $seat['status']
                                        )
                                    );


                                $seatType =
                                    strtolower(
                                        trim(
                                            $seat['seat_type']
                                        )
                                    );


                                $occupied =
                                    $status !==
                                    'available';


                                if (
                                    str_contains(
                                        $seatType,
                                        'business'
                                    )
                                ) {

                                    $cssType =
                                        'business';

                                } elseif (
                                    str_contains(
                                        $seatType,
                                        'extra'
                                    )
                                ) {

                                    $cssType =
                                        'extra-legroom';

                                } elseif (
                                    str_contains(
                                        $seatType,
                                        'preferred'
                                    )
                                ) {

                                    $cssType =
                                        'preferred';

                                } else {

                                    $cssType =
                                        'standard';
                                }

                                ?>


                                <button

                                    type="button"

                                    class="
                                        seat
                                        <?= esc($cssType) ?>

                                        <?= $occupied
                                            ? 'occupied'
                                            : ''
                                        ?>
                                    "

                                    <?= $occupied
                                        ? 'disabled'
                                        : ''
                                    ?>

                                    data-seat-id="<?= esc(
                                        $seat['seat_id']
                                    ) ?>"

                                    data-flight-seat-id="<?= esc(
                                        $seat['flight_seat_id']
                                    ) ?>"

                                    data-seat-number="<?= esc(
                                        $seat['seat_number']
                                    ) ?>"

                                    data-seat-type="<?= esc(
                                        $seat['seat_type']
                                    ) ?>"

                                    data-seat-class="<?= esc(
                                        $seat['seat_class']
                                    ) ?>"

                                    data-seat-price="<?= esc(
                                        $seat['seat_price']
                                    ) ?>"
                                >

                                    <?= esc(
                                        $seat['seat_number']
                                    ) ?>


                                    <?php if (
                                        (float)
                                        $seat['seat_price']
                                        > 0
                                    ): ?>

                                        <span
                                            class="seat-price"
                                        >

                                            +₹<?= number_format(
                                                (float)
                                                $seat['seat_price']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </button>


                            <?php endforeach; ?>


                        </div>


                    <?php endforeach; ?>


                </div>

            </div>

        </section>


        <!-- =================================================
             SUMMARY
        ================================================= -->

        <aside class="summary">


            <div class="summary-title">

                Booking Summary

            </div>


            <div class="summary-flight">

                <div class="summary-flight-number">

                    <?= esc(
                        $flight['airline_name']
                    ) ?>

                    ·

                    <?= esc(
                        $flight['flight_number']
                    ) ?>

                </div>


                <div class="summary-route">

                    <?= esc(
                        $flight['source_code']
                    ) ?>

                    →

                    <?= esc(
                        $flight['destination_code']
                    ) ?>

                </div>


                <div class="summary-date">

                    <?= esc($flightDate) ?>

                    ·

                    <?= esc($departureTime) ?>

                </div>

            </div>


            <div class="selection-box">

                <div class="selection-label">
                    SELECTED SEAT
                </div>


                <div
                    class="selection-seat"
                    id="selectedSeat"
                >
                    —
                </div>


                <div
                    class="selection-type"
                    id="selectedType"
                >
                    Select a seat
                </div>

            </div>


            <div class="price-title">
                Fare Details
            </div>


            <div class="price-row">

                <span>
                    Flight fare
                </span>

                <span>

                    ₹<span id="flightFare">

                        <?= number_format(
                            (float)
                            $flight['fare'],
                            2
                        ) ?>

                    </span>

                </span>

            </div>


            <div class="price-row">

                <span>
                    Seat selection
                </span>

                <span>

                    ₹<span id="seatFare">
                        0.00
                    </span>

                </span>

            </div>


            <div class="price-row">

                <span>
                    Taxes & fees
                </span>

                <span>

                    ₹<span id="taxes">
                        <?= number_format(
                            (float)$flight['fare']
                            * 0.05,
                            2
                        ) ?>
                    </span>

                </span>

            </div>


            <div class="total">

                <span>
                    Total
                </span>

                <span class="total-value">

                    ₹<span id="totalFare">

                        <?= number_format(
                            (float)$flight['fare']
                            * 1.05,
                            2
                        ) ?>

                    </span>

                </span>

            </div>


            <form
                method="POST"
                action="payment.php"
                id="seatForm"
            >

                <input
                    type="hidden"
                    name="flight_id"
                    value="<?= esc($flightId) ?>"
                >


                <input
                    type="hidden"
                    name="passenger_id"
                    value="<?= esc($passengerId) ?>"
                >


                <input
                    type="hidden"
                    name="seat_id"
                    id="seatId"
                >


                <input
                    type="hidden"
                    name="flight_seat_id"
                    id="flightSeatId"
                >


                <input
                    type="hidden"
                    name="seat_number"
                    id="seatNumber"
                >


                <input
                    type="hidden"
                    name="seat_price"
                    id="seatPrice"
                >


                <input
                    type="hidden"
                    name="total_amount"
                    id="totalAmount"
                >


                <button
                    type="submit"
                    class="continue"
                    id="continueButton"
                    disabled
                >

                    Continue to Payment →

                </button>

            </form>


            <div class="secure-note">

                🔒 Secure booking · Your payment is protected

            </div>


        </aside>


    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

const baseFare =
    <?= json_encode(
        (float)$flight['fare']
    ) ?>;

const taxRate = 0.05;


/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const seats =
    document.querySelectorAll(
        '.seat:not(.occupied)'
    );


const selectedSeat =
    document.getElementById(
        'selectedSeat'
    );


const selectedType =
    document.getElementById(
        'selectedType'
    );


const seatFare =
    document.getElementById(
        'seatFare'
    );


const taxes =
    document.getElementById(
        'taxes'
    );


const totalFare =
    document.getElementById(
        'totalFare'
    );


const totalAmount =
    document.getElementById(
        'totalAmount'
    );


const continueButton =
    document.getElementById(
        'continueButton'
    );


const seatId =
    document.getElementById(
        'seatId'
    );


const flightSeatId =
    document.getElementById(
        'flightSeatId'
    );


const seatNumber =
    document.getElementById(
        'seatNumber'
    );


const seatPrice =
    document.getElementById(
        'seatPrice'
    );


/*
|--------------------------------------------------------------------------
| SELECT SEAT
|--------------------------------------------------------------------------
*/

seats.forEach(
    function(seat) {

        seat.addEventListener(
            'click',
            function() {


                /*
                | Remove old selection
                */

                document
                    .querySelectorAll(
                        '.seat.selected'
                    )
                    .forEach(
                        function(oldSeat) {

                            oldSeat.classList
                                .remove(
                                    'selected'
                                );
                        }
                    );


                /*
                | Select new seat
                */

                seat.classList.add(
                    'selected'
                );


                /*
                | Read database values
                */

                const id =
                    seat.dataset.seatId;


                const flightSeat =
                    seat.dataset.flightSeatId;


                const number =
                    seat.dataset.seatNumber;


                const type =
                    seat.dataset.seatType;


                const price =
                    parseFloat(
                        seat.dataset.seatPrice
                    ) || 0;


                /*
                | Calculate fare
                */

                const subtotal =
                    baseFare + price;


                const tax =
                    subtotal * taxRate;


                const total =
                    subtotal + tax;


                /*
                | Display
                */

                selectedSeat.textContent =
                    number;


                selectedType.textContent =
                    type;


                seatFare.textContent =
                    price.toFixed(2);


                taxes.textContent =
                    tax.toFixed(2);


                totalFare.textContent =
                    total.toFixed(2);


                /*
                | Hidden values
                */

                seatId.value =
                    id;


                flightSeatId.value =
                    flightSeat;


                seatNumber.value =
                    number;


                seatPrice.value =
                    price.toFixed(2);


                totalAmount.value =
                    total.toFixed(2);


                /*
                | Enable payment button
                */

                continueButton.disabled =
                    false;

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| FORM VALIDATION
|--------------------------------------------------------------------------
*/

document
    .getElementById('seatForm')
    .addEventListener(
        'submit',
        function(event) {

            if (!seatId.value) {

                event.preventDefault();

                alert(
                    'Please select a seat before continuing.'
                );

                return;
            }


            if (!flightSeatId.value) {

                event.preventDefault();

                alert(
                    'Invalid flight seat.'
                );

                return;
            }


            if (!seatNumber.value) {

                event.preventDefault();

                alert(
                    'Seat number is missing.'
                );

                return;
            }


            if (!seatPrice.value) {

                event.preventDefault();

                alert(
                    'Seat price is missing.'
                );

                return;
            }

        }
    );

</script>


</body>

</html>