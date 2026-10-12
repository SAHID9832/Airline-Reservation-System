<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| CURRENT PASSENGER
|--------------------------------------------------------------------------
*/

$passengerId = (int)(
    $_SESSION['passenger_id']
    ?? $_SESSION['user_id']
    ?? 0
);

if ($passengerId <= 0) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$flightId = (int)($_POST['flight_id'] ?? $_GET['flight_id'] ?? 0);
$seatId = (int)($_POST['seat_id'] ?? $_GET['seat_id'] ?? 0);
$flightSeatId = (int)(
    $_POST['flight_seat_id']
    ?? $_GET['flight_seat_id']
    ?? 0
);

$action = trim((string)($_POST['action'] ?? ''));

$paymentMethod = trim(
    (string)($_POST['payment_method'] ?? '')
);

/*
|--------------------------------------------------------------------------
| GET FLIGHT
|--------------------------------------------------------------------------
*/

if ($flightId <= 0) {

    $flightId = (int)(
        $_SESSION['booking_flight_id']
        ?? 0
    );
}

if ($flightId <= 0) {
    die('Invalid flight selected.');
}

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

        ac.aircraft_no,
        ac.model

    FROM flight f

    INNER JOIN airport src
        ON f.source_airport_id = src.airport_id

    INNER JOIN airport dst
        ON f.destination_airport_id = dst.airport_id

    INNER JOIN aircraft ac
        ON f.aircraft_id = ac.aircraft_id

    WHERE f.flight_id = ?

    LIMIT 1
");

$stmt->execute([$flightId]);

$flight = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flight) {
    die('Flight not found.');
}

/*
|--------------------------------------------------------------------------
| GET SELECTED SEAT
|--------------------------------------------------------------------------
*/

if ($seatId <= 0 || $flightSeatId <= 0) {

    /*
    | If payment.php was opened directly, try session values.
    */

    $seatId = (int)(
        $_SESSION['booking_seat_id']
        ?? $seatId
    );

    $flightSeatId = (int)(
        $_SESSION['booking_flight_seat_id']
        ?? $flightSeatId
    );
}

if ($seatId <= 0 || $flightSeatId <= 0) {

    /*
    | Normal flow should always arrive from seat_selection.php.
    */

    die(
        'Seat information is missing. Please return to seat selection.'
    );
}

/*
|--------------------------------------------------------------------------
| GET SEAT
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
        ON fs.seat_id = s.seat_id

    WHERE
        fs.flight_seat_id = ?
        AND fs.flight_id = ?
        AND fs.seat_id = ?
        AND s.aircraft_id = ?

    LIMIT 1
");

$stmt->execute([
    $flightSeatId,
    $flightId,
    $seatId,
    $flight['aircraft_id']
]);

$seat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seat) {
    die('Selected seat does not belong to this flight.');
}

/*
|--------------------------------------------------------------------------
| CHECK SEAT AVAILABILITY
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        trim((string)$seat['status'])
    ) !== 'available'
) {

    die(
        'This seat is no longer available. Please select another seat.'
    );
}

/*
|--------------------------------------------------------------------------
| CALCULATE FARE FROM DATABASE
|--------------------------------------------------------------------------
*/

$flightFare = (float)$flight['fare'];

$seatPrice = (float)$seat['seat_price'];

$subtotal = $flightFare + $seatPrice;

$tax = round(
    $subtotal * 0.05,
    2
);

$total = round(
    $subtotal + $tax,
    2
);

/*
|--------------------------------------------------------------------------
| PAYMENT SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $action === 'pay'
) {

    /*
    |--------------------------------------------------------------------------
    | VALID PAYMENT METHOD
    |--------------------------------------------------------------------------
    */

    $allowedPaymentMethods = [
        'UPI',
        'Card',
        'NetBanking'
    ];

    if (
        !in_array(
            $paymentMethod,
            $allowedPaymentMethods,
            true
        )
    ) {

        die('Please select a valid payment method.');
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | LOCK FLIGHT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                flight_id,
                fare,
                aircraft_id

            FROM flight

            WHERE flight_id = ?

            LIMIT 1

            FOR UPDATE
        ");

        $stmt->execute([
            $flightId
        ]);

        $lockedFlight = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$lockedFlight) {
            throw new Exception(
                'Flight no longer exists.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | LOCK SELECTED FLIGHT SEAT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT

                fs.flight_seat_id,
                fs.flight_id,
                fs.seat_id,
                fs.status,

                s.aircraft_id,
                s.seat_number,
                s.seat_class,
                s.seat_type,
                s.seat_price

            FROM flight_seat fs

            INNER JOIN seat s
                ON fs.seat_id = s.seat_id

            WHERE
                fs.flight_seat_id = ?
                AND fs.flight_id = ?
                AND fs.seat_id = ?
                AND s.aircraft_id = ?

            LIMIT 1

            FOR UPDATE
        ");

        $stmt->execute([
            $flightSeatId,
            $flightId,
            $seatId,
            $lockedFlight['aircraft_id']
        ]);

        $lockedSeat = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$lockedSeat) {

            throw new Exception(
                'Selected seat is invalid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK AVAILABILITY AGAIN
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                trim(
                    (string)$lockedSeat['status']
                )
            ) !== 'available'
        ) {

            throw new Exception(
                'Sorry, this seat has just been booked by another user.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RECALCULATE FINAL PRICE
        |--------------------------------------------------------------------------
        */

        $finalFlightFare =
            (float)$lockedFlight['fare'];

        $finalSeatPrice =
            (float)$lockedSeat['seat_price'];

        $finalSubtotal =
            $finalFlightFare
            + $finalSeatPrice;

        $finalTax =
            round(
                $finalSubtotal * 0.05,
                2
            );

        $finalTotal =
            round(
                $finalSubtotal + $finalTax,
                2
            );

        /*
        |--------------------------------------------------------------------------
        | GENERATE UNIQUE PNR
        |--------------------------------------------------------------------------
        */

        do {

            $pnr =
                'VSK'
                .
                strtoupper(
                    substr(
                        str_shuffle(
                            'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
                        ),
                        0,
                        8
                    )
                );

            $stmt = $pdo->prepare("
                SELECT booking_id

                FROM booking

                WHERE pnr = ?

                LIMIT 1
            ");

            $stmt->execute([
                $pnr
            ]);

            $pnrExists =
                $stmt->fetchColumn();

        } while ($pnrExists);

        /*
        |--------------------------------------------------------------------------
        | CREATE BOOKING
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO booking
            (
                pnr,
                passenger_id,
                flight_id,
                seat_id,
                status,
                total_amount
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                'Confirmed',
                ?
            )
        ");

        $stmt->execute([
            $pnr,
            $passengerId,
            $flightId,
            $seatId,
            $finalTotal
        ]);

        $bookingId =
            (int)$pdo->lastInsertId();

        if ($bookingId <= 0) {

            throw new Exception(
                'Booking could not be created.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | MARK FLIGHT SEAT AS BOOKED
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE flight_seat

            SET
                status = 'Booked',
                booked_by = ?,
                booked_at = NOW()

            WHERE
                flight_seat_id = ?
                AND flight_id = ?
                AND seat_id = ?
                AND status = 'Available'
        ");

        $stmt->execute([
            $passengerId,
            $flightSeatId,
            $flightId,
            $seatId
        ]);

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'Seat could not be reserved.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE PAYMENT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO payment
            (
                booking_id,
                amount,
                payment_method,
                payment_status
            )

            VALUES
            (
                ?,
                ?,
                ?,
                'Success'
            )
        ");

        $stmt->execute([
            $bookingId,
            $finalTotal,
            $paymentMethod
        ]);

        /*
        |--------------------------------------------------------------------------
        | SAVE SESSION
        |--------------------------------------------------------------------------
        */

        $_SESSION['last_booking_id'] =
            $bookingId;

        $_SESSION['last_booking_pnr'] =
            $pnr;

        $_SESSION['booking_flight_id'] =
            $flightId;

        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        /*
        |--------------------------------------------------------------------------
        | CONFIRMATION
        |--------------------------------------------------------------------------
        */

        header(
            'Location: confirmation.php?booking_id='
            . $bookingId
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
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
                VektorSky | Payment Error
            </title>

            <style>

                * {
                    box-sizing: border-box;
                }

                body {

                    margin: 0;

                    min-height: 100vh;

                    display: flex;

                    align-items: center;

                    justify-content: center;

                    padding: 20px;

                    font-family:
                        Inter,
                        Arial,
                        sans-serif;

                    background:
                        linear-gradient(
                            135deg,
                            #eff6ff,
                            #f8fafc
                        );

                    color: #101828;
                }

                .error-card {

                    width: 100%;

                    max-width: 550px;

                    background: white;

                    border: 1px solid #e4e7ec;

                    border-radius: 22px;

                    padding: 40px;

                    text-align: center;

                    box-shadow:
                        0 20px 60px
                        rgba(16,24,40,.10);
                }

                .error-icon {

                    width: 65px;

                    height: 65px;

                    margin: 0 auto 18px;

                    display: flex;

                    align-items: center;

                    justify-content: center;

                    border-radius: 50%;

                    background: #fee2e2;

                    color: #dc2626;

                    font-size: 28px;
                }

                h1 {

                    margin: 0;

                    font-size: 25px;
                }

                p {

                    color: #667085;

                    line-height: 1.6;

                    font-size: 14px;
                }

                .error-message {

                    margin-top: 18px;

                    padding: 13px;

                    border-radius: 10px;

                    background: #fff7ed;

                    color: #9a3412;

                    font-size: 12px;
                }

                .btn {

                    display: inline-block;

                    margin-top: 20px;

                    padding: 12px 20px;

                    border-radius: 10px;

                    background: #2563eb;

                    color: white;

                    text-decoration: none;

                    font-weight: 800;

                    font-size: 13px;
                }

                .btn:hover {

                    background: #1d4ed8;
                }

            </style>

        </head>

        <body>

            <div class="error-card">

                <div class="error-icon">
                    ✕
                </div>

                <h1>
                    Payment Failed
                </h1>

                <p>
                    We could not complete your booking.
                    Your payment transaction was cancelled
                    and no seat was permanently reserved.
                </p>

                <div class="error-message">
                    <?= h($e->getMessage()) ?>
                </div>

                <a
                    href="seat_selection.php?flight_id=<?= h($flightId) ?>"
                    class="btn"
                >
                    ← Choose Another Seat
                </a>

            </div>

        </body>

        </html>

        <?php

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| PASSENGER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        name,
        email,
        phone

    FROM passenger

    WHERE passenger_id = ?

    LIMIT 1
");

$stmt->execute([
    $passengerId
]);

$passenger =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$passenger) {
    die('Passenger not found.');
}

/*
|--------------------------------------------------------------------------
| DATE / TIME
|--------------------------------------------------------------------------
*/

$departure = new DateTime(
    $flight['departure_time']
);

$flightDate =
    $departure->format('d M Y');

$departureTime =
    $departure->format('h:i A');

$arrival = new DateTime(
    $flight['arrival_time']
);

$arrivalTime =
    $arrival->format('h:i A');

/*
|--------------------------------------------------------------------------
| SAVE BOOKING SESSION
|--------------------------------------------------------------------------
*/

$_SESSION['booking_flight_id'] =
    $flightId;

$_SESSION['booking_seat_id'] =
    $seatId;

$_SESSION['booking_flight_seat_id'] =
    $flightSeatId;

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
        VektorSky | Secure Payment
    </title>

    <style>

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

            color: #101828;

            min-height: 100vh;

            background:
                radial-gradient(
                    circle at 10% 20%,
                    rgba(37,99,235,.12),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 90% 80%,
                    rgba(6,182,212,.10),
                    transparent 35%
                ),
                #f4f7fb;
        }

        .header {

            height: 72px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 6%;

            background:
                rgba(255,255,255,.85);

            backdrop-filter:
                blur(18px);

            border-bottom:
                1px solid #e5e7eb;

            position: sticky;

            top: 0;

            z-index: 10;
        }

        .logo {

            font-size: 23px;

            font-weight: 900;

            text-decoration: none;

            color: #101828;
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
        }

        .container {

            width: 100%;

            max-width: 1100px;

            margin: 40px auto;

            padding: 0 20px 60px;
        }

        .page-title {

            margin-bottom: 25px;
        }

        .page-title h1 {

            margin: 0;

            font-size: 32px;

            font-weight: 900;

            letter-spacing: -.8px;
        }

        .page-title p {

            margin: 7px 0 0;

            color: #667085;

            font-size: 13px;
        }

        .grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                380px;

            gap: 22px;

            align-items: start;
        }

        .card {

            background:
                rgba(255,255,255,.92);

            backdrop-filter:
                blur(16px);

            border:
                1px solid #e4e7ec;

            border-radius: 22px;

            padding: 25px;

            box-shadow:
                0 15px 45px
                rgba(16,24,40,.07);
        }

        .card-title {

            font-size: 17px;

            font-weight: 900;

            margin-bottom: 18px;
        }

        .flight-card {

            padding: 18px;

            border-radius: 16px;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #f8fafc
                );

            border:
                1px solid #dbeafe;

            margin-bottom: 22px;
        }

        .flight-top {

            display: flex;

            justify-content: space-between;

            gap: 15px;
        }

        .airline {

            font-weight: 900;

            font-size: 12px;
        }

        .flight-number {

            margin-top: 4px;

            color: #667085;

            font-size: 10px;
        }

        .route {

            margin-top: 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 18px;

            font-size: 24px;

            font-weight: 900;
        }

        .route-arrow {

            color: #2563eb;
        }

        .flight-details {

            margin-top: 8px;

            text-align: center;

            color: #667085;

            font-size: 10px;
        }

        .info-row {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 14px 0;

            border-bottom:
                1px solid #eef2f6;

            font-size: 12px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #667085;
        }

        .info-value {

            font-weight: 800;

            text-align: right;
        }

        .seat-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            border-radius: 8px;

            background: #eff6ff;

            color: #1d4ed8;

            font-size: 11px;

            font-weight: 900;
        }

        .summary-row {

            display: flex;

            justify-content: space-between;

            padding: 13px 0;

            border-bottom:
                1px solid #eef2f6;

            font-size: 12px;
        }

        .summary-row span:first-child {
            color: #667085;
        }

        .summary-row span:last-child {
            font-weight: 800;
        }

        .total {

            display: flex;

            justify-content: space-between;

            padding: 20px 0;

            font-size: 20px;

            font-weight: 900;
        }

        .total-value {
            color: #2563eb;
        }

        .secure {

            padding: 13px;

            border-radius: 11px;

            background: #ecfdf3;

            border: 1px solid #bbf7d0;

            color: #166534;

            font-size: 10px;

            font-weight: 700;

            margin-bottom: 18px;
        }

        .payment-title {

            font-size: 13px;

            font-weight: 900;

            margin-bottom: 12px;
        }

        .payment-option {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 15px;

            margin-bottom: 10px;

            border:
                1px solid #dbe3ef;

            border-radius: 13px;

            cursor: pointer;

            font-size: 12px;

            font-weight: 800;

            transition: .2s;
        }

        .payment-option:hover {

            border-color: #2563eb;

            background: #f8fbff;

            transform: translateY(-1px);
        }

        .payment-option input {

            accent-color: #2563eb;
        }

        .pay-button {

            width: 100%;

            height: 55px;

            margin-top: 12px;

            border: none;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            color: white;

            font-size: 13px;

            font-weight: 900;

            cursor: pointer;

            transition: .2s;
        }

        .pay-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 12px 28px
                rgba(37,99,235,.28);
        }

        .pay-button:disabled {

            opacity: .6;

            cursor: not-allowed;

            transform: none;

            box-shadow: none;
        }

        .small-note {

            text-align: center;

            margin-top: 12px;

            color: #98a2b3;

            font-size: 9px;
        }

        @media(max-width:850px) {

            .grid {
                grid-template-columns: 1fr;
            }

        }

        @media(max-width:600px) {

            .header {
                padding: 0 20px;
            }

            .container {
                margin-top: 25px;
                padding: 0 12px 40px;
            }

            .page-title h1 {
                font-size: 26px;
            }

            .card {
                padding: 18px;
            }

            .route {
                font-size: 20px;
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

    <a
        href="seat_selection.php?flight_id=<?= h($flightId) ?>"
        class="back"
    >
        ← Change Seat
    </a>

</header>


<main class="container">

    <div class="page-title">

        <h1>
            Complete Your Payment
        </h1>

        <p>
            Review your booking and choose a payment method.
        </p>

    </div>


    <div class="grid">

        <!-- LEFT -->

        <section class="card">

            <div class="card-title">
                Flight Details
            </div>

            <div class="flight-card">

                <div class="flight-top">

                    <div>

                        <div class="airline">
                            <?= h($flight['airline_name']) ?>
                        </div>

                        <div class="flight-number">

                            Flight
                            <?= h($flight['flight_number']) ?>

                            ·

                            <?= h($flight['model']) ?>

                        </div>

                    </div>

                </div>


                <div class="route">

                    <?= h($flight['source_code']) ?>

                    <span class="route-arrow">
                        ✈
                    </span>

                    <?= h($flight['destination_code']) ?>

                </div>


                <div class="flight-details">

                    <?= h($flight['source_city']) ?>

                    →

                    <?= h($flight['destination_city']) ?>

                    ·

                    <?= h($flightDate) ?>

                    ·

                    <?= h($departureTime) ?>

                    -

                    <?= h($arrivalTime) ?>

                </div>

            </div>


            <div class="card-title">
                Passenger Details
            </div>


            <div class="info-row">

                <span class="info-label">
                    Name
                </span>

                <span class="info-value">
                    <?= h($passenger['name']) ?>
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Email
                </span>

                <span class="info-value">
                    <?= h($passenger['email']) ?>
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Phone
                </span>

                <span class="info-value">
                    <?= h($passenger['phone']) ?>
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Selected Seat
                </span>

                <span class="info-value">

                    <span class="seat-badge">

                        💺

                        <?= h(
                            $seat['seat_number']
                        ) ?>

                        ·

                        <?= h(
                            $seat['seat_type']
                        ) ?>

                    </span>

                </span>

            </div>

        </section>


        <!-- RIGHT -->

        <aside class="card">

            <div class="card-title">
                Fare Summary
            </div>


            <div class="summary-row">

                <span>
                    Flight fare
                </span>

                <span>
                    ₹<?= number_format(
                        $flightFare,
                        2
                    ) ?>
                </span>

            </div>


            <div class="summary-row">

                <span>
                    Seat selection
                </span>

                <span>
                    ₹<?= number_format(
                        $seatPrice,
                        2
                    ) ?>
                </span>

            </div>


            <div class="summary-row">

                <span>
                    Taxes & fees
                </span>

                <span>
                    ₹<?= number_format(
                        $tax,
                        2
                    ) ?>
                </span>

            </div>


            <div class="total">

                <span>
                    Total
                </span>

                <span class="total-value">

                    ₹<?= number_format(
                        $total,
                        2
                    ) ?>

                </span>

            </div>


            <div class="secure">

                🔒 Secure booking

                <br>

                Your seat and payment are processed
                inside a database transaction.

            </div>


            <div class="payment-title">

                Choose Payment Method
            </div>


            <form
                method="POST"
                action="payment.php"
                id="paymentForm"
            >

                <input
                    type="hidden"
                    name="action"
                    value="pay"
                >

                <input
                    type="hidden"
                    name="flight_id"
                    value="<?= h($flightId) ?>"
                >

                <input
                    type="hidden"
                    name="seat_id"
                    value="<?= h($seatId) ?>"
                >

                <input
                    type="hidden"
                    name="flight_seat_id"
                    value="<?= h($flightSeatId) ?>"
                >


                <label class="payment-option">

                    <input
                        type="radio"
                        name="payment_method"
                        value="UPI"
                        required
                    >

                    <span>
                        📱 UPI
                    </span>

                </label>


                <label class="payment-option">

                    <input
                        type="radio"
                        name="payment_method"
                        value="Card"
                    >

                    <span>
                        💳 Credit / Debit Card
                    </span>

                </label>


                <label class="payment-option">

                    <input
                        type="radio"
                        name="payment_method"
                        value="NetBanking"
                    >

                    <span>
                        🏦 Net Banking
                    </span>

                </label>


                <button
                    type="submit"
                    class="pay-button"
                    id="payButton"
                    disabled
                >

                    Pay ₹<?= number_format(
                        $total,
                        2
                    ) ?>

                </button>

            </form>


            <div class="small-note">

                🔐 Your payment information is handled securely.

            </div>

        </aside>

    </div>

</main>


<script>

const paymentForm =
    document.getElementById(
        'paymentForm'
    );

const payButton =
    document.getElementById(
        'payButton'
    );

const paymentMethods =
    document.querySelectorAll(
        'input[name="payment_method"]'
    );


paymentMethods.forEach(
    function(input) {

        input.addEventListener(
            'change',
            function() {

                payButton.disabled =
                    false;

            }
        );

    }
);


paymentForm.addEventListener(
    'submit',
    function() {

        payButton.disabled =
            true;

        payButton.textContent =
            'Processing Payment...';

    }
);

</script>

</body>

</html>