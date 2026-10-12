<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Choose Your Seat';


/*
|--------------------------------------------------------------------------
| Flight ID
|--------------------------------------------------------------------------
*/

$flight_id = (int)($_GET['id'] ?? 0);

if ($flight_id <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Flight
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        f.flight_id,
        f.flight_number,
        f.airline_name,
        f.departure_time,
        f.arrival_time,
        f.fare,

        src.airport_code AS source_code,
        src.airport_name AS source_name,
        src.city AS source_city,

        dst.airport_code AS destination_code,
        dst.airport_name AS destination_name,
        dst.city AS destination_city,

        ac.aircraft_id,
        ac.aircraft_no,
        ac.model AS aircraft_model,
        ac.total_seats

    FROM flight f

    JOIN airport src
        ON f.source_airport_id = src.airport_id

    JOIN airport dst
        ON f.destination_airport_id = dst.airport_id

    JOIN aircraft ac
        ON f.aircraft_id = ac.aircraft_id

    WHERE f.flight_id = ?

    LIMIT 1
");

$stmt->execute([
    $flight_id
]);

$flight = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$flight) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Flight Seats
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We use flight_seat here instead of only checking booking.
|
*/

$seatStmt = $pdo->prepare("
    SELECT

        fs.flight_seat_id,

        fs.status AS flight_seat_status,

        fs.booked_by,
        fs.booked_at,

        s.seat_id,
        s.seat_number,
        s.seat_class,
        s.seat_type,
        s.seat_price

    FROM flight_seat fs

    JOIN seat s
        ON fs.seat_id = s.seat_id

    WHERE fs.flight_id = ?

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

$seatStmt->execute([
    $flight_id
]);

$seats = $seatStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| If flight_seat is empty
|--------------------------------------------------------------------------
|
| Generate flight seats automatically from aircraft seats.
|
*/

if (empty($seats)) {

    $generateStmt = $pdo->prepare("
        INSERT INTO flight_seat
        (
            flight_id,
            seat_id,
            status
        )

        SELECT
            ?,
            s.seat_id,
            'Available'

        FROM seat s

        WHERE s.aircraft_id = ?

        AND NOT EXISTS
        (
            SELECT 1

            FROM flight_seat existing

            WHERE existing.flight_id = ?

            AND existing.seat_id = s.seat_id
        )
    ");

    $generateStmt->execute([
        $flight_id,
        $flight['aircraft_id'],
        $flight_id
    ]);


    /*
    |--------------------------------------------------------------------------
    | Load again
    |--------------------------------------------------------------------------
    */

    $seatStmt->execute([
        $flight_id
    ]);

    $seats = $seatStmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/*
|--------------------------------------------------------------------------
| Seat statistics
|--------------------------------------------------------------------------
*/

$totalSeats = count($seats);

$availableSeats = 0;
$bookedSeats = 0;
$businessSeats = 0;
$economySeats = 0;

foreach ($seats as $seat) {

    $status = strtolower(
        trim(
            $seat['flight_seat_status']
        )
    );

    $class = strtolower(
        trim(
            $seat['seat_class']
        )
    );

    if ($status === 'available') {

        $availableSeats++;

    } else {

        $bookedSeats++;
    }


    if ($class === 'business') {

        $businessSeats++;

    } else {

        $economySeats++;
    }
}


/*
|--------------------------------------------------------------------------
| Date / Time
|--------------------------------------------------------------------------
*/

$departureDate =
    date(
        'D, d M Y',
        strtotime(
            $flight['departure_time']
        )
    );

$departureTime =
    date(
        'H:i',
        strtotime(
            $flight['departure_time']
        )
    );

$arrivalTime =
    date(
        'H:i',
        strtotime(
            $flight['arrival_time']
        )
    );


/*
|--------------------------------------------------------------------------
| Flight duration
|--------------------------------------------------------------------------
*/

$departureTimestamp =
    strtotime(
        $flight['departure_time']
    );

$arrivalTimestamp =
    strtotime(
        $flight['arrival_time']
    );

$durationSeconds =
    $arrivalTimestamp
    -
    $departureTimestamp;

$durationHours =
    floor(
        $durationSeconds / 3600
    );

$durationMinutes =
    floor(
        ($durationSeconds % 3600) / 60
    );

$duration =
    $durationHours
    . 'h '
    . $durationMinutes
    . 'm';


/*
|--------------------------------------------------------------------------
| Include header
|--------------------------------------------------------------------------
*/

include __DIR__ . '/includes/header.php';

?>

<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.seat-page {

    max-width: 1400px;

    margin:
        25px auto 60px;

    padding:
        0 22px;

    color:
        #10203f;
}


/*
|--------------------------------------------------------------------------
| TOP BAR
|--------------------------------------------------------------------------
*/

.seat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 20px;
}

.back-link {

    color:
        #31527f;

    text-decoration: none;

    font-size: 13px;

    font-weight: 800;
}

.back-link:hover {

    color:
        #1769ff;
}


/*
|--------------------------------------------------------------------------
| FLIGHT HEADER
|--------------------------------------------------------------------------
*/

.flight-header {

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #f7fbff
        );

    border:
        1px solid #dfe8f3;

    border-radius:
        24px;

    padding:
        25px 28px;

    box-shadow:
        0 12px 40px
        rgba(16,24,40,.06);

    margin-bottom:
        20px;
}

.flight-header-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom:
        24px;
}

.airline-box {

    display: flex;

    align-items: center;

    gap: 13px;
}

.airline-logo {

    width: 52px;

    height: 52px;

    border-radius: 16px;

    background:
        linear-gradient(
            135deg,
            #1769ff,
            #08a6d9
        );

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    font-size: 18px;

    font-weight: 900;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.20);
}

.airline-name {

    font-size: 16px;

    font-weight: 900;
}

.flight-number {

    color:
        #667085;

    font-size: 11px;

    margin-top: 4px;
}

.aircraft-pill {

    padding:
        10px 15px;

    background:
        #edf4ff;

    color:
        #175cd3;

    border-radius:
        11px;

    font-size: 11px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| ROUTE
|--------------------------------------------------------------------------
*/

.route-display {

    display: grid;

    grid-template-columns:
        180px
        1fr
        180px;

    align-items: center;

    gap: 20px;
}

.route-point {

    text-align: left;
}

.route-point.right {

    text-align: right;
}

.route-time {

    font-size: 30px;

    font-weight: 900;

    letter-spacing:
        -1px;
}

.route-code {

    font-size: 14px;

    font-weight: 900;

    margin-top: 3px;
}

.route-city {

    color:
        #667085;

    font-size: 11px;

    margin-top: 3px;
}

.route-middle {

    position: relative;

    text-align: center;

    height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;
}

.route-middle-line {

    width: 100%;

    height: 2px;

    background:
        #d0d8e5;

    position: absolute;
}

.route-plane {

    position: relative;

    z-index: 2;

    background:
        white;

    padding:
        0 15px;

    font-size: 20px;
}

.route-duration {

    position: absolute;

    top: -2px;

    z-index: 3;

    background:
        white;

    padding:
        0 10px;

    font-size: 10px;

    color:
        #667085;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| MAIN GRID
|--------------------------------------------------------------------------
*/

.seat-layout {

    display: grid;

    grid-template-columns:
        minmax(600px, 1fr)
        350px;

    gap: 20px;

    align-items: start;
}


/*
|--------------------------------------------------------------------------
| MAP CARD
|--------------------------------------------------------------------------
*/

.map-card {

    background:
        white;

    border:
        1px solid #dfe7f1;

    border-radius:
        24px;

    box-shadow:
        0 12px 40px
        rgba(16,24,40,.06);

    overflow:
        hidden;
}

.map-header {

    padding:
        22px 25px;

    border-bottom:
        1px solid #e8edf4;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}

.map-title h2 {

    margin: 0;

    font-size: 20px;

    font-weight: 900;
}

.map-title p {

    margin:
        5px 0 0;

    color:
        #667085;

    font-size: 11px;
}

.seat-count {

    background:
        #ecfdf3;

    color:
        #087443;

    padding:
        10px 13px;

    border-radius:
        10px;

    font-size: 11px;

    font-weight: 900;
}


/*
|--------------------------------------------------------------------------
| LEGEND
|--------------------------------------------------------------------------
*/

.legend {

    display: flex;

    flex-wrap: wrap;

    gap: 16px;

    padding:
        17px 25px;

    border-bottom:
        1px solid #edf1f5;

    background:
        #fbfcfe;
}

.legend-item {

    display: flex;

    align-items: center;

    gap: 7px;

    color:
        #475467;

    font-size: 10px;

    font-weight: 700;
}

.legend-seat {

    width: 18px;

    height: 18px;

    border-radius: 5px;

    border: 1px solid #cbd5e1;
}


/*
|--------------------------------------------------------------------------
| AIRCRAFT CABIN
|--------------------------------------------------------------------------
*/

.aircraft-wrapper {

    background:
        linear-gradient(
            180deg,
            #eef5fb,
            #f8fbff
        );

    padding:
        35px 25px 50px;

    overflow-x: auto;
}


/*
|--------------------------------------------------------------------------
| AIRCRAFT BODY
|--------------------------------------------------------------------------
*/

.aircraft {

    width:
        min(620px, 100%);

    min-width:
        590px;

    margin:
        0 auto;

    background:
        #ffffff;

    border:
        2px solid #d5deea;

    border-radius:
        110px 110px 45px 45px;

    padding:
        45px 35px 35px;

    position: relative;

    box-shadow:
        0 20px 50px
        rgba(15,23,42,.10);
}


/*
|--------------------------------------------------------------------------
| NOSE
|--------------------------------------------------------------------------
*/

.aircraft-nose {

    text-align: center;

    color:
        #98a2b3;

    font-size: 10px;

    font-weight: 800;

    margin-bottom:
        20px;
}

.cockpit {

    width: 130px;

    height: 34px;

    border-radius:
        50%;

    background:
        #edf2f7;

    margin:
        0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    color:
        #667085;

    font-size: 9px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| COLUMN HEADERS
|--------------------------------------------------------------------------
*/

.seat-column-header {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap:
        10px;

    margin:
        0 4px 12px;

    text-align:
        center;
}

.seat-column-header span {

    color:
        #667085;

    font-size: 10px;

    font-weight: 900;
}

.seat-column-header span:nth-child(4) {

    margin-left:
        25px;
}


/*
|--------------------------------------------------------------------------
| ROW
|--------------------------------------------------------------------------
*/

.seat-row {

    display: grid;

    grid-template-columns:
        26px
        repeat(6, 1fr);

    gap:
        10px;

    align-items:
        center;

    margin-bottom:
        9px;
}

.row-number {

    color:
        #98a2b3;

    font-size: 9px;

    font-weight: 800;

    text-align:
        center;
}


/*
|--------------------------------------------------------------------------
| AISLE
|--------------------------------------------------------------------------
*/

.seat:nth-child(4) {

    margin-right:
        25px;
}


/*
|--------------------------------------------------------------------------
| SEAT
|--------------------------------------------------------------------------
*/

.seat {

    height:
        38px;

    min-width:
        42px;

    border-radius:
        8px;

    border:
        2px solid #cbd5e1;

    background:
        #ffffff;

    color:
        #344054;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size:
        9px;

    font-weight:
        900;

    cursor:
        pointer;

    position: relative;

    transition:
        transform .15s ease,
        box-shadow .15s ease,
        background .15s ease,
        border-color .15s ease;
}


/*
|--------------------------------------------------------------------------
| AVAILABLE
|--------------------------------------------------------------------------
*/

.seat.available {

    background:
        #ffffff;

    border-color:
        #94a3b8;
}

.seat.available:hover {

    transform:
        translateY(-2px);

    border-color:
        #1769ff;

    box-shadow:
        0 6px 15px
        rgba(37,99,235,.20);

    z-index: 5;
}


/*
|--------------------------------------------------------------------------
| ECONOMY
|--------------------------------------------------------------------------
*/

.seat.economy {

    border-color:
        #8bb7e8;

    background:
        #f5faff;
}


/*
|--------------------------------------------------------------------------
| BUSINESS
|--------------------------------------------------------------------------
*/

.seat.business {

    background:
        #fff8e8;

    border-color:
        #f0b429;

    color:
        #8a5a00;
}

.seat.business:hover {

    border-color:
        #d98b00;
}


/*
|--------------------------------------------------------------------------
| TAKEN
|--------------------------------------------------------------------------
*/

.seat.taken {

    background:
        #e7ebf0;

    border-color:
        #d3d9e1;

    color:
        #98a2b3;

    cursor:
        not-allowed;

    position: relative;
}

.seat.taken::after {

    content:
        '×';

    position: absolute;

    font-size: 20px;

    color:
        #a8b0bb;

    opacity:
        .75;
}


/*
|--------------------------------------------------------------------------
| SELECTED
|--------------------------------------------------------------------------
*/

.seat.selected {

    background:
        linear-gradient(
            135deg,
            #1769ff,
            #0758df
        );

    border-color:
        #0b4cc2;

    color:
        white;

    transform:
        translateY(-2px)
        scale(1.04);

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.35);

    z-index:
        10;
}


/*
|--------------------------------------------------------------------------
| PRICE BADGE
|--------------------------------------------------------------------------
*/

.seat-price {

    position: absolute;

    top:
        -25px;

    left:
        50%;

    transform:
        translateX(-50%);

    background:
        #101828;

    color:
        white;

    padding:
        4px 6px;

    border-radius:
        5px;

    font-size:
        7px;

    white-space:
        nowrap;

    opacity:
        0;

    pointer-events:
        none;

    transition:
        opacity .15s;
}

.seat:hover .seat-price {

    opacity:
        1;
}


/*
|--------------------------------------------------------------------------
| EMERGENCY / EXIT ROW VISUAL
|--------------------------------------------------------------------------
*/

.exit-row {

    margin:
        16px 0;

    border-top:
        1px dashed #cbd5e1;

    border-bottom:
        1px dashed #cbd5e1;

    padding:
        7px 0;

    text-align:
        center;

    color:
        #667085;

    font-size:
        8px;

    font-weight:
        800;
}


/*
|--------------------------------------------------------------------------
| TAIL
|--------------------------------------------------------------------------
*/

.aircraft-tail {

    text-align:
        center;

    margin-top:
        25px;

    color:
        #98a2b3;

    font-size:
        9px;

    font-weight:
        800;
}


/*
|--------------------------------------------------------------------------
| SUMMARY CARD
|--------------------------------------------------------------------------
*/

.summary-card {

    background:
        white;

    border:
        1px solid #dfe7f1;

    border-radius:
        24px;

    box-shadow:
        0 12px 40px
        rgba(16,24,40,.06);

    padding:
        23px;

    position:
        sticky;

    top:
        100px;
}

.summary-card h2 {

    margin:
        0;

    font-size:
        19px;

    font-weight:
        900;
}

.summary-subtitle {

    color:
        #667085;

    font-size:
        10px;

    margin-top:
        5px;

    margin-bottom:
        20px;
}


/*
|--------------------------------------------------------------------------
| SELECTED SEAT
|--------------------------------------------------------------------------
*/

.selected-box {

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #f8fbff
        );

    border:
        1px solid #bfdbfe;

    border-radius:
        15px;

    padding:
        17px;

    margin-bottom:
        20px;
}

.selected-label {

    color:
        #667085;

    font-size:
        9px;

    font-weight:
        700;
}

.selected-seat-number {

    font-size:
        28px;

    font-weight:
        900;

    color:
        #1769ff;

    margin-top:
        4px;
}

.selected-seat-info {

    font-size:
        10px;

    color:
        #475467;

    margin-top:
        4px;
}


/*
|--------------------------------------------------------------------------
| SUMMARY ROW
|--------------------------------------------------------------------------
*/

.price-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding:
        11px 0;

    border-bottom:
        1px solid #edf1f5;

    font-size:
        11px;
}

.price-row span:first-child {

    color:
        #667085;
}

.price-row strong {

    font-weight:
        900;
}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

.total-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding-top:
        18px;

    font-size:
        19px;

    font-weight:
        900;
}

.total-price {

    color:
        #1769ff;
}


/*
|--------------------------------------------------------------------------
| CONTINUE
|--------------------------------------------------------------------------
*/

.continue-btn {

    width:
        100%;

    height:
        54px;

    border:
        none;

    border-radius:
        13px;

    margin-top:
        20px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    color:
        white;

    font-size:
        13px;

    font-weight:
        900;

    cursor:
        pointer;

    box-shadow:
        0 8px 22px
        rgba(37,99,235,.22);

    transition:
        .2s;
}

.continue-btn:hover:not(:disabled) {

    transform:
        translateY(-1px);

    box-shadow:
        0 12px 28px
        rgba(37,99,235,.32);
}

.continue-btn:disabled {

    background:
        #d0d5dd;

    box-shadow:
        none;

    cursor:
        not-allowed;
}


/*
|--------------------------------------------------------------------------
| INFO
|--------------------------------------------------------------------------
*/

.secure-note {

    text-align:
        center;

    color:
        #667085;

    font-size:
        8px;

    line-height:
        1.6;

    margin-top:
        14px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media(max-width:950px) {

    .seat-layout {

        grid-template-columns:
            1fr;
    }

    .summary-card {

        position:
            static;

        order:
            -1;
    }

}

@media(max-width:650px) {

    .seat-page {

        padding:
            0 10px;
    }

    .flight-header {

        padding:
            18px;
    }

    .route-display {

        grid-template-columns:
            100px
            1fr
            100px;

        gap:
            8px;
    }

    .route-time {

        font-size:
            23px;
    }

    .route-code {

        font-size:
            12px;
    }

    .aircraft {

        min-width:
            520px;
    }

}

</style>


<div class="seat-page">


    <!-- ======================================================
         BACK
    ======================================================= -->

    <div class="seat-top">

        <a
            href="javascript:history.back()"
            class="back-link"
        >
            ← Back to flights
        </a>

    </div>


    <!-- ======================================================
         FLIGHT HEADER
    ======================================================= -->

    <section class="flight-header">


        <div class="flight-header-top">


            <div class="airline-box">

                <div class="airline-logo">
                    VS
                </div>

                <div>

                    <div class="airline-name">

                        <?= h(
                            $flight['airline_name']
                        ) ?>

                    </div>

                    <div class="flight-number">

                        Flight
                        <?= h(
                            $flight['flight_number']
                        ) ?>

                    </div>

                </div>

            </div>


            <div class="aircraft-pill">

                ✈
                <?= h(
                    $flight['aircraft_model']
                ) ?>

                ·

                <?= h(
                    $flight['total_seats']
                ) ?>

                seats

            </div>


        </div>


        <div class="route-display">


            <div class="route-point">

                <div class="route-time">

                    <?= h(
                        $departureTime
                    ) ?>

                </div>

                <div class="route-code">

                    <?= h(
                        $flight['source_code']
                    ) ?>

                </div>

                <div class="route-city">

                    <?= h(
                        $flight['source_city']
                    ) ?>

                </div>

            </div>


            <div class="route-middle">

                <div class="route-duration">

                    <?= h($duration) ?>

                </div>

                <div class="route-middle-line"></div>

                <div class="route-plane">
                    ✈
                </div>

            </div>


            <div class="route-point right">

                <div class="route-time">

                    <?= h(
                        $arrivalTime
                    ) ?>

                </div>

                <div class="route-code">

                    <?= h(
                        $flight['destination_code']
                    ) ?>

                </div>

                <div class="route-city">

                    <?= h(
                        $flight['destination_city']
                    ) ?>

                </div>

            </div>


        </div>


        <div style="
            margin-top:20px;
            color:#667085;
            font-size:11px;
            font-weight:700;
        ">

            📅
            <?= h($departureDate) ?>

            &nbsp;&nbsp;·&nbsp;&nbsp;

            Base fare:

            ₹<?= number_format(
                (float)$flight['fare'],
                2
            ) ?>

        </div>


    </section>


    <!-- ======================================================
         SEAT + SUMMARY
    ======================================================= -->

    <div class="seat-layout">


        <!-- ==================================================
             SEAT MAP
        =================================================== -->

        <section class="map-card">


            <div class="map-header">

                <div class="map-title">

                    <h2>
                        Choose your seat
                    </h2>

                    <p>
                        Select an available seat for your journey.
                    </p>

                </div>


                <div class="seat-count">

                    <?= h(
                        $availableSeats
                    ) ?>

                    available

                </div>

            </div>


            <!-- ==================================================
                 LEGEND
            =================================================== -->

            <div class="legend">


                <div class="legend-item">

                    <span
                        class="legend-seat"
                        style="
                            background:#f5faff;
                            border-color:#8bb7e8;
                        "
                    ></span>

                    Economy

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat"
                        style="
                            background:#fff8e8;
                            border-color:#f0b429;
                        "
                    ></span>

                    Business

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat"
                        style="
                            background:#1769ff;
                            border-color:#0b4cc2;
                        "
                    ></span>

                    Selected

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat"
                        style="
                            background:#e7ebf0;
                            border-color:#d3d9e1;
                        "
                    ></span>

                    Reserved

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat"
                        style="
                            background:white;
                            border-color:#94a3b8;
                        "
                    ></span>

                    Available

                </div>


            </div>


            <!-- ==================================================
                 AIRCRAFT
            =================================================== -->

            <form
                action="payment.php"
                method="POST"
                id="seatForm"
            >


                <input
                    type="hidden"
                    name="flight_id"
                    value="<?= h($flight_id) ?>"
                >


                <input
                    type="hidden"
                    name="seat_id"
                    id="seat_id_input"
                    value=""
                >


                <input
                    type="hidden"
                    name="flight_seat_id"
                    id="flight_seat_id_input"
                    value=""
                >


                <input
                    type="hidden"
                    name="seat_number"
                    id="seat_number_input"
                    value=""
                >


                <input
                    type="hidden"
                    name="seat_price"
                    id="seat_price_input"
                    value=""
                >


                <div class="aircraft-wrapper">


                    <div class="aircraft">


                        <div class="aircraft-nose">

                            <div class="cockpit">

                                FLIGHT DECK

                            </div>

                            FRONT

                        </div>


                        <div class="seat-column-header">

                            <span>A</span>
                            <span>B</span>
                            <span>C</span>
                            <span>D</span>
                            <span>E</span>
                            <span>F</span>

                        </div>


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | Convert seats into rows
                        |--------------------------------------------------------------------------
                        */

                        $seatRows = [];

                        foreach ($seats as $seat) {

                            $seatNumber =
                                strtoupper(
                                    trim(
                                        $seat['seat_number']
                                    )
                                );

                            if (
                                !preg_match(
                                    '/^([0-9]+)([A-F])$/',
                                    $seatNumber,
                                    $matches
                                )
                            ) {
                                continue;
                            }

                            $row =
                                (int)$matches[1];

                            $letter =
                                $matches[2];

                            $seatRows[$row][$letter] =
                                $seat;
                        }


                        ksort($seatRows);


                        /*
                        |--------------------------------------------------------------------------
                        | Render rows
                        |--------------------------------------------------------------------------
                        */

                        $rowCounter = 0;

                        foreach ($seatRows as $rowNumber => $rowSeats):

                            $rowCounter++;


                            /*
                            |--------------------------------------------------------------------------
                            | Add exit-row visual every 12 rows
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $rowCounter > 1
                                &&
                                $rowCounter % 12 === 0
                            ):

                            ?>

                                <div class="exit-row">

                                    EMERGENCY EXIT · EXTRA LEGROOM

                                </div>

                            <?php

                            endif;

                            ?>


                            <div class="seat-row">


                                <div class="row-number">

                                    <?= h(
                                        $rowNumber
                                    ) ?>

                                </div>


                                <?php

                                $letters = [
                                    'A',
                                    'B',
                                    'C',
                                    'D',
                                    'E',
                                    'F'
                                ];


                                foreach (
                                    $letters
                                    as $letter
                                ):

                                    $seat =
                                        $rowSeats[$letter]
                                        ?? null;


                                    if (!$seat):

                                    ?>

                                        <div></div>

                                    <?php

                                        continue;

                                    endif;


                                    $status =
                                        strtolower(
                                            trim(
                                                $seat[
                                                    'flight_seat_status'
                                                ]
                                            )
                                        );


                                    $class =
                                        strtolower(
                                            trim(
                                                $seat['seat_class']
                                            )
                                        );


                                    $seatPrice =
                                        (float)(
                                            $seat['seat_price']
                                            ?? 0
                                        );


                                    if (
                                        $status === 'available'
                                    ) {

                                        $seatClasses =
                                            'seat available';

                                    } else {

                                        $seatClasses =
                                            'seat taken';
                                    }


                                    if (
                                        $class === 'business'
                                    ) {

                                        $seatClasses .=
                                            ' business';

                                    } else {

                                        $seatClasses .=
                                            ' economy';
                                    }

                                ?>


                                    <button
                                        type="button"
                                        class="<?= h(
                                            $seatClasses
                                        ) ?>"
                                        data-seat-id="<?= h(
                                            $seat['seat_id']
                                        ) ?>"
                                        data-flight-seat-id="<?= h(
                                            $seat['flight_seat_id']
                                        ) ?>"
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
                                            $seatPrice
                                        ) ?>"
                                        <?= $status !== 'available'
                                            ? 'disabled'
                                            : ''
                                        ?>
                                    >

                                        <?= h(
                                            $letter
                                        ) ?>

                                        <span class="seat-price">

                                            ₹<?= number_format(
                                                $seatPrice,
                                                0
                                            ) ?>

                                        </span>

                                    </button>


                                <?php endforeach; ?>


                            </div>


                        <?php endforeach; ?>


                        <div class="aircraft-tail">

                            REAR GALLEY &nbsp; · &nbsp; LAVATORY

                        </div>


                    </div>


                </div>


            </form>


        </section>


        <!-- ==================================================
             SUMMARY
        =================================================== -->

        <aside class="summary-card">


            <h2>
                Your selection
            </h2>

            <div class="summary-subtitle">

                Choose a seat to continue

            </div>


            <div class="selected-box">


                <div class="selected-label">

                    SELECTED SEAT

                </div>


                <div
                    class="selected-seat-number"
                    id="selectedSeat"
                >

                    —

                </div>


                <div
                    class="selected-seat-info"
                    id="selectedSeatInfo"
                >

                    No seat selected

                </div>


            </div>


            <div class="price-row">

                <span>
                    Flight fare
                </span>

                <strong>

                    ₹<?= number_format(
                        (float)$flight['fare'],
                        2
                    ) ?>

                </strong>

            </div>


            <div class="price-row">

                <span>
                    Seat price
                </span>

                <strong
                    id="seatPrice"
                >

                    ₹0.00

                </strong>

            </div>


            <div class="price-row">

                <span>
                    Taxes & fees
                </span>

                <strong
                    id="taxPrice"
                >

                    ₹<?= number_format(
                        (float)$flight['fare'] * 0.05,
                        2
                    ) ?>

                </strong>

            </div>


            <div class="total-row">

                <span>
                    Total
                </span>

                <span
                    class="total-price"
                    id="totalPrice"
                >

                    ₹<?= number_format(
                        (float)$flight['fare'] * 1.05,
                        2
                    ) ?>

                </span>

            </div>


            <button
                type="submit"
                form="seatForm"
                class="continue-btn"
                id="continueBtn"
                disabled
            >

                Select a seat to continue →

            </button>


            <div class="secure-note">

                🔒 Your seat will be reserved only after
                successful payment.

            </div>


        </aside>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const seats =
    document.querySelectorAll(
        '.seat.available'
    );

const seatIdInput =
    document.getElementById(
        'seat_id_input'
    );

const flightSeatIdInput =
    document.getElementById(
        'flight_seat_id_input'
    );

const seatNumberInput =
    document.getElementById(
        'seat_number_input'
    );

const seatPriceInput =
    document.getElementById(
        'seat_price_input'
    );

const selectedSeat =
    document.getElementById(
        'selectedSeat'
    );

const selectedSeatInfo =
    document.getElementById(
        'selectedSeatInfo'
    );

const seatPrice =
    document.getElementById(
        'seatPrice'
    );

const taxPrice =
    document.getElementById(
        'taxPrice'
    );

const totalPrice =
    document.getElementById(
        'totalPrice'
    );

const continueBtn =
    document.getElementById(
        'continueBtn'
    );


const baseFare =
    <?= json_encode(
        (float)$flight['fare']
    ) ?>;


/*
|--------------------------------------------------------------------------
| Format currency
|--------------------------------------------------------------------------
*/

function money(value)
{

    return '₹' +
        Number(value)
            .toLocaleString(
                'en-IN',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

}


/*
|--------------------------------------------------------------------------
| Select seat
|--------------------------------------------------------------------------
*/

seats.forEach(
    function(seat)
    {

        seat.addEventListener(
            'click',
            function()
            {

                /*
                |--------------------------------------------------------------------------
                | Remove previous selection
                |--------------------------------------------------------------------------
                */

                seats.forEach(
                    function(item)
                    {
                        item.classList.remove(
                            'selected'
                        );
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Select current
                |--------------------------------------------------------------------------
                */

                seat.classList.add(
                    'selected'
                );


                /*
                |--------------------------------------------------------------------------
                | Read data
                |--------------------------------------------------------------------------
                */

                const seatId =
                    seat.dataset.seatId;

                const flightSeatId =
                    seat.dataset.flightSeatId;

                const seatNumber =
                    seat.dataset.seatNumber;

                const seatClass =
                    seat.dataset.seatClass;

                const seatType =
                    seat.dataset.seatType;

                const price =
                    parseFloat(
                        seat.dataset.seatPrice
                    ) || 0;


                /*
                |--------------------------------------------------------------------------
                | Hidden fields
                |--------------------------------------------------------------------------
                */

                seatIdInput.value =
                    seatId;

                flightSeatIdInput.value =
                    flightSeatId;

                seatNumberInput.value =
                    seatNumber;

                seatPriceInput.value =
                    price;


                /*
                |--------------------------------------------------------------------------
                | Sidebar
                |--------------------------------------------------------------------------
                */

                selectedSeat.textContent =
                    seatNumber;

                selectedSeatInfo.textContent =
                    seatClass
                    + ' · '
                    + seatType;


                /*
                |--------------------------------------------------------------------------
                | Price
                |--------------------------------------------------------------------------
                */

                const subtotal =
                    baseFare
                    +
                    price;

                const tax =
                    subtotal * 0.05;

                const total =
                    subtotal + tax;


                seatPrice.textContent =
                    money(price);

                taxPrice.textContent =
                    money(tax);

                totalPrice.textContent =
                    money(total);


                /*
                |--------------------------------------------------------------------------
                | Continue button
                |--------------------------------------------------------------------------
                */

                continueBtn.disabled =
                    false;

                continueBtn.textContent =
                    'Continue to Payment →';


                /*
                |--------------------------------------------------------------------------
                | Scroll summary into view on mobile
                |--------------------------------------------------------------------------
                */

                if (
                    window.innerWidth < 950
                ) {

                    document
                        .querySelector(
                            '.summary-card'
                        )
                        .scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });

                }

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| Form validation
|--------------------------------------------------------------------------
*/

document
    .getElementById('seatForm')
    .addEventListener(
        'submit',
        function(event)
        {

            if (
                !seatIdInput.value
                ||
                !flightSeatIdInput.value
            ) {

                event.preventDefault();

                alert(
                    'Please select an available seat first.'
                );

                return;
            }

        }
    );

</script>


<?php

include __DIR__ . '/includes/footer.php';

?>