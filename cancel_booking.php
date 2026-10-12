<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$passengerId = current_passenger_id();

if (!$passengerId) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET BOOKING ID
|--------------------------------------------------------------------------
*/

$bookingId = (int)($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Invalid booking ID.'
    ];

    header('Location: my_bookings.php');
    exit;
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
        b.passenger_id,
        b.flight_id,
        b.seat_id,
        b.status AS booking_status,
        b.total_amount,
        b.booking_date,

        f.flight_number,
        f.airline_name,
        f.departure_time,
        f.arrival_time,

        src.airport_code AS source_code,
        src.city AS source_city,

        dst.airport_code AS destination_code,
        dst.city AS destination_city,

        s.seat_number,
        s.seat_class,
        s.seat_type,
        s.seat_price,

        pay.payment_method,
        pay.payment_status,
        pay.amount AS payment_amount

    FROM booking b

    INNER JOIN flight f
        ON b.flight_id = f.flight_id

    INNER JOIN airport src
        ON f.source_airport_id = src.airport_id

    INNER JOIN airport dst
        ON f.destination_airport_id = dst.airport_id

    INNER JOIN seat s
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

    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Booking not found or you do not have permission to cancel it.'
    ];

    header('Location: my_bookings.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK BOOKING STATUS
|--------------------------------------------------------------------------
*/

$bookingStatus = strtolower(
    trim(
        (string)$booking['booking_status']
    )
);

if ($bookingStatus === 'cancelled') {

    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'This booking has already been cancelled.'
    ];

    header('Location: my_bookings.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| HANDLE CANCELLATION
|--------------------------------------------------------------------------
*/

$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['confirm_cancel'])
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | BEGIN TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | LOCK BOOKING
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                booking_id,
                passenger_id,
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

        $lockedBooking = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$lockedBooking) {

            throw new Exception(
                'Booking not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK CURRENT STATUS AGAIN
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                trim(
                    (string)$lockedBooking['status']
                )
            ) === 'cancelled'
        ) {

            throw new Exception(
                'This booking is already cancelled.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CANCEL BOOKING
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | RELEASE FLIGHT SEAT
        |--------------------------------------------------------------------------
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
            $lockedBooking['flight_id'],
            $lockedBooking['seat_id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | REFUND PAYMENT STATUS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE payment
            SET payment_status = 'Refunded'
            WHERE booking_id = ?
        ");

        $stmt->execute([
            $bookingId
        ]);


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        */

        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  =>
                'Booking cancelled successfully. Your seat has been released and the payment has been marked as refunded.'
        ];


        header(
            'Location: my_bookings.php'
        );

        exit;


    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK
        |--------------------------------------------------------------------------
        */

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT DATE / TIME
|--------------------------------------------------------------------------
*/

try {

    $departure =
        new DateTime(
            $booking['departure_time']
        );

    $arrival =
        new DateTime(
            $booking['arrival_time']
        );

} catch (Throwable $e) {

    $departure = null;
    $arrival = null;
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$page_title = 'Cancel Booking';

include __DIR__ . '/includes/header.php';

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.cancel-page {

    max-width: 760px;

    margin: 0 auto;

    padding: 45px 20px 70px;
}


/* =========================================================
   CARD
========================================================= */

.cancel-card {

    background: #ffffff;

    border: 1px solid #e4e7ec;

    border-radius: 24px;

    overflow: hidden;

    box-shadow:
        0 20px 60px
        rgba(16, 24, 40, .10);

    animation:
        cardEnter .45s ease both;
}


@keyframes cardEnter {

    from {

        opacity: 0;

        transform:
            translateY(18px);

    }

    to {

        opacity: 1;

        transform:
            translateY(0);

    }
}


/* =========================================================
   HEADER
========================================================= */

.cancel-header {

    position: relative;

    overflow: hidden;

    padding: 34px 30px;

    text-align: center;

    color: #ffffff;

    background:
        linear-gradient(
            135deg,
            #7f1d1d,
            #b42318,
            #dc2626
        );
}


.cancel-header::before {

    content: "✈";

    position: absolute;

    right: 25px;

    top: -30px;

    font-size: 130px;

    opacity: .07;

    transform: rotate(-15deg);
}


.cancel-header::after {

    content: "";

    position: absolute;

    width: 180px;

    height: 180px;

    left: -80px;

    bottom: -100px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);
}


.cancel-icon {

    width: 62px;

    height: 62px;

    margin:
        0 auto 14px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(255,255,255,.15);

    border:
        1px solid
        rgba(255,255,255,.18);

    font-size: 27px;

    position: relative;

    z-index: 2;
}


.cancel-header h1 {

    margin: 0;

    color: #ffffff;

    font-size: 27px;

    font-weight: 900;

    position: relative;

    z-index: 2;
}


.cancel-header p {

    margin: 8px 0 0;

    color: #fee2e2;

    font-size: 12px;

    position: relative;

    z-index: 2;
}


/* =========================================================
   BODY
========================================================= */

.cancel-body {

    padding: 30px;
}


/* =========================================================
   ERROR
========================================================= */

.error-box {

    margin-bottom: 20px;

    padding: 14px 16px;

    border-radius: 12px;

    background: #fef3f2;

    border: 1px solid #fecdca;

    color: #b42318;

    font-size: 12px;

    font-weight: 700;
}


/* =========================================================
   WARNING
========================================================= */

.warning-box {

    display: flex;

    gap: 12px;

    align-items: flex-start;

    margin-bottom: 22px;

    padding: 15px 17px;

    border-radius: 13px;

    background: #fff7ed;

    border: 1px solid #fed7aa;

    color: #9a3412;

    font-size: 12px;

    line-height: 1.6;
}


.warning-icon {

    flex-shrink: 0;

    width: 28px;

    height: 28px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    background: #ffedd5;

}


/* =========================================================
   FLIGHT CARD
========================================================= */

.flight-card {

    border: 1px solid #e4e7ec;

    border-radius: 17px;

    overflow: hidden;

    background: #ffffff;

    margin-bottom: 20px;
}


.flight-header {

    padding: 16px 18px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    background: #f8fafc;

    border-bottom: 1px solid #e4e7ec;
}


.flight-title {

    display: flex;

    align-items: center;

    gap: 11px;
}


.flight-icon {

    width: 40px;

    height: 40px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eaf2ff;

    color: #2563eb;

    font-size: 18px;
}


.flight-number {

    color: #0b2545;

    font-size: 15px;

    font-weight: 900;
}


.airline {

    margin-top: 2px;

    color: #667085;

    font-size: 10px;
}


.pnr {

    color: #0b2545;

    font-size: 13px;

    font-weight: 900;

    letter-spacing: .5px;
}


.pnr-label {

    display: block;

    margin-bottom: 3px;

    color: #98a2b3;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .5px;
}


/* =========================================================
   ROUTE
========================================================= */

.route {

    display: grid;

    grid-template-columns:
        1fr auto 1fr;

    align-items: center;

    gap: 18px;

    padding: 25px 22px;
}


.airport {

    display: flex;

    flex-direction: column;
}


.airport.right {

    align-items: flex-end;

    text-align: right;
}


.airport-code {

    color: #0b2545;

    font-size: 31px;

    font-weight: 900;

    letter-spacing: -.5px;
}


.airport-city {

    margin-top: 3px;

    color: #667085;

    font-size: 11px;
}


.airport-time {

    margin-top: 7px;

    color: #344054;

    font-size: 11px;

    font-weight: 800;
}


.route-middle {

    width: 180px;

    text-align: center;
}


.route-plane {

    margin-bottom: 6px;

    color: #2563eb;

    font-size: 23px;

    animation:
        planeFloat 2s ease-in-out infinite;
}


@keyframes planeFloat {

    0%, 100% {
        transform: translateX(0);
    }

    50% {
        transform: translateX(4px);
    }
}


.route-line {

    height: 1px;

    position: relative;

    background: #bfdbfe;
}


.route-line::before,
.route-line::after {

    content: "";

    position: absolute;

    top: -3px;

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: #2563eb;
}


.route-line::before {
    left: 0;
}


.route-line::after {
    right: 0;
}


.route-date {

    margin-top: 7px;

    color: #98a2b3;

    font-size: 9px;

    font-weight: 700;
}


/* =========================================================
   DETAILS
========================================================= */

.details {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    border-top: 1px solid #e4e7ec;
}


.detail {

    padding: 15px;

    border-right: 1px solid #e4e7ec;
}


.detail:last-child {

    border-right: none;
}


.detail-label {

    color: #98a2b3;

    font-size: 8px;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.detail-value {

    margin-top: 5px;

    color: #344054;

    font-size: 11px;

    font-weight: 800;

    word-break: break-word;
}


/* =========================================================
   AMOUNT
========================================================= */

.amount-box {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 18px;

    margin-top: 18px;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #f8fbff,
            #eef5ff
        );

    border: 1px solid #dbeafe;
}


.amount-label {

    color: #667085;

    font-size: 11px;

    font-weight: 700;
}


.amount-label small {

    display: block;

    margin-top: 3px;

    color: #98a2b3;

    font-size: 9px;
}


.amount {

    color: #0b2545;

    font-size: 23px;

    font-weight: 900;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {

    display: flex;

    gap: 10px;

    margin-top: 25px;
}


.btn {

    min-height: 44px;

    flex: 1;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 11px 18px;

    border-radius: 10px;

    border: none;

    font-family: inherit;

    font-size: 11px;

    font-weight: 900;

    text-decoration: none;

    cursor: pointer;

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}


.btn:hover {

    transform:
        translateY(-2px);

    text-decoration: none;
}


.btn-back {

    background: #f2f4f7;

    color: #344054 !important;

    border: 1px solid #e4e7ec;
}


.btn-back:hover {

    background: #eaecf0;
}


.btn-cancel {

    background:
        linear-gradient(
            135deg,
            #dc2626,
            #b91c1c
        );

    color: #ffffff;

    box-shadow:
        0 7px 18px
        rgba(220,38,38,.20);
}


.btn-cancel:hover {

    box-shadow:
        0 10px 25px
        rgba(220,38,38,.28);
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .cancel-page {

        padding:
            25px 12px 50px;
    }


    .cancel-body {

        padding:
            20px;
    }


    .cancel-header {

        padding:
            28px 20px;
    }


    .route {

        grid-template-columns:
            1fr;

        text-align: center;

        gap: 12px;
    }


    .airport,
    .airport.right {

        align-items: center;

        text-align: center;
    }


    .route-middle {

        width: 100%;
    }


    .details {

        grid-template-columns:
            1fr 1fr;
    }


    .detail {

        border-bottom:
            1px solid #e4e7ec;
    }


    .detail:nth-child(even) {

        border-right:
            none;
    }

}


@media (max-width: 480px) {

    .flight-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .details {

        grid-template-columns:
            1fr;
    }


    .detail {

        border-right:
            none;

        border-bottom:
            1px solid #e4e7ec;
    }


    .detail:last-child {

        border-bottom:
            none;
    }


    .actions {

        flex-direction:
            column;
    }


    .airport-code {

        font-size:
            27px;
    }

}

</style>


<main class="cancel-page">


    <div class="cancel-card">


        <!-- =================================================
             HEADER
        ================================================= -->

        <div class="cancel-header">

            <div class="cancel-icon">
                ⚠
            </div>

            <h1>
                Cancel Booking?
            </h1>

            <p>
                Please review your flight details before continuing.
            </p>

        </div>


        <!-- =================================================
             BODY
        ================================================= -->

        <div class="cancel-body">


            <?php if ($error): ?>

                <div class="error-box">

                    <?= h($error) ?>

                </div>

            <?php endif; ?>


            <!-- WARNING -->

            <div class="warning-box">

                <div class="warning-icon">
                    ⚠️
                </div>

                <div>

                    <strong>
                        Please note:
                    </strong>

                    Cancelling this booking will release
                    your selected seat. Your payment status
                    will be changed to
                    <strong>Refunded</strong>.

                </div>

            </div>


            <!-- =================================================
                 FLIGHT CARD
            ================================================= -->

            <div class="flight-card">


                <div class="flight-header">


                    <div class="flight-title">

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


                    <div class="pnr">

                        <span class="pnr-label">
                            PNR
                        </span>

                        <?= h(
                            $booking['pnr']
                        ) ?>

                    </div>


                </div>


                <!-- ROUTE -->

                <div class="route">


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

                        <div class="airport-time">

                            <?php if ($departure): ?>

                                <?= h(
                                    $departure->format(
                                        'd M Y, h:i A'
                                    )
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="route-middle">

                        <div class="route-plane">
                            ✈
                        </div>

                        <div class="route-line"></div>

                        <div class="route-date">

                            Flight Route

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

                        <div class="airport-time">

                            <?php if ($arrival): ?>

                                <?= h(
                                    $arrival->format(
                                        'd M Y, h:i A'
                                    )
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>


                </div>


                <!-- DETAILS -->

                <div class="details">


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
                            Seat Type
                        </div>

                        <div class="detail-value">

                            <?= h(
                                $booking['seat_type']
                            ) ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Payment
                        </div>

                        <div class="detail-value">

                            <?= h(
                                $booking[
                                    'payment_method'
                                ] ?? 'N/A'
                            ) ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Booking ID
                        </div>

                        <div class="detail-value">

                            #<?= (int)
                                $booking[
                                    'booking_id'
                                ] ?>

                        </div>

                    </div>


                </div>


            </div>


            <!-- =================================================
                 AMOUNT
            ================================================= -->

            <div class="amount-box">

                <div class="amount-label">

                    Total Booking Amount

                    <small>
                        Payment status:
                        <?= h(
                            $booking[
                                'payment_status'
                            ] ?? 'N/A'
                        ) ?>
                    </small>

                </div>

                <div class="amount">

                    ₹<?= number_format(
                        (float)
                        $booking['total_amount'],
                        2
                    ) ?>

                </div>

            </div>


            <!-- =================================================
                 ACTIONS
            ================================================= -->

            <div class="actions">


                <a
                    href="my_bookings.php"
                    class="btn btn-back"
                >
                    ← Keep Booking
                </a>


                <form
                    method="POST"
                    style="flex:1; margin:0;"
                >

                    <button
                        type="submit"
                        name="confirm_cancel"
                        value="1"
                        class="btn btn-cancel"
                        onclick="
                            return confirm(
                                'Are you sure you want to cancel this booking?'
                            );
                        "
                    >
                        ✓ Yes, Cancel Booking
                    </button>

                </form>


            </div>


        </div>

    </div>

</main>


<?php

include __DIR__ . '/includes/footer.php';

?>