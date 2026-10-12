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

function h($value)
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
| FLIGHT ID
|--------------------------------------------------------------------------
*/

$flightId = 0;

if (isset($_GET['flight_id'])) {

    $flightId =
        (int)$_GET['flight_id'];

}

if (
    $flightId <= 0
    &&
    isset($_POST['flight_id'])
) {

    $flightId =
        (int)$_POST['flight_id'];

}

if (
    $flightId <= 0
    &&
    isset($_SESSION['booking_flight_id'])
) {

    $flightId =
        (int)$_SESSION['booking_flight_id'];

}


if ($flightId <= 0) {

    die('
        <div style="
            font-family:Arial;
            max-width:600px;
            margin:100px auto;
            text-align:center;
            padding:40px;
        ">

            <h2 style="color:#dc2626;">
                Invalid flight selected
            </h2>

            <p style="color:#64748b;">
                Flight information was not received.
            </p>

            <a
                href="index.php"
                style="
                    display:inline-block;
                    margin-top:20px;
                    padding:12px 22px;
                    background:#2563eb;
                    color:#fff;
                    text-decoration:none;
                    border-radius:10px;
                "
            >
                Back to Flight Search
            </a>

        </div>
    ');

}


$_SESSION['booking_flight_id'] =
    $flightId;


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

        src.airport_code AS source_code,
        src.city AS source_city,

        dst.airport_code AS destination_code,
        dst.city AS destination_city,

        ac.model,
        ac.aircraft_no,
        ac.total_seats

    FROM flight f

    INNER JOIN airport src
        ON
            f.source_airport_id
            =
            src.airport_id

    INNER JOIN airport dst
        ON
            f.destination_airport_id
            =
            dst.airport_id

    INNER JOIN aircraft ac
        ON
            f.aircraft_id
            =
            ac.aircraft_id

    WHERE
        f.flight_id = ?

    LIMIT 1

");

$stmt->execute([
    $flightId
]);

$flight =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$flight) {

    die('
        <div style="
            font-family:Arial;
            text-align:center;
            padding:100px;
        ">

            <h2>
                Flight not found.
            </h2>

            <a href="index.php">
                Back to Search
            </a>

        </div>
    ');

}


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

        s.seat_number,
        s.seat_class,
        s.seat_type,
        s.seat_price

    FROM flight_seat fs

    INNER JOIN seat s
        ON
            fs.seat_id =
            s.seat_id

    WHERE
        fs.flight_id = ?

        AND

        s.aircraft_id = ?

    ORDER BY

        CAST(
            REGEXP_REPLACE(
                s.seat_number,
                '[^0-9]',
                ''
            )
            AS UNSIGNED
        ),

        s.seat_number

");

$stmt->execute([
    $flightId,
    $flight['aircraft_id']
]);

$seats =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SEAT COUNTS
|--------------------------------------------------------------------------
*/

$totalSeatCount =
    count($seats);

$availableSeatCount = 0;

$bookedSeatCount = 0;


foreach (
    $seats as $seatStatusRow
) {

    $seatStatus =
        strtolower(
            trim(
                (string)
                $seatStatusRow['status']
            )
        );


    if (
        $seatStatus ===
        'available'
    ) {

        $availableSeatCount++;

    } else {

        $bookedSeatCount++;

    }

}


/*
|--------------------------------------------------------------------------
| GROUP SEATS BY ROW
|--------------------------------------------------------------------------
*/

$seatRows = [];

$seatLetters = [];


foreach (
    $seats as $seat
) {

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
    function (
        $a,
        $b
    ) {

        return
            (int)$a
            <=>
            (int)$b;

    }
);


/*
|--------------------------------------------------------------------------
| SORT LETTERS
|--------------------------------------------------------------------------
*/

$seatLetters =
    array_values(
        $seatLetters
    );

usort(
    $seatLetters,
    function (
        $a,
        $b
    ) {

        return strcmp(
            $a,
            $b
        );

    }
);


/*
|--------------------------------------------------------------------------
| FLIGHT DATE/TIME
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

$passengerName =
    'Passenger 1';

$passenger = [];


$stmt = $pdo->prepare("

    SELECT

        name,
        email,
        phone,
        passport_no,
        date_of_birth,
        gender

    FROM passenger

    WHERE
        passenger_id = ?

    LIMIT 1

");

$stmt->execute([
    $passengerId
]);

$passenger =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (
    $passenger
    &&
    !empty(
        $passenger['name']
    )
) {

    $passengerName =
        $passenger['name'];

}


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
    Vector Sky | Seat Selection
</title>


<style>

* {
    box-sizing:border-box;
}


body {

    margin:0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:#f3f6fa;

    color:#101828;

}


.navbar {

    height:72px;

    background:#fff;

    border-bottom:
        1px solid #e5e7eb;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 5%;

}


.logo {

    text-decoration:none;

    color:#101828;

    font-size:22px;

    font-weight:900;

    display:flex;

    align-items:center;

    gap:10px;

}


.logo-icon {

    width:42px;

    height:42px;

    border-radius:13px;

    display:flex;

    align-items:center;

    justify-content:center;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

}


.logo span {
    color:#2563eb;
}


.back {

    color:#475467;

    text-decoration:none;

    font-size:12px;

    font-weight:800;

}


.container {

    max-width:1250px;

    margin:28px auto;

    padding:0 20px;

}


.page-header {

    margin-bottom:18px;

}


.page-header h1 {

    margin:0;

    font-size:30px;

    font-weight:900;

}


.page-header p {

    margin-top:6px;

    color:#667085;

    font-size:12px;

}


/*
|--------------------------------------------------------------------------
| FLIGHT BAR
|--------------------------------------------------------------------------
*/

.flight-bar {

    background:#fff;

    border:1px solid #e4e7ec;

    border-radius:18px;

    padding:18px 22px;

    display:grid;

    grid-template-columns:
        1fr 1.5fr 1fr;

    align-items:center;

    gap:20px;

    margin-bottom:18px;

    box-shadow:
        0 8px 25px
        rgba(16,24,40,.05);

}


.airline-name {

    font-size:14px;

    font-weight:900;

}


.flight-no {

    color:#667085;

    font-size:10px;

    margin-top:4px;

}


.route {

    text-align:center;

}


.route-codes {

    font-size:20px;

    font-weight:900;

}


.route-codes span {

    color:#2563eb;

    margin:0 12px;

}


.route-info {

    color:#667085;

    font-size:10px;

    margin-top:5px;

}


.base-fare {

    text-align:right;

    color:#2563eb;

    font-size:23px;

    font-weight:900;

}


.base-fare small {

    display:block;

    color:#98a2b3;

    font-size:9px;

}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.main {

    display:grid;

    grid-template-columns:
        minmax(600px,1fr)
        350px;

    gap:18px;

    align-items:start;

}


/*
|--------------------------------------------------------------------------
| SEAT PANEL
|--------------------------------------------------------------------------
*/

.seat-panel {

    background:#fff;

    border:1px solid #e4e7ec;

    border-radius:20px;

    padding:22px;

    box-shadow:
        0 8px 30px
        rgba(16,24,40,.05);

}


/*
|--------------------------------------------------------------------------
| PASSENGER
|--------------------------------------------------------------------------
*/

.passenger {

    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:12px 15px;

    background:#f8fafc;

    border:1px solid #eaecf0;

    border-radius:12px;

    margin-bottom:18px;

}


.passenger-label {

    color:#667085;

    font-size:9px;

    font-weight:800;

}


.passenger-name {

    margin-top:3px;

    font-size:13px;

    font-weight:900;

}


.passenger-number {

    width:36px;

    height:36px;

    border-radius:50%;

    background:#2563eb;

    color:#fff;

    display:flex;

    align-items:center;

    justify-content:center;

    font-weight:900;

}


/*
|--------------------------------------------------------------------------
| AVAILABILITY
|--------------------------------------------------------------------------
*/

.availability {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:18px;

    padding:12px 15px;

    border-radius:12px;

    background:#f8fafc;

    border:1px solid #eaecf0;

}


.availability-label {

    font-size:9px;

    color:#667085;

    font-weight:800;

}


.availability-value {

    margin-top:4px;

    font-size:14px;

    font-weight:900;

    color:#101828;

}


.booked-count {

    text-align:right;

}


.booked-count .availability-value {

    color:#6b7280;

}


/*
|--------------------------------------------------------------------------
| LEGEND
|--------------------------------------------------------------------------
*/

.legend {

    display:flex;

    justify-content:center;

    gap:16px;

    flex-wrap:wrap;

    margin-bottom:20px;

}


.legend-item {

    display:flex;

    align-items:center;

    gap:6px;

    color:#667085;

    font-size:9px;

    font-weight:700;

}


.legend-box {

    width:17px;

    height:17px;

    border-radius:5px;

    border:1px solid #d0d5dd;

}


.standard {

    background:#fff;

}


.preferred {

    background:#eff6ff;

    border-color:#60a5fa;

}


.extra {

    background:#ecfdf3;

    border-color:#34d399;

}


.business {

    background:#fff7ed;

    border-color:#fb923c;

}


.occupied {

    background:#e5e7eb;

    border-color:#9ca3af;

}


/*
|--------------------------------------------------------------------------
| AIRCRAFT
|--------------------------------------------------------------------------
*/

.aircraft {

    max-width:620px;

    margin:auto;

    background:
        linear-gradient(
            180deg,
            #f8fafc,
            #eef2f7
        );

    border:1px solid #dbe2ea;

    border-radius:
        90px 90px 35px 35px;

    padding:30px 22px 35px;

    overflow-x:auto;

    box-shadow:
        inset 0 0 30px
        rgba(15,23,42,.04);

}


.cockpit {

    width:210px;

    height:60px;

    margin:0 auto 25px;

    border-radius:
        55% 55% 15px 15px;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #e0e7ff
        );

    display:flex;

    align-items:center;

    justify-content:center;

    color:#3730a3;

    font-size:10px;

    font-weight:900;

}


/*
|--------------------------------------------------------------------------
| SEAT GRID
|--------------------------------------------------------------------------
*/

.seat-grid {

    width:max-content;

    margin:auto;

}


.column-labels,
.seat-row {

    display:flex;

    align-items:center;

    gap:8px;

    margin-bottom:8px;

}


.column-labels {

    margin-bottom:10px;

}


.column-label {

    width:48px;

    text-align:center;

    color:#98a2b3;

    font-size:9px;

    font-weight:900;

}


.row-number {

    width:25px;

    text-align:center;

    color:#98a2b3;

    font-size:8px;

    font-weight:900;

}


.aisle {

    width:28px;

}


/*
|--------------------------------------------------------------------------
| SEAT
|--------------------------------------------------------------------------
*/

.seat {

    width:48px;

    height:43px;

    border-radius:
        9px 9px 6px 6px;

    border:1px solid #cbd5e1;

    cursor:pointer;

    background:#fff;

    color:#344054;

    font-size:8px;

    font-weight:900;

    transition:.15s;

    position:relative;

}


.seat:hover:not(:disabled) {

    transform:
        translateY(-2px);

    box-shadow:
        0 5px 14px
        rgba(37,99,235,.2);

    border-color:#2563eb;

}


.seat.standard {

    background:#fff;

}


.seat.preferred {

    background:#eff6ff;

    border-color:#60a5fa;

}


.seat.extra-legroom {

    background:#ecfdf3;

    border-color:#34d399;

}


.seat.business {

    background:#fff7ed;

    border-color:#fb923c;

}


/*
|--------------------------------------------------------------------------
| BOOKED SEAT
|--------------------------------------------------------------------------
*/

.seat.occupied {

    background:#e5e7eb !important;

    border:2px solid #9ca3af !important;

    color:#6b7280 !important;

    cursor:not-allowed !important;

    opacity:1;

    position:relative;

    box-shadow:none !important;

}


.seat.occupied::after {

    content:"BOOKED";

    position:absolute;

    left:50%;

    bottom:3px;

    transform:
        translateX(-50%);

    font-size:5px;

    line-height:1;

    font-weight:900;

    color:#6b7280;

    letter-spacing:.3px;

    pointer-events:none;

}


.seat.occupied:hover {

    transform:none !important;

    background:#e5e7eb !important;

    border-color:#9ca3af !important;

    box-shadow:none !important;

}


/*
|--------------------------------------------------------------------------
| SELECTED
|--------------------------------------------------------------------------
*/

.seat.selected {

    background:#2563eb !important;

    border-color:#1d4ed8 !important;

    color:#fff !important;

    transform:scale(1.08);

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.35);

}


.seat-price {

    display:block;

    font-size:6px;

    margin-top:2px;

    opacity:.8;

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.summary {

    background:#fff;

    border:1px solid #e4e7ec;

    border-radius:20px;

    padding:22px;

    position:sticky;

    top:20px;

    box-shadow:
        0 8px 30px
        rgba(16,24,40,.05);

}


.summary-title {

    font-size:18px;

    font-weight:900;

    margin-bottom:18px;

}


.summary-flight {

    padding:14px;

    border-radius:12px;

    background:#f8fafc;

    border:1px solid #eaecf0;

    margin-bottom:18px;

}


.summary-flight-number {

    font-size:10px;

    font-weight:900;

}


.summary-route {

    margin-top:7px;

    font-size:14px;

    font-weight:900;

}


.summary-date {

    margin-top:4px;

    color:#667085;

    font-size:9px;

}


.selection-box {

    padding:16px;

    border-radius:13px;

    background:#eff6ff;

    margin-bottom:18px;

}


.selection-label {

    color:#667085;

    font-size:9px;

    font-weight:800;

}


.selection-seat {

    margin-top:5px;

    color:#2563eb;

    font-size:27px;

    font-weight:900;

}


.selection-type {

    color:#475467;

    font-size:9px;

    margin-top:3px;

}


/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

.price-title {

    font-size:12px;

    font-weight:900;

    margin-bottom:10px;

}


.price-row {

    display:flex;

    justify-content:space-between;

    padding:9px 0;

    border-bottom:
        1px solid #f2f4f7;

    font-size:10px;

}


.price-row span:first-child {

    color:#667085;

}


.price-row span:last-child {

    font-weight:800;

}


.total {

    display:flex;

    justify-content:space-between;

    padding:17px 0;

    font-size:17px;

    font-weight:900;

}


.total-value {

    color:#2563eb;

}


/*
|--------------------------------------------------------------------------
| CONTINUE
|--------------------------------------------------------------------------
*/

.continue {

    width:100%;

    height:52px;

    border:none;

    border-radius:12px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color:#fff;

    font-size:12px;

    font-weight:900;

    cursor:pointer;

    transition:.15s;

}


.continue:hover:not(:disabled) {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 22px
        rgba(37,99,235,.25);

}


.continue:disabled {

    background:#cbd5e1;

    cursor:not-allowed;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media(max-width:950px) {

    .main {

        grid-template-columns:1fr;

    }


    .summary {

        position:static;

    }

}


@media(max-width:700px) {

    .flight-bar {

        grid-template-columns:1fr;

        text-align:center;

    }


    .base-fare {

        text-align:center;

    }


    .availability {

        flex-direction:column;

        align-items:flex-start;

    }


    .booked-count {

        text-align:left;

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
            &#9992;
        </span>

        Vector<span>Sky</span>

    </a>


    <a
        href="book.php?flight_id=<?= h($flightId) ?>"
        class="back"
    >

        &larr; Passenger Details

    </a>

</header>


<main class="container">


    <div class="page-header">

        <h1>
            Select Your Seat
        </h1>

        <p>

            Choose your preferred seat for
            <?= h($flight['flight_number']) ?>.

        </p>

    </div>


    <!-- FLIGHT -->

    <section class="flight-bar">


        <div>

            <div class="airline-name">

                <?= h(
                    $flight['airline_name']
                ) ?>

            </div>


            <div class="flight-no">

                Flight

                <?= h(
                    $flight['flight_number']
                ) ?>

                &middot;

                <?= h(
                    $flight['model']
                ) ?>

            </div>

        </div>


        <div class="route">

            <div class="route-codes">

                <?= h(
                    $flight['source_code']
                ) ?>

                <span>
                    &rarr;
                </span>

                <?= h(
                    $flight['destination_code']
                ) ?>

            </div>


            <div class="route-info">

                <?= h(
                    $flight['source_city']
                ) ?>

                &rarr;

                <?= h(
                    $flight['destination_city']
                ) ?>

                &middot;

                <?= h(
                    $flightDate
                ) ?>

                &middot;

                <?= h(
                    $departureTime
                ) ?>

                -

                <?= h(
                    $arrivalTime
                ) ?>

            </div>

        </div>


        <div class="base-fare">

            &#8377;<?= number_format(
                (float)$flight['fare'],
                2
            ) ?>

            <small>
                base fare / passenger
            </small>

        </div>

    </section>


    <div class="main">


        <!-- SEAT MAP -->

        <section class="seat-panel">


            <div class="passenger">

                <div>

                    <div class="passenger-label">
                        PASSENGER 1
                    </div>


                    <div class="passenger-name">

                        <?= h(
                            $passengerName
                        ) ?>

                    </div>

                </div>


                <div class="passenger-number">
                    1
                </div>

            </div>


            <!-- AVAILABILITY -->

            <div class="availability">


                <div>

                    <div class="availability-label">

                        SEAT AVAILABILITY

                    </div>


                    <div class="availability-value">

                        <?= h(
                            $availableSeatCount
                        ) ?>

                        seats available

                    </div>

                </div>


                <div class="booked-count">

                    <div class="availability-label">

                        ALREADY BOOKED

                    </div>


                    <div class="availability-value">

                        <?= h(
                            $bookedSeatCount
                        ) ?>

                        seats

                    </div>

                </div>


            </div>


            <!-- LEGEND -->

            <div class="legend">


                <div class="legend-item">

                    <span
                        class="legend-box standard"
                    ></span>

                    Standard

                </div>


                <div class="legend-item">

                    <span
                        class="legend-box preferred"
                    ></span>

                    Preferred

                </div>


                <div class="legend-item">

                    <span
                        class="legend-box extra"
                    ></span>

                    Extra Legroom

                </div>


                <div class="legend-item">

                    <span
                        class="legend-box business"
                    ></span>

                    Business

                </div>


                <div class="legend-item">

                    <span
                        class="legend-box occupied"
                    ></span>

                    Booked

                </div>


            </div>


            <!-- AIRCRAFT -->

            <div class="aircraft">


                <div class="cockpit">

                    FRONT &middot; COCKPIT

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

                                <?= h(
                                    $letter
                                ) ?>

                            </div>


                        <?php endforeach; ?>


                    </div>


                    <!-- SEATS -->

                    <?php foreach (
                        $seatRows
                        as $row => $rowSeats
                    ): ?>


                        <div class="seat-row">


                            <div
                                class="row-number"
                            >

                                <?= h(
                                    $row
                                ) ?>

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
                                        $rowSeats[
                                            $letter
                                        ]
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

                                    endif;


                                    $seat =
                                        $rowSeats[
                                            $letter
                                        ];


                                    $status =
                                        strtolower(
                                            trim(
                                                (string)
                                                $seat[
                                                    'status'
                                                ]
                                            )
                                        );


                                    $type =
                                        strtolower(
                                            trim(
                                                (string)
                                                $seat[
                                                    'seat_type'
                                                ]
                                            )
                                        );


                                    /*
                                    |--------------------------------------------------------------------------
                                    | ANY NON-AVAILABLE STATUS = BOOKED
                                    |--------------------------------------------------------------------------
                                    */

                                    $occupied =
                                        (
                                            $status
                                            !==
                                            'available'
                                        );


                                    if (
                                        str_contains(
                                            $type,
                                            'business'
                                        )
                                    ) {

                                        $cssType =
                                            'business';

                                    } elseif (
                                        str_contains(
                                            $type,
                                            'extra'
                                        )
                                    ) {

                                        $cssType =
                                            'extra-legroom';

                                    } elseif (
                                        str_contains(
                                            $type,
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
                                        <?= h(
                                            $cssType
                                        ) ?>

                                        <?= $occupied
                                            ? 'occupied'
                                            : ''
                                        ?>
                                    "

                                    <?= $occupied
                                        ? 'disabled'
                                        : ''
                                    ?>

                                    title="<?= h(
                                        $occupied

                                        ? 'Seat '
                                            .
                                            $seat[
                                                'seat_number'
                                            ]
                                            .
                                            ' is already booked'

                                        : 'Select seat '
                                            .
                                            $seat[
                                                'seat_number'
                                            ]
                                    ) ?>"

                                    data-seat-id="<?= h(
                                        $seat[
                                            'seat_id'
                                        ]
                                    ) ?>"

                                    data-flight-seat-id="<?= h(
                                        $seat[
                                            'flight_seat_id'
                                        ]
                                    ) ?>"

                                    data-seat-number="<?= h(
                                        $seat[
                                            'seat_number'
                                        ]
                                    ) ?>"

                                    data-seat-type="<?= h(
                                        $seat[
                                            'seat_type'
                                        ]
                                    ) ?>"

                                    data-seat-class="<?= h(
                                        $seat[
                                            'seat_class'
                                        ]
                                    ) ?>"

                                    data-seat-price="<?= h(
                                        $seat[
                                            'seat_price'
                                        ]
                                    ) ?>"
                                >


                                    <?php if (
                                        $occupied
                                    ): ?>

                                        <span
                                            style="
                                                display:block;
                                                font-size:8px;
                                                line-height:1;
                                                font-weight:900;
                                            "
                                        >

                                            <?= h(
                                                $seat[
                                                    'seat_number'
                                                ]
                                            ) ?>

                                        </span>


                                    <?php else: ?>


                                        <?= h(
                                            $seat[
                                                'seat_number'
                                            ]
                                        ) ?>


                                    <?php endif; ?>


                                    <?php if (
                                        !$occupied
                                        &&
                                        (float)
                                        $seat[
                                            'seat_price'
                                        ]
                                        > 0
                                    ): ?>


                                        <span
                                            class="seat-price"
                                        >

                                            +&#8377;<?= number_format(
                                                (float)
                                                $seat[
                                                    'seat_price'
                                                ]
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


        <!-- SUMMARY -->

        <aside class="summary">


            <div class="summary-title">

                Booking Summary

            </div>


            <div class="summary-flight">


                <div
                    class="summary-flight-number"
                >

                    <?= h(
                        $flight[
                            'airline_name'
                        ]
                    ) ?>

                    &middot;

                    <?= h(
                        $flight[
                            'flight_number'
                        ]
                    ) ?>

                </div>


                <div
                    class="summary-route"
                >

                    <?= h(
                        $flight[
                            'source_code'
                        ]
                    ) ?>

                    &rarr;

                    <?= h(
                        $flight[
                            'destination_code'
                        ]
                    ) ?>

                </div>


                <div
                    class="summary-date"
                >

                    <?= h(
                        $flightDate
                    ) ?>

                    &middot;

                    <?= h(
                        $departureTime
                    ) ?>

                </div>


            </div>


            <div class="selection-box">


                <div
                    class="selection-label"
                >

                    SELECTED SEAT

                </div>


                <div
                    class="selection-seat"
                    id="selectedSeat"
                >

                    &mdash;

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

                    &#8377;<span
                        id="flightFare"
                    >

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

                    &#8377;<span
                        id="seatFare"
                    >
                        0.00
                    </span>

                </span>

            </div>


            <div class="price-row">

                <span>
                    Taxes &amp; fees
                </span>

                <span>

                    &#8377;<span
                        id="taxes"
                    >
                        0.00
                    </span>

                </span>

            </div>


            <div class="total">

                <span>
                    Total
                </span>

                <span class="total-value">

                    &#8377;<span
                        id="totalFare"
                    >

                        <?= number_format(
                            (float)
                            $flight['fare'],
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
                    value="<?= h(
                        $flightId
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="passenger_id"
                    value="<?= h(
                        $passengerId
                    ) ?>"
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

                    Continue to Payment &rarr;

                </button>


            </form>


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
| SELECT AVAILABLE SEAT
|--------------------------------------------------------------------------
*/

seats.forEach(
    function(seat) {

        seat.addEventListener(
            'click',
            function() {


                /*
                |--------------------------------------------------------------------------
                | Remove previous selection
                |--------------------------------------------------------------------------
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
                |--------------------------------------------------------------------------
                | Select current seat
                |--------------------------------------------------------------------------
                */

                seat.classList.add(
                    'selected'
                );


                /*
                |--------------------------------------------------------------------------
                | GET DATA
                |--------------------------------------------------------------------------
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
                    )
                    || 0;


                /*
                |--------------------------------------------------------------------------
                | CALCULATE
                |--------------------------------------------------------------------------
                */

                const subtotal =
                    baseFare + price;


                const tax =
                    subtotal * taxRate;


                const total =
                    subtotal + tax;


                /*
                |--------------------------------------------------------------------------
                | DISPLAY
                |--------------------------------------------------------------------------
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
                |--------------------------------------------------------------------------
                | HIDDEN FIELDS
                |--------------------------------------------------------------------------
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
                |--------------------------------------------------------------------------
                | ENABLE PAYMENT BUTTON
                |--------------------------------------------------------------------------
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
    .getElementById(
        'seatForm'
    )
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

        }
    );

</script>


</body>

</html>