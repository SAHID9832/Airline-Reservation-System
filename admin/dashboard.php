<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$page_title = 'Admin Dashboard';
$asset_prefix = '../';


/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | TOTAL BOOKINGS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM booking
        WHERE status <> 'Cancelled'
    ");

    $totalBookings = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL REVENUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM payment
        WHERE payment_status = 'Success'
    ");

    $totalRevenue = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL PASSENGERS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM passenger
    ");

    $totalPassengers = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL FLIGHTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM flight
    ");

    $totalFlights = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | AVERAGE FARE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COALESCE(AVG(fare), 0)
        FROM flight
    ");

    $avgFare = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | CONFIRMED BOOKINGS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM booking
        WHERE status = 'Confirmed'
    ");

    $confirmedBookings = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PENDING BOOKINGS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM booking
        WHERE status = 'Pending'
    ");

    $pendingBookings = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | CANCELLED BOOKINGS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM booking
        WHERE status = 'Cancelled'
    ");

    $cancelledBookings = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | SUCCESSFUL PAYMENTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM payment
        WHERE payment_status = 'Success'
    ");

    $successfulPayments = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | BOOKINGS PER FLIGHT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT

            f.flight_id,

            f.flight_number,

            f.airline_name,

            src.airport_code AS source_code,

            dst.airport_code AS destination_code,

            COUNT(
                CASE
                    WHEN b.status <> 'Cancelled'
                    THEN b.booking_id
                END
            ) AS bookings

        FROM flight f

        LEFT JOIN airport src
            ON f.source_airport_id = src.airport_id

        LEFT JOIN airport dst
            ON f.destination_airport_id = dst.airport_id

        LEFT JOIN booking b
            ON b.flight_id = f.flight_id

        GROUP BY
            f.flight_id,
            f.flight_number,
            f.airline_name,
            src.airport_code,
            dst.airport_code

        ORDER BY bookings DESC

        LIMIT 5
    ");

    $perFlight = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | RECENT BOOKINGS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT

            b.booking_id,

            b.pnr,

            b.status,

            b.total_amount,

            b.booking_date,

            p.name AS passenger_name,

            f.flight_number,

            src.airport_code AS source_code,

            dst.airport_code AS destination_code,

            s.seat_number

        FROM booking b

        LEFT JOIN passenger p
            ON b.passenger_id = p.passenger_id

        LEFT JOIN flight f
            ON b.flight_id = f.flight_id

        LEFT JOIN airport src
            ON f.source_airport_id = src.airport_id

        LEFT JOIN airport dst
            ON f.destination_airport_id = dst.airport_id

        LEFT JOIN seat s
            ON b.seat_id = s.seat_id

        ORDER BY
            b.booking_id DESC

        LIMIT 6
    ");

    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | UPCOMING FLIGHTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT

            f.flight_id,

            f.flight_number,

            f.airline_name,

            f.departure_time,

            f.arrival_time,

            f.fare,

            src.airport_code AS source_code,

            dst.airport_code AS destination_code,

            ac.model

        FROM flight f

        LEFT JOIN airport src
            ON f.source_airport_id = src.airport_id

        LEFT JOIN airport dst
            ON f.destination_airport_id = dst.airport_id

        LEFT JOIN aircraft ac
            ON f.aircraft_id = ac.aircraft_id

        WHERE f.departure_time >= NOW()

        ORDER BY
            f.departure_time ASC

        LIMIT 6
    ");

    $upcomingFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | ABOVE AVERAGE FARES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT

            flight_number,

            airline_name,

            fare

        FROM flight

        WHERE fare > (
            SELECT AVG(fare)
            FROM flight
        )

        ORDER BY fare DESC

        LIMIT 5
    ");

    $aboveAvg = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    die(
        'Dashboard database error: ' .
        h($e->getMessage())
    );

}


/*
|--------------------------------------------------------------------------
| ADMIN NAME
|--------------------------------------------------------------------------
*/

$adminName = 'Administrator';

if (!empty($_SESSION['admin_username'])) {

    $adminName =
        $_SESSION['admin_username'];

} elseif (!empty($_SESSION['username'])) {

    $adminName =
        $_SESSION['username'];

}

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
    VektorSky Admin Dashboard
</title>


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

    font-family:
        Inter,
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;

    background:
        #f4f7fc;

    color:
        #172b4d;
}


/* =========================================================
   PAGE
========================================================= */

.admin-page {

    min-height: 100vh;

    padding:
        30px 35px 60px;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 28px;

    gap: 20px;
}


.brand-area {

    display: flex;

    align-items: center;

    gap: 13px;
}


.brand-logo {

    width: 48px;

    height: 48px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #087cff,
            #15b8ff
        );

    color: white;

    font-size: 23px;

    box-shadow:
        0 10px 25px
        rgba(8,124,255,.25);

    animation:
        floating 4s ease-in-out infinite;
}


@keyframes floating {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-4px);
    }
}


.brand-text strong {

    display: block;

    font-size: 19px;

    font-weight: 900;

    letter-spacing: -.5px;
}


.brand-text span {

    display: block;

    margin-top: 2px;

    color: #718096;

    font-size: 9px;

    letter-spacing: 1.5px;

    text-transform: uppercase;

    font-weight: 700;
}


.admin-user {

    display: flex;

    align-items: center;

    gap: 11px;

    background: white;

    border: 1px solid #e3eaf4;

    padding:
        8px 13px;

    border-radius: 12px;

    box-shadow:
        0 5px 18px
        rgba(31,60,100,.05);
}


.admin-avatar {

    width: 35px;

    height: 35px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #1769ff,
            #5939e8
        );

    color: white;

    font-size: 12px;

    font-weight: 900;
}


.admin-user-text strong {

    display: block;

    color: #142850;

    font-size: 10px;
}


.admin-user-text span {

    display: block;

    margin-top: 2px;

    color: #718096;

    font-size: 8px;
}


/* =========================================================
   WELCOME
========================================================= */

.welcome {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 23px;
}


.welcome h1 {

    margin: 0;

    color: #102a56;

    font-size: 29px;

    font-weight: 900;

    letter-spacing: -.9px;
}


.welcome p {

    margin:
        6px 0 0;

    color: #718096;

    font-size: 12px;
}


.welcome-date {

    padding:
        11px 15px;

    border:
        1px solid #dfe7f2;

    border-radius: 10px;

    background: white;

    color: #4a5b78;

    font-size: 10px;

    font-weight: 700;

    box-shadow:
        0 5px 18px
        rgba(31,60,100,.04);
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.quick-actions {

    display: flex;

    gap: 8px;

    margin-bottom: 23px;

    flex-wrap: wrap;
}


.quick-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        10px 15px;

    border-radius: 9px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 800;

    transition:
        .2s ease;
}


.quick-btn:hover {

    transform:
        translateY(-2px);

    text-decoration: none;
}


.quick-primary {

    background:
        linear-gradient(
            90deg,
            #1677ff,
            #2563eb
        );

    color: white !important;

    box-shadow:
        0 8px 18px
        rgba(22,119,255,.2);
}


.quick-secondary {

    background: white;

    border:
        1px solid #dce5f1;

    color: #344054 !important;
}


/* =========================================================
   STAT GRID
========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 17px;

    margin-bottom: 20px;
}


.stat-card {

    position: relative;

    overflow: hidden;

    padding:
        20px;

    min-height: 145px;

    background:
        rgba(255,255,255,.96);

    border:
        1px solid #e2e9f3;

    border-radius: 16px;

    box-shadow:
        0 8px 30px
        rgba(31,60,100,.06);

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.stat-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 16px 35px
        rgba(31,60,100,.1);
}


.stat-card::after {

    content: "";

    position: absolute;

    width: 110px;

    height: 110px;

    right: -50px;

    bottom: -50px;

    border-radius: 50%;

    background:
        rgba(22,119,255,.06);
}


.stat-top {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;
}


.stat-icon {

    width: 45px;

    height: 45px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    font-size: 20px;

    background:
        #eaf3ff;

    color:
        #1677ff;
}


.stat-icon.green {

    background: #e7f8ef;

    color: #15a05d;
}


.stat-icon.purple {

    background: #f0edff;

    color: #6941c6;
}


.stat-icon.orange {

    background: #fff2e5;

    color: #f07818;
}


.stat-label {

    margin-top: 15px;

    color: #718096;

    font-size: 9px;

    font-weight: 700;
}


.stat-value {

    margin-top: 5px;

    color: #102a56;

    font-size: 25px;

    line-height: 1;

    font-weight: 900;

    letter-spacing: -.7px;
}


.stat-description {

    margin-top: 9px;

    color: #7a879a;

    font-size: 8px;
}


.stat-description strong {

    color: #16a05d;
}


/* =========================================================
   LOWER STATISTICS
========================================================= */

.small-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}


.small-card {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        15px 18px;

    background: white;

    border:
        1px solid #e3eaf4;

    border-radius: 12px;

    box-shadow:
        0 5px 20px
        rgba(31,60,100,.04);
}


.small-label {

    color: #718096;

    font-size: 9px;

    font-weight: 700;
}


.small-value {

    color: #102a56;

    font-size: 17px;

    font-weight: 900;
}


.small-dot {

    width: 8px;

    height: 8px;

    border-radius: 50%;

    display: inline-block;

    margin-right: 6px;
}


.dot-green {
    background: #16a05d;
}

.dot-orange {
    background: #f59e0b;
}

.dot-red {
    background: #ef4444;
}


/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        1.7fr 1fr;

    gap: 20px;

    margin-bottom: 22px;
}


.dashboard-card {

    background:
        rgba(255,255,255,.97);

    border:
        1px solid #e1e8f2;

    border-radius: 15px;

    overflow: hidden;

    box-shadow:
        0 7px 25px
        rgba(31,60,100,.05);
}


.card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        17px 19px;

    border-bottom:
        1px solid #edf1f6;
}


.card-header h2 {

    margin: 0;

    color: #142850;

    font-size: 13px;

    font-weight: 900;
}


.card-header span {

    color: #8793a5;

    font-size: 8px;
}


.view-all {

    color: #1677ff;

    font-size: 9px;

    font-weight: 800;

    text-decoration: none;
}


.view-all:hover {

    text-decoration: none;

    color: #075ed0;
}


/* =========================================================
   TABLE
========================================================= */

.table-scroll {

    overflow-x: auto;
}


.dashboard-table {

    width: 100%;

    min-width: 600px;

    border-collapse: collapse;
}


.dashboard-table th {

    padding:
        11px 15px;

    background:
        #f8faff;

    color:
        #7a879a;

    text-align:
        left;

    font-size:
        8px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        .4px;
}


.dashboard-table td {

    padding:
        12px 15px;

    border-top:
        1px solid #edf1f6;

    color:
        #344054;

    font-size:
        9px;
}


.dashboard-table tbody tr {

    transition:
        background .2s ease;
}


.dashboard-table tbody tr:hover {

    background:
        #f8fbff;
}


.pnr {

    color:
        #1677ff;

    font-weight:
        900;
}


.passenger-name {

    color:
        #142850;

    font-weight:
        800;
}


.route {

    font-weight:
        800;
}


.route-arrow {

    color:
        #1677ff;

    margin:
        0 3px;
}


.amount {

    color:
        #142850;

    font-weight:
        900;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        5px 8px;

    border-radius:
        999px;

    font-size:
        7px;

    font-weight:
        900;
}


.status::before {

    content: "";

    width:
        5px;

    height:
        5px;

    border-radius:
        50%;
}


.status-confirmed {

    background:
        #e7f8ef;

    color:
        #168a4d;
}


.status-confirmed::before {

    background:
        #16a05d;
}


.status-pending {

    background:
        #fff5df;

    color:
        #a16207;
}


.status-pending::before {

    background:
        #f59e0b;
}


.status-cancelled {

    background:
        #ffeded;

    color:
        #d92d20;
}


.status-cancelled::before {

    background:
        #ef4444;
}


/* =========================================================
   ROUTE LIST
========================================================= */

.route-list {

    padding:
        7px 18px 12px;
}


.route-row {

    padding:
        14px 0;

    border-bottom:
        1px solid #edf1f6;
}


.route-row:last-child {

    border-bottom:
        none;
}


.route-top {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        8px;
}


.route-name {

    color:
        #172b4d;

    font-size:
        9px;

    font-weight:
        800;
}


.route-count {

    color:
        #344054;

    font-size:
        9px;

    font-weight:
        900;
}


.progress {

    height:
        6px;

    overflow:
        hidden;

    background:
        #e8eef6;

    border-radius:
        99px;
}


.progress-bar {

    height:
        100%;

    border-radius:
        inherit;

    background:
        linear-gradient(
            90deg,
            #1677ff,
            #3b82f6
        );

    animation:
        growBar 1.2s ease;
}


@keyframes growBar {

    from {
        width: 0 !important;
    }
}


/* =========================================================
   FARE CARD
========================================================= */

.fare-list {

    padding:
        7px 18px 12px;
}


.fare-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding:
        13px 0;

    border-bottom:
        1px solid #edf1f6;
}


.fare-row:last-child {

    border-bottom:
        none;
}


.fare-flight {

    color:
        #142850;

    font-size:
        9px;

    font-weight:
        900;
}


.fare-airline {

    display:
        block;

    margin-top:
        3px;

    color:
        #8793a5;

    font-size:
        7px;
}


.fare-price {

    color:
        #1677ff;

    font-size:
        10px;

    font-weight:
        900;
}


/* =========================================================
   FLIGHTS
========================================================= */

.flight-table-wrapper {

    overflow-x:
        auto;
}


.flight-table {

    width:
        100%;

    min-width:
        700px;

    border-collapse:
        collapse;
}


.flight-table th {

    padding:
        11px 15px;

    background:
        #f8faff;

    color:
        #7a879a;

    text-align:
        left;

    font-size:
        8px;

    text-transform:
        uppercase;
}


.flight-table td {

    padding:
        13px 15px;

    border-top:
        1px solid #edf1f6;

    font-size:
        9px;
}


.flight-number {

    color:
        #142850;

    font-weight:
        900;
}


.airline-name {

    display:
        block;

    margin-top:
        3px;

    color:
        #8793a5;

    font-size:
        7px;
}


.aircraft {

    color:
        #596780;

    font-size:
        8px;
}


.fare {

    color:
        #142850;

    font-weight:
        900;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        30px;

    text-align:
        center;

    color:
        #8793a5;

    font-size:
        9px;
}


/* =========================================================
   FOOTER
========================================================= */

.dashboard-footer {

    margin-top:
        25px;

    text-align:
        center;

    color:
        #8793a5;

    font-size:
        8px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .content-grid {

        grid-template-columns:
            1fr;
    }

}


@media (max-width: 700px) {

    .admin-page {

        padding:
            20px 13px 45px;
    }


    .topbar {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .welcome {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .welcome h1 {

        font-size:
            24px;
    }


    .stats-grid {

        grid-template-columns:
            1fr;
    }


    .small-stats {

        grid-template-columns:
            1fr;
    }


    .quick-actions {

        width:
            100%;
    }


    .quick-btn {

        flex:
            1;

        justify-content:
            center;
    }

}

</style>

</head>


<body>


<main class="admin-page">


    <!-- =================================================
         TOP BAR
    ================================================= -->

    <header class="topbar">


        <div class="brand-area">

            <div class="brand-logo">
                ✈
            </div>


            <div class="brand-text">

                <strong>
                    Vektor<span style="color:#1677ff">
                        Sky
                    </span>
                </strong>

                <span>
                    Airline Administration
                </span>

            </div>

        </div>


        <div class="admin-user">


            <div class="admin-avatar">

                <?= h(
                    strtoupper(
                        substr(
                            $adminName,
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div class="admin-user-text">

                <strong>
                    <?= h(
                        $adminName
                    ) ?>
                </strong>

                <span>
                    Super Administrator
                </span>

            </div>


        </div>


    </header>


    <!-- =================================================
         WELCOME
    ================================================= -->

    <section class="welcome">


        <div>

            <h1>
                Welcome back,
                <?= h(
                    $adminName
                ) ?>
                👋
            </h1>


            <p>
                Here's what's happening
                with VektorSky today.
            </p>

        </div>


        <div class="welcome-date">

            📅

            <?= date(
                'd M Y, l'
            ) ?>

        </div>


    </section>


    <!-- =================================================
         QUICK ACTIONS
    ================================================= -->

    <div class="quick-actions">


        <a
            href="flights.php"
            class="
                quick-btn
                quick-primary
            "
        >
            ✈️ Manage Flights
        </a>


        <a
            href="flights.php"
            class="
                quick-btn
                quick-secondary
            "
        >
            ＋ Add Flight
        </a>


        <a
            href="../my_bookings.php"
            class="
                quick-btn
                quick-secondary
            "
        >
            🎫 View Bookings
        </a>


        <a
            href="logout.php"
            class="
                quick-btn
                quick-secondary
            "
        >
            ⇥ Logout
        </a>


    </div>


    <!-- =================================================
         MAIN STATISTICS
    ================================================= -->

    <section class="stats-grid">


        <!-- BOOKINGS -->

        <article class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    🎫
                </div>

            </div>


            <div class="stat-label">
                Total Bookings
            </div>


            <div class="stat-value">

                <?= number_format(
                    $totalBookings
                ) ?>

            </div>


            <div class="stat-description">

                <strong>
                    <?= number_format(
                        $confirmedBookings
                    ) ?>
                </strong>

                confirmed bookings

            </div>

        </article>


        <!-- REVENUE -->

        <article class="stat-card">

            <div class="stat-top">

                <div
                    class="
                        stat-icon
                        green
                    "
                >
                    ₹
                </div>

            </div>


            <div class="stat-label">
                Total Revenue
            </div>


            <div class="stat-value">

                ₹<?= number_format(
                    $totalRevenue,
                    2
                ) ?>

            </div>


            <div class="stat-description">

                <strong>
                    <?= number_format(
                        $successfulPayments
                    ) ?>
                </strong>

                successful payments

            </div>

        </article>


        <!-- PASSENGERS -->

        <article class="stat-card">

            <div class="stat-top">

                <div
                    class="
                        stat-icon
                        purple
                    "
                >
                    👤
                </div>

            </div>


            <div class="stat-label">
                Total Passengers
            </div>


            <div class="stat-value">

                <?= number_format(
                    $totalPassengers
                ) ?>

            </div>


            <div class="stat-description">

                Registered passengers

            </div>

        </article>


        <!-- FLIGHTS -->

        <article class="stat-card">

            <div class="stat-top">

                <div
                    class="
                        stat-icon
                        orange
                    "
                >
                    ✈
                </div>

            </div>


            <div class="stat-label">
                Total Flights
            </div>


            <div class="stat-value">

                <?= number_format(
                    $totalFlights
                ) ?>

            </div>


            <div class="stat-description">

                Average fare:

                <strong>
                    ₹<?= number_format(
                        $avgFare,
                        0
                    ) ?>
                </strong>

            </div>

        </article>


    </section>


    <!-- =================================================
         SECONDARY STATISTICS
    ================================================= -->

    <section class="small-stats">


        <div class="small-card">

            <div>

                <span
                    class="
                        small-dot
                        dot-green
                    "
                ></span>

                <span class="small-label">
                    Confirmed Bookings
                </span>

            </div>


            <div class="small-value">

                <?= number_format(
                    $confirmedBookings
                ) ?>

            </div>

        </div>


        <div class="small-card">

            <div>

                <span
                    class="
                        small-dot
                        dot-orange
                    "
                ></span>

                <span class="small-label">
                    Pending Bookings
                </span>

            </div>


            <div class="small-value">

                <?= number_format(
                    $pendingBookings
                ) ?>

            </div>

        </div>


        <div class="small-card">

            <div>

                <span
                    class="
                        small-dot
                        dot-red
                    "
                ></span>

                <span class="small-label">
                    Cancelled Bookings
                </span>

            </div>


            <div class="small-value">

                <?= number_format(
                    $cancelledBookings
                ) ?>

            </div>

        </div>


    </section>


    <!-- =================================================
         RECENT BOOKINGS + ROUTES
    ================================================= -->

    <section class="content-grid">


        <!-- RECENT BOOKINGS -->

        <div class="dashboard-card">


            <div class="card-header">

                <h2>
                    Recent Bookings
                </h2>


                <a
                    href="../my_bookings.php"
                    class="view-all"
                >
                    View All →
                </a>

            </div>


            <div class="table-scroll">


                <table
                    class="dashboard-table"
                >


                    <thead>

                        <tr>

                            <th>
                                PNR
                            </th>

                            <th>
                                Passenger
                            </th>

                            <th>
                                Flight
                            </th>

                            <th>
                                Route
                            </th>

                            <th>
                                Seat
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty(
                            $recentBookings
                        )
                    ): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="empty"
                            >
                                No bookings available.
                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $recentBookings
                            as $booking
                        ): ?>


                            <?php

                            $status =
                                strtolower(
                                    trim(
                                        $booking[
                                            'status'
                                        ]
                                        ?? ''
                                    )
                                );


                            $statusClass =
                                $status === 'confirmed'
                                ? 'status-confirmed'
                                : (
                                    $status === 'cancelled'
                                    ? 'status-cancelled'
                                    : 'status-pending'
                                );

                            ?>


                            <tr>


                                <td>

                                    <span
                                        class="pnr"
                                    >

                                        <?= h(
                                            $booking[
                                                'pnr'
                                            ]
                                            ?: 'N/A'
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span
                                        class="
                                            passenger-name
                                        "
                                    >

                                        <?= h(
                                            $booking[
                                                'passenger_name'
                                            ]
                                            ?: 'Unknown'
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <strong>

                                        <?= h(
                                            $booking[
                                                'flight_number'
                                            ]
                                            ?: '-'
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <span
                                        class="route"
                                    >

                                        <?= h(
                                            $booking[
                                                'source_code'
                                            ]
                                            ?: '-'
                                        ) ?>

                                        <span
                                            class="
                                                route-arrow
                                            "
                                        >
                                            →
                                        </span>

                                        <?= h(
                                            $booking[
                                                'destination_code'
                                            ]
                                            ?: '-'
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= h(
                                        $booking[
                                            'seat_number'
                                        ]
                                        ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="amount"
                                    >

                                        ₹<?= number_format(
                                            (float)
                                            $booking[
                                                'total_amount'
                                            ],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span
                                        class="
                                            status
                                            <?= h(
                                                $statusClass
                                            ) ?>
                                        "
                                    >

                                        <?= h(
                                            $booking[
                                                'status'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </div>


        <!-- BOOKINGS PER FLIGHT -->

        <div class="dashboard-card">


            <div class="card-header">

                <h2>
                    Top Flights
                </h2>


                <a
                    href="flights.php"
                    class="view-all"
                >
                    Manage →
                </a>

            </div>


            <div class="route-list">


                <?php

                $maxBookings = 1;

                foreach (
                    $perFlight
                    as $row
                ) {

                    $maxBookings =
                        max(
                            $maxBookings,
                            (int)
                            $row[
                                'bookings'
                            ]
                        );
                }

                ?>


                <?php if (
                    empty(
                        $perFlight
                    )
                ): ?>

                    <div class="empty">
                        No flight data available.
                    </div>

                <?php else: ?>


                    <?php foreach (
                        $perFlight
                        as $row
                    ): ?>


                        <?php

                        $bookingCount =
                            (int)
                            $row[
                                'bookings'
                            ];


                        $percentage =
                            $maxBookings > 0
                            ? (
                                $bookingCount
                                /
                                $maxBookings
                                * 100
                            )
                            : 0;

                        ?>


                        <div
                            class="route-row"
                        >


                            <div
                                class="route-top"
                            >

                                <span
                                    class="
                                        route-name
                                    "
                                >

                                    <?= h(
                                        $row[
                                            'flight_number'
                                        ]
                                    ) ?>

                                    ·

                                    <?= h(
                                        $row[
                                            'source_code'
                                        ]
                                    ) ?>

                                    →

                                    <?= h(
                                        $row[
                                            'destination_code'
                                        ]
                                    ) ?>

                                </span>


                                <span
                                    class="
                                        route-count
                                    "
                                >

                                    <?= number_format(
                                        $bookingCount
                                    ) ?>

                                </span>

                            </div>


                            <div class="progress">

                                <div
                                    class="
                                        progress-bar
                                    "
                                    style="
                                        width:
                                        <?= max(
                                            4,
                                            $percentage
                                        ) ?>%;
                                    "
                                ></div>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </div>


    </section>


    <!-- =================================================
         UPCOMING FLIGHTS
    ================================================= -->

    <section
        class="dashboard-card"
        style="margin-bottom:22px;"
    >


        <div class="card-header">

            <h2>
                Upcoming Flights
            </h2>


            <a
                href="flights.php"
                class="view-all"
            >
                Manage Flights →
            </a>

        </div>


        <div class="flight-table-wrapper">


            <table
                class="flight-table"
            >


                <thead>

                    <tr>

                        <th>
                            Flight
                        </th>

                        <th>
                            Route
                        </th>

                        <th>
                            Aircraft
                        </th>

                        <th>
                            Departure
                        </th>

                        <th>
                            Arrival
                        </th>

                        <th>
                            Fare
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    empty(
                        $upcomingFlights
                    )
                ): ?>


                    <tr>

                        <td
                            colspan="6"
                            class="empty"
                        >
                            No upcoming flights available.
                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $upcomingFlights
                        as $flight
                    ): ?>


                        <tr>


                            <td>

                                <span
                                    class="
                                        flight-number
                                    "
                                >

                                    <?= h(
                                        $flight[
                                            'flight_number'
                                        ]
                                    ) ?>

                                </span>


                                <span
                                    class="
                                        airline-name
                                    "
                                >

                                    <?= h(
                                        $flight[
                                            'airline_name'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="route"
                                >

                                    <?= h(
                                        $flight[
                                            'source_code'
                                        ]
                                    ) ?>

                                    <span
                                        class="
                                            route-arrow
                                        "
                                    >
                                        →
                                    </span>

                                    <?= h(
                                        $flight[
                                            'destination_code'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="aircraft"
                                >

                                    ✈

                                    <?= h(
                                        $flight[
                                            'model'
                                        ]
                                        ?: 'Aircraft'
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= h(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $flight[
                                                'departure_time'
                                            ]
                                        )
                                    )
                                ) ?>

                                <br>

                                <small
                                    style="
                                        color:#8793a5;
                                        font-size:7px;
                                    "
                                >

                                    <?= h(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $flight[
                                                    'departure_time'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </small>

                            </td>


                            <td>

                                <?= h(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $flight[
                                                'arrival_time'
                                            ]
                                        )
                                    )
                                ) ?>

                                <br>

                                <small
                                    style="
                                        color:#8793a5;
                                        font-size:7px;
                                    "
                                >

                                    <?= h(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $flight[
                                                    'arrival_time'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </small>

                            </td>


                            <td>

                                <span
                                    class="fare"
                                >

                                    ₹<?= number_format(
                                        (float)
                                        $flight[
                                            'fare'
                                        ],
                                        2
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </section>


    <!-- =================================================
         ABOVE AVERAGE FARES
    ================================================= -->

    <section
        class="dashboard-card"
        style="margin-bottom:22px;"
    >


        <div class="card-header">

            <h2>
                Flights Above Average Fare
            </h2>


            <span>
                Highest fares
            </span>

        </div>


        <div class="fare-list">


            <?php if (
                empty(
                    $aboveAvg
                )
            ): ?>


                <div class="empty">
                    No flights above average fare.
                </div>


            <?php else: ?>


                <?php foreach (
                    $aboveAvg
                    as $row
                ): ?>


                    <div
                        class="fare-row"
                    >


                        <div>

                            <span
                                class="
                                    fare-flight
                                "
                            >

                                <?= h(
                                    $row[
                                        'flight_number'
                                    ]
                                ) ?>

                            </span>


                            <span
                                class="
                                    fare-airline
                                "
                            >

                                <?= h(
                                    $row[
                                        'airline_name'
                                    ]
                                ) ?>

                            </span>

                        </div>


                        <span
                            class="fare-price"
                        >

                            ₹<?= number_format(
                                (float)
                                $row[
                                    'fare'
                                ],
                                2
                            ) ?>

                        </span>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


    </section>


    <!-- =================================================
         FOOTER
    ================================================= -->

    <footer
        class="dashboard-footer"
    >

        © <?= date('Y') ?>

        VektorSky Airline Administration

        ·

        PHP + MySQL

    </footer>


</main>


<script>

/*
|--------------------------------------------------------------------------
| CARD ANIMATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const cards =
            document.querySelectorAll(
                '.stat-card, .dashboard-card, .small-card'
            );


        cards.forEach(
            function (card, index) {

                card.style.opacity =
                    '0';

                card.style.transform =
                    'translateY(12px)';


                setTimeout(
                    function () {

                        card.style.transition =
                            'opacity .5s ease, transform .5s ease';

                        card.style.opacity =
                            '1';

                        card.style.transform =
                            'translateY(0)';

                    },
                    80 * index
                );

            }
        );

    }
);

</script>


</body>

</html>