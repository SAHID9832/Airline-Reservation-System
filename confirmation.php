<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$passengerId = (int)($_SESSION['passenger_id'] ?? 0);

$bookingId = (int)(
    $_GET['booking_id']
    ?? $_SESSION['last_booking_id']
    ?? 0
);

if ($passengerId <= 0) {
    header('Location: login.php');
    exit;
}

if ($bookingId <= 0) {
    die('Invalid booking ID.');
}


/*
|--------------------------------------------------------------------------
| GET BOOKING
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        b.booking_id,
        b.pnr,
        b.status,
        b.total_amount,
        b.booking_date,

        p.name AS passenger_name,
        p.email AS passenger_email,
        p.phone AS passenger_phone,

        f.flight_number,
        f.airline_name,
        f.departure_time,
        f.arrival_time,
        f.fare,

        src.airport_code AS source_code,
        src.city AS source_city,

        dst.airport_code AS destination_code,
        dst.city AS destination_city,

        ac.model,
        ac.aircraft_no,

        s.seat_number,
        s.seat_class,
        s.seat_type,
        s.seat_price,

        pay.payment_method,
        pay.payment_status,
        pay.amount AS payment_amount,
        pay.payment_date

    FROM booking b

    JOIN passenger p
        ON b.passenger_id = p.passenger_id

    JOIN flight f
        ON b.flight_id = f.flight_id

    JOIN airport src
        ON f.source_airport_id = src.airport_id

    JOIN airport dst
        ON f.destination_airport_id = dst.airport_id

    JOIN aircraft ac
        ON f.aircraft_id = ac.aircraft_id

    JOIN seat s
        ON b.seat_id = s.seat_id

    LEFT JOIN payment pay
        ON b.booking_id = pay.booking_id

    WHERE
        b.booking_id = ?
        AND b.passenger_id = ?

    ORDER BY pay.payment_id DESC

    LIMIT 1
");

$stmt->execute([
    $bookingId,
    $passengerId
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BOOKING NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$booking) {

    die('
        <div style="
            font-family:Arial,sans-serif;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#f4f7fb;
        ">

            <div style="
                background:white;
                padding:45px;
                border-radius:20px;
                text-align:center;
                box-shadow:0 15px 50px rgba(0,0,0,.08);
                max-width:500px;
                width:90%;
            ">

                <div style="
                    font-size:50px;
                    margin-bottom:15px;
                ">
                    🎫
                </div>

                <h2 style="
                    color:#0b2545;
                    margin-bottom:10px;
                ">
                    Booking Not Found
                </h2>

                <p style="
                    color:#667085;
                    line-height:1.6;
                ">
                    We could not find this booking for your account.
                    Please open the ticket from My Bookings.
                </p>

                <a
                    href="my_bookings.php"
                    style="
                        display:inline-block;
                        margin-top:15px;
                        padding:12px 22px;
                        background:#2563eb;
                        color:white;
                        text-decoration:none;
                        border-radius:10px;
                        font-weight:bold;
                    "
                >
                    View My Bookings
                </a>

            </div>

        </div>
    ');

    exit;
}


/*
|--------------------------------------------------------------------------
| DATE / TIME
|--------------------------------------------------------------------------
*/

$departure =
    new DateTime(
        $booking['departure_time']
    );

$arrival =
    new DateTime(
        $booking['arrival_time']
    );


$departureDate =
    $departure->format('d M Y');

$departureTime =
    $departure->format('h:i A');

$arrivalTime =
    $arrival->format('h:i A');


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

$paymentStatus =
    $booking['payment_status']
    ?? 'Pending';

$paymentAmount =
    $booking['payment_amount']
    ?? $booking['total_amount'];

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
    VektorSky | Booking Confirmation
</title>

<style>

* {
    box-sizing:border-box;
}

body {

    margin:0;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #eef5ff,
            #f8fafc
        );

    color:#101828;

    min-height:100vh;
}

.header {

    height:72px;

    background:white;

    border-bottom:
        1px solid #e4e7ec;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 6%;
}

.logo {

    text-decoration:none;

    font-size:23px;

    font-weight:900;

    color:#101828;
}

.logo span {
    color:#2563eb;
}

.header a:last-child {

    color:#475467;

    text-decoration:none;

    font-size:13px;

    font-weight:700;
}

.container {

    width:100%;

    max-width:900px;

    margin:45px auto;

    padding:0 20px 60px;
}

.success {

    text-align:center;

    margin-bottom:25px;
}

.success-icon {

    width:70px;

    height:70px;

    border-radius:50%;

    background:#22c55e;

    color:white;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:35px;

    margin:0 auto 15px;

    box-shadow:
        0 12px 30px
        rgba(34,197,94,.25);
}

.success h1 {

    margin:0;

    color:#0b2545;

    font-size:30px;
}

.success p {

    color:#667085;

    font-size:14px;
}

.ticket {

    background:white;

    border-radius:22px;

    overflow:hidden;

    box-shadow:
        0 20px 60px
        rgba(16,24,40,.10);
}

.ticket-header {

    padding:25px 30px;

    color:white;

    background:
        linear-gradient(
            135deg,
            #0b2545,
            #2563eb
        );

    display:flex;

    justify-content:space-between;

    align-items:center;
}

.brand {

    font-size:22px;

    font-weight:900;
}

.brand small {

    display:block;

    color:#cbdff8;

    font-size:9px;

    margin-top:3px;
}

.pnr {

    text-align:right;
}

.pnr small {

    display:block;

    color:#cbdff8;

    font-size:8px;
}

.pnr strong {

    font-size:20px;

    letter-spacing:2px;
}

.route {

    padding:30px;

    display:grid;

    grid-template-columns:1fr auto 1fr;

    align-items:center;

    gap:20px;

    border-bottom:
        1px dashed #d0d5dd;
}

.airport {

    display:flex;

    flex-direction:column;
}

.airport.right {

    align-items:flex-end;

    text-align:right;
}

.code {

    font-size:35px;

    font-weight:900;

    color:#0b2545;
}

.city {

    color:#667085;

    font-size:12px;
}

.time {

    margin-top:8px;

    font-size:13px;

    font-weight:800;
}

.plane {

    color:#2563eb;

    font-size:25px;

    text-align:center;
}

.flight-no {

    color:#98a2b3;

    font-size:9px;

    margin-top:6px;
}

.info-grid {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    border-bottom:
        1px solid #eef2f6;
}

.info {

    padding:18px;

    border-right:
        1px solid #eef2f6;
}

.info:last-child {
    border-right:none;
}

.label {

    color:#98a2b3;

    font-size:8px;

    font-weight:800;

    text-transform:uppercase;
}

.value {

    margin-top:6px;

    color:#344054;

    font-size:12px;

    font-weight:800;
}

.section {

    padding:25px 30px;

    border-bottom:
        1px solid #eef2f6;
}

.section h3 {

    margin:0 0 15px;

    color:#0b2545;

    font-size:15px;
}

.passenger-grid {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:12px;
}

.box {

    padding:14px;

    border-radius:10px;

    background:#f8fafc;

    border:
        1px solid #eef2f6;
}

.total {

    padding:22px 30px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    background:#f8fbff;
}

.total span {

    color:#667085;

    font-size:12px;

    font-weight:700;
}

.total strong {

    color:#2563eb;

    font-size:25px;
}

.actions {

    display:flex;

    justify-content:center;

    gap:12px;

    margin-top:22px;

    flex-wrap:wrap;
}

.btn {

    border:none;

    padding:12px 20px;

    border-radius:10px;

    text-decoration:none;

    font-size:12px;

    font-weight:800;

    cursor:pointer;
}

.primary {

    background:#2563eb;

    color:white;
}

.secondary {

    background:white;

    color:#344054;

    border:
        1px solid #d0d5dd;
}

@media(max-width:650px) {

    .ticket-header {
        flex-direction:column;
        align-items:flex-start;
        gap:15px;
    }

    .pnr {
        text-align:left;
    }

    .route {
        gap:8px;
        padding:22px 15px;
    }

    .code {
        font-size:25px;
    }

    .info-grid {
        grid-template-columns:1fr 1fr;
    }

    .info {
        border-bottom:
            1px solid #eef2f6;
    }

    .passenger-grid {
        grid-template-columns:1fr;
    }
}

@media print {

    .header,
    .actions {
        display:none;
    }

    body {
        background:white;
    }

    .container {
        margin:0;
        max-width:100%;
    }

    .ticket {
        box-shadow:none;
    }
}

</style>

</head>

<body>


<header class="header">

    <a
        href="index.php"
        class="logo"
    >
        Vektor<span>Sky</span>
    </a>

    <a href="my_bookings.php">
        My Bookings
    </a>

</header>


<main class="container">


<div class="success">

    <div class="success-icon">
        ✓
    </div>

    <h1>
        Booking Confirmed!
    </h1>

    <p>
        Your VektorSky flight has been successfully booked.
    </p>

</div>


<div class="ticket">


<div class="ticket-header">

    <div>

        <div class="brand">
            VektorSky
        </div>

        <small>
            DIGITAL AIRLINE TICKET
        </small>

    </div>


    <div class="pnr">

        <small>
            BOOKING REFERENCE
        </small>

        <strong>
            <?= h($booking['pnr']) ?>
        </strong>

    </div>

</div>


<div class="route">


    <div class="airport">

        <div class="code">
            <?= h($booking['source_code']) ?>
        </div>

        <div class="city">
            <?= h($booking['source_city']) ?>
        </div>

        <div class="time">
            <?= h($departureTime) ?>
        </div>

    </div>


    <div>

        <div class="plane">
            ✈
        </div>

        <div class="flight-no">

            <?= h($booking['flight_number']) ?>

        </div>

    </div>


    <div class="airport right">

        <div class="code">
            <?= h($booking['destination_code']) ?>
        </div>

        <div class="city">
            <?= h($booking['destination_city']) ?>
        </div>

        <div class="time">
            <?= h($arrivalTime) ?>
        </div>

    </div>


</div>


<div class="info-grid">


    <div class="info">

        <div class="label">
            Date
        </div>

        <div class="value">
            <?= h($departureDate) ?>
        </div>

    </div>


    <div class="info">

        <div class="label">
            Flight
        </div>

        <div class="value">
            <?= h($booking['flight_number']) ?>
        </div>

    </div>


    <div class="info">

        <div class="label">
            Aircraft
        </div>

        <div class="value">
            <?= h($booking['model']) ?>
        </div>

    </div>


    <div class="info">

        <div class="label">
            Seat
        </div>

        <div class="value">

            <?= h($booking['seat_number']) ?>

            ·

            <?= h($booking['seat_class']) ?>

        </div>

    </div>


</div>


<div class="section">

    <h3>
        Passenger Information
    </h3>


    <div class="passenger-grid">


        <div class="box">

            <div class="label">
                Passenger
            </div>

            <div class="value">
                <?= h($booking['passenger_name']) ?>
            </div>

        </div>


        <div class="box">

            <div class="label">
                Email
            </div>

            <div class="value">
                <?= h($booking['passenger_email']) ?>
            </div>

        </div>


        <div class="box">

            <div class="label">
                Phone
            </div>

            <div class="value">
                <?= h($booking['passenger_phone']) ?>
            </div>

        </div>


    </div>

</div>


<div class="section">

    <h3>
        Payment Information
    </h3>


    <div class="passenger-grid">


        <div class="box">

            <div class="label">
                Payment Method
            </div>

            <div class="value">

                <?= h(
                    $booking['payment_method']
                    ?? 'N/A'
                ) ?>

            </div>

        </div>


        <div class="box">

            <div class="label">
                Payment Status
            </div>

            <div
                class="value"
                style="color:#15803d;"
            >

                <?= h(
                    $paymentStatus
                ) ?>

            </div>

        </div>


        <div class="box">

            <div class="label">
                Booking Status
            </div>

            <div class="value">

                <?= h(
                    $booking['status']
                ) ?>

            </div>

        </div>


    </div>

</div>


<div class="total">

    <span>
        Total Amount Paid
    </span>

    <strong>

        ₹<?= number_format(
            (float)$paymentAmount,
            2
        ) ?>

    </strong>

</div>


</div>


<div class="actions">

    <a
        href="my_bookings.php"
        class="btn primary"
    >
        🎫 My Bookings
    </a>


    <button
        class="btn secondary"
        onclick="window.print()"
    >
        🖨 Print Ticket
    </button>


    <a
        href="index.php"
        class="btn secondary"
    >
        ✈ Book Another Flight
    </a>

</div>


</main>


</body>

</html>