<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$page_title = 'Flight Management';
$asset_prefix = '../';


/*
|--------------------------------------------------------------------------
| DELETE FLIGHT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_flight'])
) {

    $flightId = (int)$_POST['delete_flight'];

    if ($flightId > 0) {

        try {

            /*
            | Check existing bookings
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM booking
                WHERE flight_id = ?
                  AND status <> 'Cancelled'
            ");

            $stmt->execute([
                $flightId
            ]);

            $activeBookings =
                (int)$stmt->fetchColumn();


            if ($activeBookings > 0) {

                $_SESSION['admin_error'] =
                    'This flight cannot be deleted because it has active bookings.';

            } else {

                /*
                | Delete flight seats first
                */

                $stmt = $pdo->prepare("
                    DELETE FROM flight_seat
                    WHERE flight_id = ?
                ");

                $stmt->execute([
                    $flightId
                ]);


                /*
                | Delete flight
                */

                $stmt = $pdo->prepare("
                    DELETE FROM flight
                    WHERE flight_id = ?
                ");

                $stmt->execute([
                    $flightId
                ]);


                $_SESSION['admin_success'] =
                    'Flight deleted successfully.';
            }

        } catch (PDOException $e) {

            $_SESSION['admin_error'] =
                'Unable to delete the flight. It may be referenced by another record.';
        }
    }


    header('Location: flights.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$success =
    $_SESSION['admin_success']
    ?? null;

$error =
    $_SESSION['admin_error']
    ?? null;

unset(
    $_SESSION['admin_success'],
    $_SESSION['admin_error']
);


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

$status =
    trim(
        $_GET['status']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| FLIGHTS QUERY
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        f.flight_id,
        f.flight_number,
        f.airline_name,

        f.departure_time,
        f.arrival_time,

        f.fare,

        src.airport_code AS source_code,
        src.city AS source_city,

        dst.airport_code AS destination_code,
        dst.city AS destination_city,

        a.aircraft_id,
        a.aircraft_no,
        a.model,
        a.total_seats,

        COUNT(
            DISTINCT fs.flight_seat_id
        ) AS total_flight_seats,

        COUNT(
            DISTINCT CASE
                WHEN fs.status = 'Booked'
                THEN fs.flight_seat_id
            END
        ) AS booked_seats,

        COUNT(
            DISTINCT CASE
                WHEN fs.status = 'Available'
                THEN fs.flight_seat_id
            END
        ) AS available_seats

    FROM flight f

    INNER JOIN airport src
        ON f.source_airport_id =
           src.airport_id

    INNER JOIN airport dst
        ON f.destination_airport_id =
           dst.airport_id

    INNER JOIN aircraft a
        ON f.aircraft_id =
           a.aircraft_id

    LEFT JOIN flight_seat fs
        ON f.flight_id =
           fs.flight_id

    WHERE 1 = 1
";


$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "

        AND (
            f.flight_number LIKE ?
            OR f.airline_name LIKE ?
            OR src.airport_code LIKE ?
            OR dst.airport_code LIKE ?
            OR src.city LIKE ?
            OR dst.city LIKE ?
        )

    ";

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| GROUP
|--------------------------------------------------------------------------
*/

$sql .= "

    GROUP BY

        f.flight_id,
        f.flight_number,
        f.airline_name,
        f.departure_time,
        f.arrival_time,
        f.fare,

        src.airport_code,
        src.city,

        dst.airport_code,
        dst.city,

        a.aircraft_id,
        a.aircraft_no,
        a.model,
        a.total_seats

";


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status === 'upcoming') {

    $sql .= "
        HAVING f.departure_time >= NOW()
    ";

} elseif ($status === 'completed') {

    $sql .= "
        HAVING f.arrival_time < NOW()
    ";

} elseif ($status === 'today') {

    $sql .= "
        HAVING DATE(f.departure_time) = CURDATE()
    ";
}


/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "

    ORDER BY
        f.departure_time ASC

";


$stmt =
    $pdo->prepare($sql);

$stmt->execute(
    $params
);

$flights =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalFlights =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM flight
    ")->fetchColumn();


$upcomingFlights =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM flight
        WHERE departure_time >= NOW()
    ")->fetchColumn();


$todayFlights =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM flight
        WHERE DATE(departure_time) = CURDATE()
    ")->fetchColumn();


$totalAircraft =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM aircraft
    ")->fetchColumn();


include '../includes/header.php';

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.flights-page {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        30px 20px 60px;
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


.page-title h1 {

    margin: 0;

    color: var(--navy);

    font-size: 2rem;

    font-weight: 900;
}


.page-title p {

    margin:
        7px 0 0;

    color: var(--muted);

    font-size: .9rem;
}


.page-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.flight-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        11px 17px;

    border-radius: 9px;

    font-size: .8rem;

    font-weight: 800;

    text-decoration: none;

    transition:
        .2s ease;
}


.flight-btn:hover {

    text-decoration: none;

    transform:
        translateY(-2px);
}


.flight-btn-primary {

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white !important;

    box-shadow:
        0 7px 18px
        rgba(37,99,235,.2);
}


.flight-btn-secondary {

    background: white;

    color: var(--navy) !important;

    border:
        1px solid var(--border);
}


/* =========================================================
   FLASH
========================================================= */

.message {

    padding:
        13px 16px;

    border-radius:
        10px;

    margin-bottom:
        20px;

    font-size:
        .85rem;

    font-weight:
        600;
}


.message-success {

    background:
        #ecfdf3;

    color:
        #047857;

    border:
        1px solid #a7f3d0;
}


.message-error {

    background:
        #fef2f2;

    color:
        #b91c1c;

    border:
        1px solid #fecaca;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom:
        22px;
}


.stat {

    background:
        rgba(255,255,255,.92);

    border:
        1px solid var(--border);

    border-radius:
        14px;

    padding:
        18px;

    box-shadow:
        0 5px 20px
        rgba(11,37,69,.05);

    transition:
        .2s ease;
}


.stat:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 28px
        rgba(11,37,69,.09);
}


.stat-label {

    color:
        var(--muted);

    font-size:
        .72rem;

    font-weight:
        700;
}


.stat-value {

    margin-top:
        5px;

    color:
        var(--navy);

    font-size:
        1.6rem;

    font-weight:
        900;
}


/* =========================================================
   FILTER CARD
========================================================= */

.filters {

    background:
        white;

    border:
        1px solid var(--border);

    border-radius:
        15px;

    padding:
        17px;

    margin-bottom:
        20px;

    box-shadow:
        0 4px 18px
        rgba(11,37,69,.04);
}


.filter-form {

    display:
        grid;

    grid-template-columns:
        1fr 180px auto auto;

    gap:
        10px;

    align-items:
        end;
}


.filter-field label {

    display:
        block;

    margin-bottom:
        6px;

    color:
        var(--muted);

    font-size:
        .72rem;

    font-weight:
        700;
}


.filter-field input,
.filter-field select {

    width:
        100%;

    padding:
        11px 12px;

    border:
        1px solid var(--border);

    border-radius:
        9px;

    font-size:
        .85rem;

    background:
        white;
}


.filter-field input:focus,
.filter-field select:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.1);
}


.filter-button {

    height:
        40px;

    padding:
        0 18px;

    border:
        none;

    border-radius:
        9px;

    background:
        #2563eb;

    color:
        white;

    font-weight:
        800;

    cursor:
        pointer;
}


.clear-button {

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    height:
        40px;

    padding:
        0 15px;

    border:
        1px solid var(--border);

    border-radius:
        9px;

    background:
        white;

    color:
        var(--muted);

    text-decoration:
        none;

    font-size:
        .8rem;

    font-weight:
        700;
}


/* =========================================================
   TABLE
========================================================= */

.table-card {

    background:
        white;

    border:
        1px solid var(--border);

    border-radius:
        16px;

    overflow:
        hidden;

    box-shadow:
        0 5px 22px
        rgba(11,37,69,.05);
}


.table-top {

    padding:
        17px 18px;

    border-bottom:
        1px solid var(--border);

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.table-top h2 {

    margin:
        0;

    color:
        var(--navy);

    font-size:
        1rem;

    font-weight:
        900;
}


.result-count {

    color:
        var(--muted);

    font-size:
        .72rem;
}


.table-wrapper {

    overflow-x:
        auto;
}


.flights-table {

    width:
        100%;

    border-collapse:
        collapse;

    min-width:
        1050px;
}


.flights-table th {

    background:
        #f7f9fc;

    color:
        #475467;

    padding:
        12px 14px;

    text-align:
        left;

    font-size:
        .68rem;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        .03em;

    border-bottom:
        1px solid var(--border);
}


.flights-table td {

    padding:
        14px;

    border-bottom:
        1px solid #f0f2f5;

    font-size:
        .78rem;

    vertical-align:
        middle;
}


.flights-table tr:last-child td {

    border-bottom:
        none;
}


.flights-table tbody tr {

    transition:
        .15s ease;
}


.flights-table tbody tr:hover {

    background:
        #f8fbff;
}


/* =========================================================
   FLIGHT
========================================================= */

.flight-number {

    color:
        var(--navy);

    font-weight:
        900;

    font-size:
        .85rem;
}


.airline {

    color:
        var(--muted);

    font-size:
        .68rem;

    margin-top:
        3px;
}


.route {

    color:
        var(--navy);

    font-weight:
        800;
}


.route-city {

    color:
        var(--muted);

    font-size:
        .65rem;

    margin-top:
        3px;
}


.time {

    color:
        var(--navy);

    font-weight:
        800;
}


.date {

    color:
        var(--muted);

    font-size:
        .65rem;

    margin-top:
        3px;
}


/* =========================================================
   SEAT BAR
========================================================= */

.seat-info {

    min-width:
        110px;
}


.seat-bar {

    height:
        6px;

    width:
        100%;

    max-width:
        100px;

    background:
        #e5e7eb;

    border-radius:
        999px;

    overflow:
        hidden;

    margin-top:
        5px;
}


.seat-progress {

    height:
        100%;

    background:
        linear-gradient(
            90deg,
            #2563eb,
            #06b6d4
        );

    border-radius:
        inherit;
}


.seat-text {

    font-size:
        .65rem;

    color:
        var(--muted);
}


/* =========================================================
   STATUS
========================================================= */

.flight-status {

    display:
        inline-flex;

    padding:
        5px 9px;

    border-radius:
        999px;

    font-size:
        .65rem;

    font-weight:
        800;
}


.status-upcoming {

    background:
        #ecfdf3;

    color:
        #047857;
}


.status-today {

    background:
        #eff6ff;

    color:
        #1d4ed8;
}


.status-completed {

    background:
        #f2f4f7;

    color:
        #667085;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display:
        flex;

    gap:
        7px;

    align-items:
        center;
}


.action {

    width:
        32px;

    height:
        32px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        8px;

    text-decoration:
        none;

    font-size:
        13px;

    transition:
        .2s;
}


.action:hover {

    text-decoration:
        none;

    transform:
        translateY(-2px);
}


.action-view {

    background:
        #eff6ff;

    color:
        #2563eb;
}


.action-seat {

    background:
        #ecfdf3;

    color:
        #047857;
}


.action-delete {

    background:
        #fef2f2;

    color:
        #dc2626;

    border:
        none;

    cursor:
        pointer;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align:
        center;

    padding:
        60px 20px;

    color:
        var(--muted);
}


.empty-icon {

    font-size:
        38px;

    margin-bottom:
        10px;
}


.empty h3 {

    margin:
        0;

    color:
        var(--navy);
}


.empty p {

    font-size:
        .8rem;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .filter-form {

        grid-template-columns:
            1fr 1fr;
    }

}


@media(max-width:650px) {

    .page-header {

        flex-direction:
            column;
    }


    .page-actions {

        width:
            100%;
    }


    .flight-btn {

        flex:
            1;
    }


    .stats {

        grid-template-columns:
            1fr;
    }


    .filter-form {

        grid-template-columns:
            1fr;
    }

}

</style>


<main class="flights-page">


    <!-- =================================================
         HEADER
    ================================================= -->

    <div class="page-header">


        <div class="page-title">

            <h1>
                Flight Management
            </h1>

            <p>
                View, monitor and manage all VektorSky flights.
            </p>

        </div>


        <div class="page-actions">

            <a
                href="dashboard.php"
                class="flight-btn flight-btn-secondary"
            >
                ← Dashboard
            </a>


            <a
                href="add_flight.php"
                class="flight-btn flight-btn-primary"
            >
                ✈ + Add Flight
            </a>

        </div>


    </div>


    <!-- =================================================
         MESSAGES
    ================================================= -->

    <?php if ($success): ?>

        <div class="message message-success">

            ✓ <?= h($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="message message-error">

            ⚠ <?= h($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         STATISTICS
    ================================================= -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                Total Flights
            </div>

            <div class="stat-value">
                <?= number_format($totalFlights) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Upcoming Flights
            </div>

            <div class="stat-value">
                <?= number_format($upcomingFlights) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Today's Flights
            </div>

            <div class="stat-value">
                <?= number_format($todayFlights) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Aircraft
            </div>

            <div class="stat-value">
                <?= number_format($totalAircraft) ?>
            </div>

        </div>


    </div>


    <!-- =================================================
         FILTER
    ================================================= -->

    <div class="filters">


        <form
            method="GET"
            class="filter-form"
        >


            <div class="filter-field">

                <label>
                    Search Flight
                </label>

                <input
                    type="text"
                    name="search"
                    value="<?= h($search) ?>"
                    placeholder="Flight, airline, airport or city..."
                >

            </div>


            <div class="filter-field">

                <label>
                    Flight Status
                </label>

                <select name="status">

                    <option value="">
                        All Flights
                    </option>

                    <option
                        value="upcoming"
                        <?= $status === 'upcoming'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Upcoming
                    </option>

                    <option
                        value="today"
                        <?= $status === 'today'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Today
                    </option>

                    <option
                        value="completed"
                        <?= $status === 'completed'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Completed
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="filter-button"
            >
                Search
            </button>


            <a
                href="flights.php"
                class="clear-button"
            >
                Clear
            </a>


        </form>


    </div>


    <!-- =================================================
         TABLE
    ================================================= -->

    <div class="table-card">


        <div class="table-top">

            <h2>
                All Flights
            </h2>

            <div class="result-count">

                <?= number_format(
                    count($flights)
                ) ?>

                result(s)

            </div>

        </div>


        <div class="table-wrapper">


            <?php if (empty($flights)): ?>


                <div class="empty">

                    <div class="empty-icon">
                        ✈️
                    </div>

                    <h3>
                        No flights found
                    </h3>

                    <p>
                        Try changing your search or add a new flight.
                    </p>

                </div>


            <?php else: ?>


                <table class="flights-table">


                    <thead>

                        <tr>

                            <th>
                                Flight
                            </th>

                            <th>
                                Route
                            </th>

                            <th>
                                Departure
                            </th>

                            <th>
                                Arrival
                            </th>

                            <th>
                                Aircraft
                            </th>

                            <th>
                                Seats
                            </th>

                            <th>
                                Fare
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $flights
                        as $flight
                    ): ?>


                        <?php

                        $departure =
                            new DateTime(
                                $flight['departure_time']
                            );

                        $arrival =
                            new DateTime(
                                $flight['arrival_time']
                            );


                        $now =
                            new DateTime();


                        if (
                            $departure->format('Y-m-d')
                            ===
                            $now->format('Y-m-d')
                        ) {

                            $statusText =
                                'Today';

                            $statusClass =
                                'status-today';

                        } elseif (
                            $departure > $now
                        ) {

                            $statusText =
                                'Upcoming';

                            $statusClass =
                                'status-upcoming';

                        } else {

                            $statusText =
                                'Completed';

                            $statusClass =
                                'status-completed';
                        }


                        $capacity =
                            max(
                                1,
                                (int)$flight['total_seats']
                            );


                        $booked =
                            (int)$flight['booked_seats'];


                        $available =
                            (int)$flight['available_seats'];


                        $bookedPercent =
                            min(
                                100,
                                round(
                                    (
                                        $booked
                                        /
                                        $capacity
                                    ) * 100
                                )
                            );

                        ?>


                        <tr>


                            <!-- FLIGHT -->

                            <td>

                                <div class="flight-number">

                                    <?= h(
                                        $flight['flight_number']
                                    ) ?>

                                </div>


                                <div class="airline">

                                    <?= h(
                                        $flight['airline_name']
                                    ) ?>

                                </div>

                            </td>


                            <!-- ROUTE -->

                            <td>

                                <div class="route">

                                    <?= h(
                                        $flight['source_code']
                                    ) ?>

                                    →

                                    <?= h(
                                        $flight['destination_code']
                                    ) ?>

                                </div>


                                <div class="route-city">

                                    <?= h(
                                        $flight['source_city']
                                    ) ?>

                                    →

                                    <?= h(
                                        $flight['destination_city']
                                    ) ?>

                                </div>

                            </td>


                            <!-- DEPARTURE -->

                            <td>

                                <div class="time">

                                    <?= h(
                                        $departure->format(
                                            'h:i A'
                                        )
                                    ) ?>

                                </div>

                                <div class="date">

                                    <?= h(
                                        $departure->format(
                                            'd M Y'
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- ARRIVAL -->

                            <td>

                                <div class="time">

                                    <?= h(
                                        $arrival->format(
                                            'h:i A'
                                        )
                                    ) ?>

                                </div>

                                <div class="date">

                                    <?= h(
                                        $arrival->format(
                                            'd M Y'
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- AIRCRAFT -->

                            <td>

                                <strong>

                                    <?= h(
                                        $flight['model']
                                    ) ?>

                                </strong>

                                <div class="airline">

                                    <?= h(
                                        $flight['aircraft_no']
                                    ) ?>

                                </div>

                            </td>


                            <!-- SEATS -->

                            <td>

                                <div class="seat-info">

                                    <div class="seat-text">

                                        <strong>
                                            <?= $booked ?>
                                        </strong>

                                        booked /

                                        <?= $capacity ?>

                                    </div>


                                    <div class="seat-bar">

                                        <div
                                            class="seat-progress"
                                            style="
                                                width:
                                                <?= $bookedPercent ?>%;
                                            "
                                        ></div>

                                    </div>


                                    <div class="seat-text">

                                        <?= $available ?>
                                        available

                                    </div>

                                </div>

                            </td>


                            <!-- FARE -->

                            <td>

                                <strong>

                                    ₹<?= number_format(
                                        (float)$flight['fare'],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        flight-status
                                        <?= h(
                                            $statusClass
                                        ) ?>
                                    "
                                >

                                    <?= h(
                                        $statusText
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!-- VIEW -->

                                    <a
                                        href="../book.php?flight_id=<?= (int)$flight['flight_id'] ?>"
                                        class="
                                            action
                                            action-view
                                        "
                                        title="View Flight"
                                    >
                                        👁
                                    </a>


                                    <!-- SEATS -->

                                    <a
                                        href="aircraft_seats.php?aircraft_id=<?= (int)$flight['aircraft_id'] ?>"
                                        class="
                                            action
                                            action-seat
                                        "
                                        title="Manage Aircraft Seats"
                                    >
                                        💺
                                    </a>


                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to delete flight <?= h($flight['flight_number']) ?>?'
                                            );
                                        "
                                    >

                                        <button
                                            type="submit"
                                            name="delete_flight"
                                            value="<?= (int)$flight['flight_id'] ?>"
                                            class="
                                                action
                                                action-delete
                                            "
                                            title="Delete Flight"
                                        >
                                            🗑
                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>


            <?php endif; ?>


        </div>

    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.action-delete')
    .forEach(
        function(button) {

            button.addEventListener(
                'click',
                function(event) {

                    const form =
                        button.closest('form');

                    if (!form) {
                        return;
                    }

                    const confirmed =
                        confirm(
                            'Delete this flight? This action cannot be undone.'
                        );

                    if (!confirmed) {

                        event.preventDefault();
                    }

                }
            );

        }
    );

</script>


<?php

include '../includes/footer.php';

?>