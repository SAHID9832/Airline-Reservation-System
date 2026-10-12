<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$page_title = 'My Trips';


/*
|--------------------------------------------------------------------------
| PASSENGER
|--------------------------------------------------------------------------
*/

$passengerId = current_passenger_id();

if (!$passengerId) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| HANDLE CANCELLATION
|--------------------------------------------------------------------------
*/

$flashMessage = '';
$flashType = '';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cancel_booking'])
) {

    $bookingId =
        (int)($_POST['booking_id'] ?? 0);


    if ($bookingId <= 0) {

        $flashMessage =
            'Invalid booking selected.';

        $flashType = 'error';

    } else {

        try {

            $pdo->beginTransaction();


            /*
            | Get booking belonging to passenger
            */

            $stmt = $pdo->prepare("
                SELECT
                    booking_id,
                    flight_id,
                    seat_id,
                    status
                FROM booking
                WHERE
                    booking_id = ?
                    AND passenger_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $bookingId,
                $passengerId
            ]);

            $booking =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$booking) {

                throw new Exception(
                    'Booking not found.'
                );
            }


            /*
            | Already cancelled
            */

            if (
                strtolower(
                    trim(
                        (string)$booking['status']
                    )
                ) === 'cancelled'
            ) {

                throw new Exception(
                    'This booking is already cancelled.'
                );
            }


            /*
            | Cancel booking
            */

            $stmt = $pdo->prepare("
                UPDATE booking
                SET status = 'Cancelled'
                WHERE
                    booking_id = ?
                    AND passenger_id = ?
            ");

            $stmt->execute([
                $bookingId,
                $passengerId
            ]);


            /*
            | Release flight seat
            */

            $stmt = $pdo->prepare("
                UPDATE flight_seat
                SET
                    status = 'Available',
                    booked_by = NULL,
                    booked_at = NULL
                WHERE
                    flight_id = ?
                    AND seat_id = ?
            ");

            $stmt->execute([
                $booking['flight_id'],
                $booking['seat_id']
            ]);


            /*
            | Refund payment status
            */

            $stmt = $pdo->prepare("
                UPDATE payment
                SET payment_status = 'Refunded'
                WHERE booking_id = ?
            ");

            $stmt->execute([
                $bookingId
            ]);


            $pdo->commit();


            $flashMessage =
                'Booking cancelled successfully. The seat is available again.';

            $flashType = 'success';


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $flashMessage =
                $e->getMessage();

            $flashType = 'error';
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET BOOKINGS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        b.booking_id,
        b.pnr,
        b.booking_date,
        b.status AS booking_status,
        b.total_amount,

        f.flight_id,
        f.flight_number,
        f.airline_name,
        f.departure_time,
        f.arrival_time,
        f.fare,

        src.airport_code AS source_code,
        src.city AS source_city,
        src.airport_name AS source_airport,

        dst.airport_code AS destination_code,
        dst.city AS destination_city,
        dst.airport_name AS destination_airport,

        ac.model,
        ac.aircraft_no,

        s.seat_number,
        s.seat_class,
        s.seat_type,
        s.seat_price,

        pay.payment_method,
        pay.payment_status,
        pay.payment_date

    FROM booking b

    INNER JOIN flight f
        ON b.flight_id = f.flight_id

    INNER JOIN airport src
        ON f.source_airport_id = src.airport_id

    INNER JOIN airport dst
        ON f.destination_airport_id = dst.airport_id

    INNER JOIN aircraft ac
        ON f.aircraft_id = ac.aircraft_id

    INNER JOIN seat s
        ON b.seat_id = s.seat_id

    LEFT JOIN payment pay
        ON b.booking_id = pay.booking_id

    WHERE
        b.passenger_id = ?

    ORDER BY
        b.booking_date DESC,
        b.booking_id DESC
");

$stmt->execute([
    $passengerId
]);

$bookings =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalBookings =
    count($bookings);

$confirmedCount = 0;
$pendingCount = 0;
$cancelledCount = 0;

$totalSpent = 0;


foreach ($bookings as $booking) {

    $status =
        strtolower(
            trim(
                (string)$booking['booking_status']
            )
        );


    if ($status === 'confirmed') {

        $confirmedCount++;

        $totalSpent +=
            (float)$booking['total_amount'];

    } elseif ($status === 'pending') {

        $pendingCount++;

    } elseif ($status === 'cancelled') {

        $cancelledCount++;
    }
}


/*
|--------------------------------------------------------------------------
| INCLUDE HEADER
|--------------------------------------------------------------------------
*/

include __DIR__ . '/includes/header.php';

?>


<style>

/* =========================================================
   MY TRIPS
========================================================= */

.trips-page {

    max-width: 1180px;

    margin: 0 auto;

    padding:
        35px 20px 70px;
}


/* =========================================================
   HERO
========================================================= */

.trips-hero {

    background:
        linear-gradient(
            135deg,
            #071d3b,
            #0b3570,
            #1474d4
        );

    color: white;

    border-radius: 24px;

    padding:
        35px;

    margin-bottom: 24px;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 18px 45px
        rgba(11,37,69,.16);
}


.trips-hero::before {

    content: "✈";

    position: absolute;

    right: 40px;

    top: 15px;

    font-size: 130px;

    opacity: .08;

    transform:
        rotate(-12deg);
}


.trips-hero h1 {

    margin: 0;

    color: white;

    font-size: 32px;

    font-weight: 900;
}


.trips-hero p {

    margin:
        8px 0 0;

    color:
        #c9dcf5;

    font-size: 13px;
}


/* =========================================================
   FLASH
========================================================= */

.trip-alert {

    padding:
        14px 18px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 13px;

    font-weight: 700;
}


.trip-alert.success {

    background: #ecfdf3;

    color: #027a48;

    border:
        1px solid #abefc6;
}


.trip-alert.error {

    background: #fef3f2;

    color: #b42318;

    border:
        1px solid #fecdca;
}


/* =========================================================
   STATS
========================================================= */

.trip-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}


.trip-stat {

    background: white;

    border:
        1px solid #e4e7ec;

    border-radius: 15px;

    padding: 18px;

    box-shadow:
        0 5px 20px
        rgba(16,24,40,.04);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.trip-stat:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 12px 30px
        rgba(16,24,40,.08);
}


.trip-stat-label {

    color: #667085;

    font-size: 10px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.trip-stat-value {

    margin-top: 6px;

    color: #0b2545;

    font-size: 24px;

    font-weight: 900;
}


/* =========================================================
   BOOKING CARD
========================================================= */

.booking-list {

    display:
        flex;

    flex-direction:
        column;

    gap: 18px;
}


.booking-card {

    background: white;

    border:
        1px solid #e4e7ec;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 7px 25px
        rgba(16,24,40,.05);

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.booking-card:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 15px 40px
        rgba(16,24,40,.09);
}


/* =========================================================
   CARD HEADER
========================================================= */

.booking-top {

    padding:
        18px 22px;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    border-bottom:
        1px solid #eef2f6;
}


.flight-info {

    display: flex;

    align-items: center;

    gap: 12px;
}


.flight-icon {

    width: 42px;

    height: 42px;

    border-radius: 12px;

    background:
        #eaf2ff;

    color:
        #2563eb;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;
}


.flight-number {

    color: #0b2545;

    font-size: 15px;

    font-weight: 900;
}


.airline {

    color: #667085;

    font-size: 10px;

    margin-top: 2px;
}


/* =========================================================
   STATUS
========================================================= */

.booking-status {

    padding:
        6px 11px;

    border-radius: 999px;

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}


.status-confirmed {

    background:
        #ecfdf3;

    color:
        #027a48;
}


.status-pending {

    background:
        #fff7ed;

    color:
        #c2410c;
}


.status-cancelled {

    background:
        #fef2f2;

    color:
        #b42318;
}


/* =========================================================
   ROUTE
========================================================= */

.route-section {

    padding:
        25px 22px;

    display: grid;

    grid-template-columns:
        1fr auto 1fr;

    align-items: center;

    gap: 20px;
}


.airport {

    display:
        flex;

    flex-direction:
        column;
}


.airport.right {

    align-items:
        flex-end;

    text-align:
        right;
}


.airport-code {

    color:
        #0b2545;

    font-size:
        28px;

    font-weight:
        900;
}


.airport-city {

    color:
        #667085;

    font-size:
        11px;

    margin-top:
        2px;
}


.flight-time {

    color:
        #344054;

    font-size:
        12px;

    font-weight:
        800;

    margin-top:
        7px;
}


.route-line {

    width:
        180px;

    text-align:
        center;

    color:
        #2563eb;
}


.route-plane {

    font-size:
        22px;

    margin-bottom:
        5px;
}


.route-dash {

    height:
        1px;

    background:
        #bfdbfe;

    position:
        relative;
}


.route-dash::before,
.route-dash::after {

    content: "";

    width:
        7px;

    height:
        7px;

    border-radius:
        50%;

    background:
        #2563eb;

    position:
        absolute;

    top:
        -3px;
}


.route-dash::before {

    left: 0;
}


.route-dash::after {

    right: 0;
}


.route-date {

    margin-top:
        6px;

    color:
        #98a2b3;

    font-size:
        9px;

    font-weight:
        700;
}


/* =========================================================
   DETAILS
========================================================= */

.booking-details {

    display:
        grid;

    grid-template-columns:
        repeat(5, 1fr);

    border-top:
        1px solid #eef2f6;

    border-bottom:
        1px solid #eef2f6;
}


.detail {

    padding:
        15px 18px;

    border-right:
        1px solid #eef2f6;
}


.detail:last-child {

    border-right:
        none;
}


.detail-label {

    color:
        #98a2b3;

    font-size:
        8px;

    font-weight:
        900;

    text-transform:
        uppercase;

    letter-spacing:
        .5px;
}


.detail-value {

    color:
        #344054;

    font-size:
        11px;

    font-weight:
        800;

    margin-top:
        5px;

    word-break:
        break-word;
}


/* =========================================================
   BOTTOM
========================================================= */

.booking-bottom {

    padding:
        16px 22px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;
}


.booking-meta {

    display:
        flex;

    align-items:
        center;

    gap:
        25px;
}


.pnr {

    color:
        #667085;

    font-size:
        9px;

    font-weight:
        700;
}


.pnr strong {

    display:
        block;

    color:
        #0b2545;

    font-size:
        12px;

    margin-top:
        2px;
}


.amount {

    color:
        #0b2545;

    font-size:
        17px;

    font-weight:
        900;
}


.actions {

    display:
        flex;

    gap:
        8px;

    flex-wrap:
        wrap;

    justify-content:
        flex-end;
}


.action-btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        9px 13px;

    border-radius:
        9px;

    text-decoration:
        none;

    font-size:
        9px;

    font-weight:
        900;

    transition:
        .2s ease;

    cursor:
        pointer;

    border:
        none;
}


.action-btn:hover {

    transform:
        translateY(-1px);

    text-decoration:
        none;
}


.ticket-btn {

    background:
        #2563eb;

    color:
        white !important;
}


.print-btn {

    background:
        #f2f4f7;

    color:
        #344054 !important;
}


.cancel-btn {

    background:
        #fff1f0;

    color:
        #b42318 !important;

    border:
        1px solid #fecdca;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background:
        white;

    border:
        1px solid #e4e7ec;

    border-radius:
        20px;

    padding:
        65px 25px;

    text-align:
        center;

    box-shadow:
        0 7px 25px
        rgba(16,24,40,.05);
}


.empty-icon {

    font-size:
        45px;

    margin-bottom:
        10px;
}


.empty h2 {

    margin:
        0 0 7px;

    color:
        #0b2545;
}


.empty p {

    margin:
        0 0 20px;

    color:
        #667085;

    font-size:
        13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:950px) {

    .trip-stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .booking-details {

        grid-template-columns:
            repeat(3, 1fr);
    }

    .detail:nth-child(3) {

        border-right:
            none;
    }

}


@media(max-width:700px) {

    .trips-page {

        padding:
            20px 12px 50px;
    }

    .trips-hero {

        padding:
            25px 20px;
    }

    .trips-hero h1 {

        font-size:
            26px;
    }

    .trip-stats {

        grid-template-columns:
            1fr 1fr;
    }

    .route-section {

        grid-template-columns:
            1fr;

        text-align:
            center;

        gap:
            14px;
    }

    .airport,
    .airport.right {

        align-items:
            center;

        text-align:
            center;
    }

    .route-line {

        width:
            100%;
    }

    .booking-details {

        grid-template-columns:
            1fr 1fr;
    }

    .detail {

        border-bottom:
            1px solid #eef2f6;
    }

    .detail:nth-child(even) {

        border-right:
            none;
    }

    .booking-bottom {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .actions {

        width:
            100%;

        justify-content:
            flex-start;
    }

}


@media(max-width:450px) {

    .trip-stats {

        grid-template-columns:
            1fr;
    }

    .booking-top {

        align-items:
            flex-start;

        flex-direction:
            column;

        gap:
            10px;
    }

    .booking-details {

        grid-template-columns:
            1fr;
    }

    .detail {

        border-right:
            none !important;
    }

    .booking-meta {

        width:
            100%;

        justify-content:
            space-between;

        gap:
            10px;
    }

    .action-btn {

        flex:
            1;
    }

}

</style>


<main class="trips-page">


<!-- =====================================================
     HERO
===================================================== -->

<section class="trips-hero">

    <h1>
        My Trips ✈
    </h1>

    <p>
        Manage your VektorSky bookings,
        tickets and upcoming journeys.
    </p>

</section>


<!-- =====================================================
     FLASH MESSAGE
===================================================== -->

<?php if ($flashMessage): ?>

    <div
        class="
            trip-alert
            <?= h($flashType) ?>
        "
    >

        <?= h($flashMessage) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="trip-stats">


    <div class="trip-stat">

        <div class="trip-stat-label">
            Total Trips
        </div>

        <div class="trip-stat-value">
            <?= number_format($totalBookings) ?>
        </div>

    </div>


    <div class="trip-stat">

        <div class="trip-stat-label">
            Confirmed
        </div>

        <div
            class="trip-stat-value"
            style="color:#027a48;"
        >
            <?= number_format($confirmedCount) ?>
        </div>

    </div>


    <div class="trip-stat">

        <div class="trip-stat-label">
            Pending
        </div>

        <div
            class="trip-stat-value"
            style="color:#c2410c;"
        >
            <?= number_format($pendingCount) ?>
        </div>

    </div>


    <div class="trip-stat">

        <div class="trip-stat-label">
            Cancelled
        </div>

        <div
            class="trip-stat-value"
            style="color:#b42318;"
        >
            <?= number_format($cancelledCount) ?>
        </div>

    </div>


</div>


<!-- =====================================================
     BOOKINGS
===================================================== -->

<?php if (empty($bookings)): ?>


    <div class="empty">

        <div class="empty-icon">
            ✈️
        </div>

        <h2>
            No trips yet
        </h2>

        <p>
            You haven't booked a flight with VektorSky yet.
        </p>

        <a
            href="index.php"
            class="action-btn ticket-btn"
        >
            Search Flights
        </a>

    </div>


<?php else: ?>


<div class="booking-list">


<?php foreach ($bookings as $booking): ?>


<?php

$status =
    strtolower(
        trim(
            (string)$booking['booking_status']
        )
    );


if ($status === 'confirmed') {

    $statusClass =
        'status-confirmed';

} elseif ($status === 'cancelled') {

    $statusClass =
        'status-cancelled';

} else {

    $statusClass =
        'status-pending';
}


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


$paymentStatus =
    strtolower(
        trim(
            (string)(
                $booking['payment_status']
                ?? ''
            )
        )
    );

?>


<article class="booking-card">


<!-- =================================================
     CARD TOP
================================================= -->

<div class="booking-top">


    <div class="flight-info">

        <div class="flight-icon">
            ✈
        </div>

        <div>

            <div class="flight-number">

                <?= h(
                    $booking['flight_number']
                ) ?>

            </div>

            <div class="airline">

                <?= h(
                    $booking['airline_name']
                ) ?>

            </div>

        </div>

    </div>


    <span
        class="
            booking-status
            <?= h($statusClass) ?>
        "
    >

        <?= h(
            $booking['booking_status']
        ) ?>

    </span>


</div>


<!-- =================================================
     ROUTE
================================================= -->

<div class="route-section">


    <div class="airport">

        <div class="airport-code">

            <?= h(
                $booking['source_code']
            ) ?>

        </div>

        <div class="airport-city">

            <?= h(
                $booking['source_city']
            ) ?>

        </div>

        <div class="flight-time">

            <?= h(
                $departureTime
            ) ?>

        </div>

    </div>


    <div class="route-line">

        <div class="route-plane">
            ✈
        </div>

        <div class="route-dash"></div>

        <div class="route-date">

            <?= h(
                $departureDate
            ) ?>

        </div>

    </div>


    <div class="airport right">

        <div class="airport-code">

            <?= h(
                $booking['destination_code']
            ) ?>

        </div>

        <div class="airport-city">

            <?= h(
                $booking['destination_city']
            ) ?>

        </div>

        <div class="flight-time">

            <?= h(
                $arrivalTime
            ) ?>

        </div>

    </div>


</div>


<!-- =================================================
     DETAILS
================================================= -->

<div class="booking-details">


    <div class="detail">

        <div class="detail-label">
            Seat
        </div>

        <div class="detail-value">

            <?= h(
                $booking['seat_number']
            ) ?>

            ·

            <?= h(
                $booking['seat_class']
            ) ?>

        </div>

    </div>


    <div class="detail">

        <div class="detail-label">
            Aircraft
        </div>

        <div class="detail-value">

            <?= h(
                $booking['model']
            ) ?>

        </div>

    </div>


    <div class="detail">

        <div class="detail-label">
            Payment
        </div>

        <div class="detail-value">

            <?= h(
                $booking['payment_method']
                ?? 'N/A'
            ) ?>

        </div>

    </div>


    <div class="detail">

        <div class="detail-label">
            Payment Status
        </div>

        <div
            class="detail-value"
            style="
                color:
                <?= $paymentStatus === 'success'
                    ? '#027a48'
                    : (
                        $paymentStatus === 'refunded'
                        ? '#b42318'
                        : '#c2410c'
                    );
                ?>;
            "
        >

            <?= h(
                ucfirst(
                    $paymentStatus ?: 'Pending'
                )
            ) ?>

        </div>

    </div>


    <div class="detail">

        <div class="detail-label">
            Booking ID
        </div>

        <div class="detail-value">

            #<?= (int)$booking['booking_id'] ?>

        </div>

    </div>


</div>


<!-- =================================================
     BOTTOM
================================================= -->

<div class="booking-bottom">


    <div class="booking-meta">


        <div class="pnr">

            PNR

            <strong>

                <?= h(
                    $booking['pnr']
                ) ?>

            </strong>

        </div>


        <div class="amount">

            ₹<?= number_format(
                (float)$booking['total_amount'],
                2
            ) ?>

        </div>


    </div>


    <div class="actions">


        <!-- VIEW TICKET -->

        <a
            href="
                confirmation.php?booking_id=<?= (int)$booking['booking_id'] ?>
            "
            class="
                action-btn
                ticket-btn
            "
        >
            🎫 View Ticket
        </a>


        <!-- PRINT -->

        <a
            href="
                confirmation.php?booking_id=<?= (int)$booking['booking_id'] ?>
            "
            target="_blank"
            class="
                action-btn
                print-btn
            "
        >
            🖨 Print
        </a>


        <!-- CANCEL -->

        <?php if (
            $status !== 'cancelled'
        ): ?>

            <form
                method="POST"
                style="display:inline;"
                onsubmit="
                    return confirm(
                        'Are you sure you want to cancel this booking? The seat will become available again.'
                    );
                "
            >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= (int)$booking['booking_id'] ?>"
                >

                <button
                    type="submit"
                    name="cancel_booking"
                    value="1"
                    class="
                        action-btn
                        cancel-btn
                    "
                >
                    × Cancel
                </button>

            </form>

        <?php endif; ?>


    </div>


</div>


</article>


<?php endforeach; ?>


</div>


<?php endif; ?>


</main>


<?php

include __DIR__ . '/includes/footer.php';

?>