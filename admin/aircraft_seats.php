<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$page_title = 'Aircraft Seats';

$error = '';
$success = '';

$aircraftId = isset($_GET['aircraft_id'])
    ? (int)$_GET['aircraft_id']
    : 0;

if ($aircraftId <= 0) {
    header('Location: aircraft.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| AIRCRAFT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        aircraft_id,
        aircraft_no,
        model,
        total_seats,
        airline_name
    FROM aircraft
    WHERE aircraft_id = ?
    LIMIT 1
");

$stmt->execute([$aircraftId]);

$aircraft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aircraft) {
    header('Location: aircraft.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE SEAT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_seat'])
) {

    $seatId = (int)($_POST['seat_id'] ?? 0);

    $seatClass = trim(
        $_POST['seat_class'] ?? ''
    );

    $seatType = trim(
        $_POST['seat_type'] ?? ''
    );

    $seatPrice = (float)(
        $_POST['seat_price'] ?? 0
    );

    $seatStatus = trim(
        $_POST['status'] ?? 'Available'
    );


    $allowedClasses = [
        'Economy',
        'Business'
    ];

    $allowedTypes = [
        'Standard',
        'Window',
        'Aisle',
        'Business'
    ];

    $allowedStatuses = [
        'Available',
        'Blocked'
    ];


    if ($seatId <= 0) {

        $error = 'Invalid seat selected.';

    } elseif (
        !in_array(
            $seatClass,
            $allowedClasses,
            true
        )
    ) {

        $error = 'Invalid seat class.';

    } elseif (
        !in_array(
            $seatType,
            $allowedTypes,
            true
        )
    ) {

        $error = 'Invalid seat type.';

    } elseif ($seatPrice < 0) {

        $error = 'Seat price cannot be negative.';

    } elseif (
        !in_array(
            $seatStatus,
            $allowedStatuses,
            true
        )
    ) {

        $error = 'Invalid seat status.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | VERIFY SEAT BELONGS TO AIRCRAFT
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare("
                SELECT seat_id
                FROM seat
                WHERE seat_id = ?
                AND aircraft_id = ?
                LIMIT 1
            ");

            $check->execute([
                $seatId,
                $aircraftId
            ]);

            if (!$check->fetch()) {

                throw new Exception(
                    'This seat does not belong to the selected aircraft.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $update = $pdo->prepare("
                UPDATE seat
                SET
                    seat_class = ?,
                    seat_type = ?,
                    seat_price = ?,
                    status = ?
                WHERE seat_id = ?
                AND aircraft_id = ?
            ");

            $update->execute([
                $seatClass,
                $seatType,
                $seatPrice,
                $seatStatus,
                $seatId,
                $aircraftId
            ]);

            $success =
                'Seat updated successfully.';

        } catch (Throwable $e) {

            $error =
                'Unable to update seat: ' .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEATS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        seat_id,
        seat_number,
        seat_class,
        seat_type,
        seat_price,
        status
    FROM seat
    WHERE aircraft_id = ?
    ORDER BY seat_id
");

$stmt->execute([$aircraftId]);

$seats = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalSeats = count($seats);

$availableSeats = 0;
$blockedSeats = 0;
$businessSeats = 0;
$economySeats = 0;

foreach ($seats as $seat) {

    if (
        strtolower($seat['status'])
        === 'available'
    ) {
        $availableSeats++;
    } else {
        $blockedSeats++;
    }

    if (
        strtolower($seat['seat_class'])
        === 'business'
    ) {
        $businessSeats++;
    } else {
        $economySeats++;
    }
}


/*
|--------------------------------------------------------------------------
| FLIGHTS USING AIRCRAFT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        f.flight_id,
        f.flight_number,
        f.departure_time,
        f.arrival_time,
        src.airport_code AS source_code,
        dst.airport_code AS destination_code
    FROM flight f

    INNER JOIN airport src
        ON f.source_airport_id = src.airport_id

    INNER JOIN airport dst
        ON f.destination_airport_id = dst.airport_id

    WHERE f.aircraft_id = ?

    ORDER BY f.departure_time ASC
");

$stmt->execute([$aircraftId]);

$flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    VektorSky | Aircraft Seats
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
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f4f8ff,
            #eef5ff,
            #f8fbff
        );

    color: #172033;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {
    height: 70px;

    padding:
        0 5%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(255,255,255,.92);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid #e5e7eb;

    position: sticky;

    top: 0;

    z-index: 50;
}

.brand {
    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: #0b2545;

    font-size: 21px;

    font-weight: 900;
}

.brand-icon {
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
            #1683ff,
            #2563eb
        );

    box-shadow:
        0 8px 22px
        rgba(37,99,235,.28);
}

.brand span {
    color: #2563eb;
}

.back-link {
    text-decoration: none;

    color: #475467;

    font-size: 12px;

    font-weight: 800;

    transition: .2s;
}

.back-link:hover {
    color: #2563eb;
}


/* =========================================================
   PAGE
========================================================= */

.page {
    max-width: 1250px;

    margin: auto;

    padding:
        34px 20px 70px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header {
    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    margin-bottom: 25px;
}

.title-area h1 {
    margin: 0;

    color: #0b2545;

    font-size: 31px;

    font-weight: 900;
}

.title-area p {
    margin:
        7px 0 0;

    color: #667085;

    font-size: 13px;
}

.aircraft-badge {
    padding:
        12px 17px;

    border-radius: 13px;

    background: white;

    border:
        1px solid #e4e7ec;

    box-shadow:
        0 5px 18px
        rgba(16,24,40,.05);

    text-align: right;
}

.aircraft-badge strong {
    display: block;

    color: #0b2545;

    font-size: 15px;
}

.aircraft-badge span {
    display: block;

    margin-top: 4px;

    color: #667085;

    font-size: 10px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {
    padding:
        13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 12px;

    font-weight: 700;
}

.alert-success {
    background: #ecfdf3;

    color: #047857;

    border:
        1px solid #a7f3d0;
}

.alert-error {
    background: #fef2f2;

    color: #b91c1c;

    border:
        1px solid #fecaca;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}

.stat {
    background:
        rgba(255,255,255,.96);

    border:
        1px solid #e4e7ec;

    border-radius: 15px;

    padding: 18px;

    box-shadow:
        0 5px 20px
        rgba(16,24,40,.05);

    transition: .2s ease;
}

.stat:hover {
    transform:
        translateY(-3px);

    box-shadow:
        0 12px 30px
        rgba(16,24,40,.09);
}

.stat-icon {
    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    background: #eff6ff;

    color: #2563eb;

    margin-bottom: 10px;
}

.stat-label {
    color: #667085;

    font-size: 10px;

    font-weight: 700;
}

.stat-value {
    margin-top: 4px;

    color: #0b2545;

    font-size: 22px;

    font-weight: 900;
}


/* =========================================================
   GRID
========================================================= */

.main-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        320px;

    gap: 20px;

    align-items: start;
}


/* =========================================================
   CARD
========================================================= */

.card {
    background:
        rgba(255,255,255,.97);

    border:
        1px solid #e4e7ec;

    border-radius: 17px;

    overflow: hidden;

    box-shadow:
        0 6px 24px
        rgba(16,24,40,.05);
}

.card-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        17px 19px;

    border-bottom:
        1px solid #eef0f3;
}

.card-header h2 {
    margin: 0;

    color: #0b2545;

    font-size: 16px;

    font-weight: 900;
}

.card-header span {
    color: #667085;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   SEAT MAP
========================================================= */

.seat-map-wrapper {
    padding: 22px;
}

.seat-map {
    display: flex;

    flex-direction: column;

    gap: 9px;

    max-height: 680px;

    overflow-y: auto;

    padding: 5px;
}

.seat-row {
    display: grid;

    grid-template-columns:
        35px
        repeat(3, 54px)
        25px
        repeat(3, 54px);

    gap: 7px;

    align-items: center;

    justify-content: center;
}

.row-number {
    color: #98a2b3;

    font-size: 9px;

    font-weight: 800;

    text-align: center;
}

.aisle {
    width: 20px;
}


/* =========================================================
   SEAT
========================================================= */

.seat {
    min-height: 45px;

    border:
        1px solid #d0d5dd;

    border-radius:
        9px 9px 6px 6px;

    background: white;

    cursor: pointer;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.seat:hover {
    transform:
        translateY(-3px)
        scale(1.03);

    box-shadow:
        0 8px 18px
        rgba(16,24,40,.14);
}

.seat-number {
    font-size: 9px;

    font-weight: 900;
}

.seat-price {
    margin-top: 2px;

    font-size: 7px;

    color: #98a2b3;
}


/* ECONOMY */

.seat.economy {
    background: #eff6ff;

    border-color: #bfdbfe;
}

.seat.economy .seat-number {
    color: #1d4ed8;
}


/* BUSINESS */

.seat.business {
    background: #fff7e6;

    border-color: #f4a300;
}

.seat.business .seat-number {
    color: #a06400;
}


/* BLOCKED */

.seat.blocked {
    background: #f1f5f9;

    border-color: #cbd5e1;

    opacity: .65;
}

.seat.blocked .seat-number {
    color: #64748b;
}


/* =========================================================
   LEGEND
========================================================= */

.legend {
    display: flex;

    flex-wrap: wrap;

    gap: 15px;

    padding:
        15px 20px;

    border-top:
        1px solid #eef0f3;

    background:
        #fafbfc;
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
    width: 13px;
    height: 13px;

    border-radius: 4px;

    border: 1px solid #d0d5dd;
}

.legend-economy {
    background: #eff6ff;

    border-color: #bfdbfe;
}

.legend-business {
    background: #fff7e6;

    border-color: #f4a300;
}

.legend-blocked {
    background: #f1f5f9;

    border-color: #cbd5e1;
}


/* =========================================================
   SIDE PANEL
========================================================= */

.info-box {
    padding: 18px;
}

.info-label {
    color: #98a2b3;

    font-size: 9px;

    font-weight: 800;

    margin-bottom: 3px;
}

.info-value {
    color: #344054;

    font-size: 12px;

    font-weight: 800;

    margin-bottom: 15px;
}


/* =========================================================
   FLIGHTS
========================================================= */

.flight-list {
    padding:
        5px 18px 18px;
}

.flight-item {
    padding:
        13px 0;

    border-bottom:
        1px solid #eef0f3;
}

.flight-item:last-child {
    border-bottom: none;
}

.flight-number {
    color: #0b2545;

    font-size: 12px;

    font-weight: 900;
}

.flight-route {
    margin-top: 4px;

    color: #475467;

    font-size: 10px;

    font-weight: 700;
}

.flight-date {
    margin-top: 3px;

    color: #98a2b3;

    font-size: 9px;
}


/* =========================================================
   MODAL
========================================================= */

.modal-overlay {
    position: fixed;

    inset: 0;

    background:
        rgba(4,15,35,.55);

    backdrop-filter:
        blur(8px);

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 999;
}

.modal-overlay.active {
    display: flex;

    animation:
        fadeIn .2s ease;
}

.modal {
    width: 100%;

    max-width: 460px;

    background: white;

    border-radius: 20px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.25);

    overflow: hidden;

    animation:
        modalIn .25s ease;
}

.modal-header {
    padding:
        20px 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom:
        1px solid #eef0f3;
}

.modal-header h2 {
    margin: 0;

    color: #0b2545;

    font-size: 18px;

    font-weight: 900;
}

.close-modal {
    width: 34px;
    height: 34px;

    border: none;

    border-radius: 9px;

    background: #f2f4f7;

    color: #475467;

    cursor: pointer;

    font-size: 18px;

    transition: .2s;
}

.close-modal:hover {
    background: #fee4e2;

    color: #b42318;
}

.modal-body {
    padding: 22px;
}

.seat-preview {
    display: flex;

    align-items: center;

    gap: 15px;

    padding: 14px;

    margin-bottom: 20px;

    border-radius: 12px;

    background: #f8fafc;

    border:
        1px solid #e4e7ec;
}

.preview-seat {
    width: 52px;
    height: 52px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eff6ff;

    color: #2563eb;

    font-size: 13px;

    font-weight: 900;
}

.preview-info strong {
    display: block;

    color: #0b2545;

    font-size: 14px;
}

.preview-info span {
    display: block;

    margin-top: 3px;

    color: #667085;

    font-size: 10px;
}


/* FORM */

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;

    margin-bottom: 6px;

    color: #344054;

    font-size: 11px;

    font-weight: 800;
}

.form-group input,
.form-group select {
    width: 100%;

    padding:
        11px 12px;

    border:
        1px solid #d0d5dd;

    border-radius: 9px;

    background: white;

    color: #344054;

    font-size: 12px;

    outline: none;

    transition: .2s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}

.modal-actions {
    display: flex;

    gap: 10px;

    margin-top: 22px;
}

.btn {
    flex: 1;

    border: none;

    border-radius: 10px;

    padding: 12px;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;
}

.btn-cancel {
    background: #f2f4f7;

    color: #344054;
}

.btn-save {
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    box-shadow:
        0 8px 18px
        rgba(37,99,235,.22);
}

.btn-save:hover {
    transform:
        translateY(-1px);
}


/* =========================================================
   ANIMATIONS
========================================================= */

@keyframes fadeIn {

    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes modalIn {

    from {
        opacity: 0;

        transform:
            translateY(20px)
            scale(.97);
    }

    to {
        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 950px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .main-grid {
        grid-template-columns: 1fr;
    }
}


@media(max-width: 600px) {

    .page-header {
        flex-direction: column;
    }

    .aircraft-badge {
        width: 100%;

        text-align: left;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .seat-row {
        grid-template-columns:
            25px
            repeat(3, 40px)
            12px
            repeat(3, 40px);

        gap: 4px;
    }

    .seat {
        min-height: 38px;
    }
}

</style>

</head>


<body>


<header class="topbar">

    <a
        href="dashboard.php"
        class="brand"
    >

        <span class="brand-icon">
            ✈
        </span>

        Vektor<span>Sky</span>

    </a>


    <a
        href="aircraft.php"
        class="back-link"
    >
        ← Aircraft Management
    </a>

</header>


<main class="page">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

    <div class="title-area">

        <h1>
            Aircraft Seat Management
        </h1>

        <p>
            Configure seat classes, types, prices and availability.
        </p>

    </div>


    <div class="aircraft-badge">

        <strong>

            <?= h(
                $aircraft['aircraft_no']
            ) ?>

        </strong>

        <span>

            <?= h(
                $aircraft['model']
            ) ?>

            ·

            <?= h(
                $aircraft['airline_name']
                ?: 'VektorSky'
            ) ?>

        </span>

    </div>

</div>


<!-- =====================================================
     ALERTS
===================================================== -->

<?php if ($success): ?>

<div class="alert alert-success">

    ✓ <?= h($success) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert alert-error">

    ⚠ <?= h($error) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     STATS
===================================================== -->

<div class="stats">


    <div class="stat">

        <div class="stat-icon">
            💺
        </div>

        <div class="stat-label">
            Total Seats
        </div>

        <div class="stat-value">
            <?= number_format($totalSeats) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            ✓
        </div>

        <div class="stat-label">
            Available
        </div>

        <div class="stat-value">
            <?= number_format($availableSeats) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            ⭐
        </div>

        <div class="stat-label">
            Business
        </div>

        <div class="stat-value">
            <?= number_format($businessSeats) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            ✈
        </div>

        <div class="stat-label">
            Assigned Flights
        </div>

        <div class="stat-value">
            <?= number_format(count($flights)) ?>
        </div>

    </div>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main-grid">


<section class="card">


<div class="card-header">

    <h2>
        Seat Configuration
    </h2>

    <span>
        Click any seat to edit
    </span>

</div>


<div class="seat-map-wrapper">


<?php if (empty($seats)): ?>

    <div
        style="
            padding:70px 20px;
            text-align:center;
            color:#667085;
        "
    >

        <div
            style="
                font-size:42px;
                margin-bottom:10px;
            "
        >
            💺
        </div>

        <strong>
            No seats found
        </strong>

        <p style="font-size:11px;">
            This aircraft does not have seat records.
        </p>

    </div>

<?php else: ?>


<div class="seat-map">


<?php

$seatRows = [];

foreach ($seats as $seat) {

    if (
        preg_match(
            '/^(\d+)([A-F])$/',
            $seat['seat_number'],
            $matches
        )
    ) {

        $row = (int)$matches[1];

        $letter = $matches[2];

    } else {

        continue;
    }

    $seatRows[$row][$letter] = $seat;
}

ksort(
    $seatRows,
    SORT_NUMERIC
);

?>


<?php foreach (
    $seatRows as
    $rowNumber => $rowSeats
): ?>


<div class="seat-row">


<div class="row-number">
    <?= $rowNumber ?>
</div>


<?php foreach (
    ['A','B','C'] as $letter
): ?>


<?php if (
    isset($rowSeats[$letter])
): ?>


<?php

$seat =
    $rowSeats[$letter];

$seatClass =
    strtolower(
        $seat['seat_class']
    );

$seatStatus =
    strtolower(
        $seat['status']
    );

?>


<div
    class="
        seat
        <?= $seatClass === 'business'
            ? 'business'
            : 'economy'
        ?>

        <?= $seatStatus !== 'available'
            ? 'blocked'
            : ''
        ?>
    "

    data-seat-id="<?= (int)$seat['seat_id'] ?>"

    data-seat-number="<?= h(
        $seat['seat_number']
    ) ?>"

    data-seat-class="<?= h(
        $seat['seat_class']
    ) ?>"

    data-seat-type="<?= h(
        $seat['seat_type']
    ) ?>"

    data-seat-price="<?= h(
        $seat['seat_price']
    ) ?>"

    data-seat-status="<?= h(
        $seat['status']
    ) ?>"
>

    <span class="seat-number">
        <?= h(
            $seat['seat_number']
        ) ?>
    </span>

    <span class="seat-price">
        ₹<?= number_format(
            (float)$seat['seat_price'],
            0
        ) ?>
    </span>

</div>


<?php else: ?>

<div></div>

<?php endif; ?>


<?php endforeach; ?>


<div class="aisle"></div>


<?php foreach (
    ['D','E','F'] as $letter
): ?>


<?php if (
    isset($rowSeats[$letter])
): ?>


<?php

$seat =
    $rowSeats[$letter];

$seatClass =
    strtolower(
        $seat['seat_class']
    );

$seatStatus =
    strtolower(
        $seat['status']
    );

?>


<div
    class="
        seat
        <?= $seatClass === 'business'
            ? 'business'
            : 'economy'
        ?>

        <?= $seatStatus !== 'available'
            ? 'blocked'
            : ''
        ?>
    "

    data-seat-id="<?= (int)$seat['seat_id'] ?>"

    data-seat-number="<?= h(
        $seat['seat_number']
    ) ?>"

    data-seat-class="<?= h(
        $seat['seat_class']
    ) ?>"

    data-seat-type="<?= h(
        $seat['seat_type']
    ) ?>"

    data-seat-price="<?= h(
        $seat['seat_price']
    ) ?>"

    data-seat-status="<?= h(
        $seat['status']
    ) ?>"
>

    <span class="seat-number">
        <?= h(
            $seat['seat_number']
        ) ?>
    </span>

    <span class="seat-price">
        ₹<?= number_format(
            (float)$seat['seat_price'],
            0
        ) ?>
    </span>

</div>


<?php else: ?>

<div></div>

<?php endif; ?>


<?php endforeach; ?>


</div>


<?php endforeach; ?>


</div>


<?php endif; ?>


</div>


<div class="legend">

    <div class="legend-item">

        <span
            class="
                legend-box
                legend-economy
            "
        ></span>

        Economy

    </div>


    <div class="legend-item">

        <span
            class="
                legend-box
                legend-business
            "
        ></span>

        Business

    </div>


    <div class="legend-item">

        <span
            class="
                legend-box
                legend-blocked
            "
        ></span>

        Blocked

    </div>


    <div class="legend-item">

        <strong>
            Click seat = Edit
        </strong>

    </div>

</div>


</section>


<!-- =====================================================
     SIDE PANEL
===================================================== -->

<aside>


<section
    class="card"
    style="margin-bottom:20px;"
>


<div class="card-header">

    <h2>
        Aircraft Details
    </h2>

</div>


<div class="info-box">


<div class="info-label">
    AIRCRAFT NUMBER
</div>

<div class="info-value">

    <?= h(
        $aircraft['aircraft_no']
    ) ?>

</div>


<div class="info-label">
    MODEL
</div>

<div class="info-value">

    <?= h(
        $aircraft['model']
    ) ?>

</div>


<div class="info-label">
    AIRLINE
</div>

<div class="info-value">

    <?= h(
        $aircraft['airline_name']
        ?: 'VektorSky'
    ) ?>

</div>


<div class="info-label">
    CAPACITY
</div>

<div class="info-value">

    <?= number_format(
        (int)$aircraft['total_seats']
    ) ?>

    seats

</div>


</div>

</section>


<section class="card">


<div class="card-header">

    <h2>
        Assigned Flights
    </h2>

    <span>
        <?= count($flights) ?>
    </span>

</div>


<?php if (empty($flights)): ?>

<div
    style="
        padding:25px;
        text-align:center;
        color:#667085;
        font-size:10px;
    "
>
    No flights assigned.
</div>

<?php else: ?>


<div class="flight-list">


<?php foreach (
    $flights as $flight
): ?>


<div class="flight-item">

    <div class="flight-number">

        <?= h(
            $flight['flight_number']
        ) ?>

    </div>


    <div class="flight-route">

        <?= h(
            $flight['source_code']
        ) ?>

        →

        <?= h(
            $flight['destination_code']
        ) ?>

    </div>


    <div class="flight-date">

        <?php

        try {

            $date = new DateTime(
                $flight['departure_time']
            );

            echo h(
                $date->format(
                    'd M Y, h:i A'
                )
            );

        } catch (Exception $e) {

            echo 'N/A';
        }

        ?>

    </div>

</div>


<?php endforeach; ?>


</div>

<?php endif; ?>


</section>


</aside>


</div>


</main>


<!-- =====================================================
     EDIT SEAT MODAL
===================================================== -->

<div
    class="modal-overlay"
    id="seatModal"
>


<div class="modal">


<div class="modal-header">

    <h2>
        Edit Seat
    </h2>

    <button
        type="button"
        class="close-modal"
        id="closeModal"
    >
        ×
    </button>

</div>


<form
    method="POST"
    action=""
>


<div class="modal-body">


<div class="seat-preview">


<div
    class="preview-seat"
    id="previewSeatNumber"
>
    1A
</div>


<div class="preview-info">

    <strong>
        Seat Configuration
    </strong>

    <span id="previewSeatDetails">
        Economy · Window
    </span>

</div>


</div>


<input
    type="hidden"
    name="seat_id"
    id="seatId"
>


<div class="form-group">

    <label>
        Seat Class
    </label>

    <select
        name="seat_class"
        id="seatClass"
    >

        <option value="Economy">
            Economy
        </option>

        <option value="Business">
            Business
        </option>

    </select>

</div>


<div class="form-group">

    <label>
        Seat Type
    </label>

    <select
        name="seat_type"
        id="seatType"
    >

        <option value="Standard">
            Standard
        </option>

        <option value="Window">
            Window
        </option>

        <option value="Aisle">
            Aisle
        </option>

        <option value="Business">
            Business
        </option>

    </select>

</div>


<div class="form-group">

    <label>
        Seat Price (₹)
    </label>

    <input
        type="number"
        name="seat_price"
        id="seatPrice"
        min="0"
        step="0.01"
        required
    >

</div>


<div class="form-group">

    <label>
        Seat Status
    </label>

    <select
        name="status"
        id="seatStatus"
    >

        <option value="Available">
            Available
        </option>

        <option value="Blocked">
            Blocked
        </option>

    </select>

</div>


<div class="modal-actions">


<button
    type="button"
    class="btn btn-cancel"
    id="cancelModal"
>
    Cancel
</button>


<button
    type="submit"
    name="update_seat"
    value="1"
    class="btn btn-save"
>
    Save Changes
</button>


</div>


</div>


</form>


</div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const modal =
    document.getElementById(
        'seatModal'
    );

const closeModal =
    document.getElementById(
        'closeModal'
    );

const cancelModal =
    document.getElementById(
        'cancelModal'
    );


const seatId =
    document.getElementById(
        'seatId'
    );

const seatClass =
    document.getElementById(
        'seatClass'
    );

const seatType =
    document.getElementById(
        'seatType'
    );

const seatPrice =
    document.getElementById(
        'seatPrice'
    );

const seatStatus =
    document.getElementById(
        'seatStatus'
    );

const previewSeatNumber =
    document.getElementById(
        'previewSeatNumber'
    );

const previewSeatDetails =
    document.getElementById(
        'previewSeatDetails'
    );


/*
|--------------------------------------------------------------------------
| OPEN MODAL
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.seat')
    .forEach(function(seat) {

        seat.addEventListener(
            'click',
            function() {

                seatId.value =
                    this.dataset.seatId;

                seatClass.value =
                    this.dataset.seatClass;

                seatType.value =
                    this.dataset.seatType;

                seatPrice.value =
                    this.dataset.seatPrice;

                seatStatus.value =
                    this.dataset.seatStatus;

                previewSeatNumber.textContent =
                    this.dataset.seatNumber;

                previewSeatDetails.textContent =
                    this.dataset.seatClass
                    + ' · '
                    + this.dataset.seatType;

                modal.classList.add(
                    'active'
                );

                document.body.style.overflow =
                    'hidden';
            }
        );

    });


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeSeatModal() {

    modal.classList.remove(
        'active'
    );

    document.body.style.overflow =
        '';
}


closeModal.addEventListener(
    'click',
    closeSeatModal
);


cancelModal.addEventListener(
    'click',
    closeSeatModal
);


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE
|--------------------------------------------------------------------------
*/

modal.addEventListener(
    'click',
    function(event) {

        if (
            event.target === modal
        ) {

            closeSeatModal();
        }

    }
);


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
            && modal.classList.contains(
                'active'
            )
        ) {

            closeSeatModal();
        }

    }
);


/*
|--------------------------------------------------------------------------
| LIVE PREVIEW
|--------------------------------------------------------------------------
*/

seatClass.addEventListener(
    'change',
    updatePreview
);

seatType.addEventListener(
    'change',
    updatePreview
);

function updatePreview() {

    previewSeatDetails.textContent =
        seatClass.value
        + ' · '
        + seatType.value;
}

</script>


</body>

</html>