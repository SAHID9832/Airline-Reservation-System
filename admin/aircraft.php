<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$page_title = 'Aircraft Management';
$asset_prefix = '../';


/*
|--------------------------------------------------------------------------
| DELETE AIRCRAFT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_aircraft'])
) {

    $aircraftId =
        (int)$_POST['delete_aircraft'];

    if ($aircraftId > 0) {

        try {

            /*
            | Check whether aircraft is being used by flights
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM flight
                WHERE aircraft_id = ?
            ");

            $stmt->execute([
                $aircraftId
            ]);

            $flightCount =
                (int)$stmt->fetchColumn();


            if ($flightCount > 0) {

                $_SESSION['admin_error'] =
                    'This aircraft cannot be deleted because it is assigned to one or more flights.';

            } else {

                /*
                | Delete seats belonging to aircraft
                */

                $stmt = $pdo->prepare("
                    DELETE FROM seat
                    WHERE aircraft_id = ?
                ");

                $stmt->execute([
                    $aircraftId
                ]);


                /*
                | Delete aircraft
                */

                $stmt = $pdo->prepare("
                    DELETE FROM aircraft
                    WHERE aircraft_id = ?
                ");

                $stmt->execute([
                    $aircraftId
                ]);


                $_SESSION['admin_success'] =
                    'Aircraft deleted successfully.';
            }

        } catch (PDOException $e) {

            $_SESSION['admin_error'] =
                'Unable to delete this aircraft. Please check related records.';
        }
    }


    header('Location: aircraft.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
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
| AIRCRAFT LIST
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        a.aircraft_id,
        a.aircraft_no,
        a.model,
        a.total_seats,
        a.airline_name,

        COUNT(
            DISTINCT s.seat_id
        ) AS actual_seats,

        COUNT(
            DISTINCT f.flight_id
        ) AS flight_count,

        COUNT(
            DISTINCT CASE
                WHEN fs.status = 'Booked'
                THEN fs.flight_seat_id
            END
        ) AS booked_seats

    FROM aircraft a

    LEFT JOIN seat s
        ON a.aircraft_id =
           s.aircraft_id

    LEFT JOIN flight f
        ON a.aircraft_id =
           f.aircraft_id

    LEFT JOIN flight_seat fs
        ON f.flight_id =
           fs.flight_id

    WHERE 1 = 1

";


$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "

        AND (
            a.aircraft_no LIKE ?
            OR a.model LIKE ?
            OR a.airline_name LIKE ?
        )

    ";

    $searchValue =
        '%' . $search . '%';

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

        a.aircraft_id,
        a.aircraft_no,
        a.model,
        a.total_seats,
        a.airline_name

    ORDER BY
        a.aircraft_id DESC

";


$stmt =
    $pdo->prepare($sql);

$stmt->execute(
    $params
);

$aircraft =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalAircraft =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM aircraft
    ")->fetchColumn();


$totalCapacity =
    (int)$pdo->query("
        SELECT COALESCE(
            SUM(total_seats),
            0
        )
        FROM aircraft
    ")->fetchColumn();


$actualSeatRecords =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM seat
    ")->fetchColumn();


$aircraftInUse =
    (int)$pdo->query("
        SELECT COUNT(
            DISTINCT aircraft_id
        )
        FROM flight
    ")->fetchColumn();


include '../includes/header.php';

?>


<style>

/* =========================================================
   AIRCRAFT PAGE
========================================================= */

.aircraft-page {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        30px 20px 60px;
}


/* =========================================================
   HEADER
========================================================= */

.aircraft-header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}


.aircraft-title h1 {

    margin: 0;

    color: var(--navy);

    font-size: 2rem;

    font-weight: 900;

    letter-spacing: -.5px;
}


.aircraft-title p {

    margin:
        7px 0 0;

    color: var(--muted);

    font-size: .9rem;
}


.aircraft-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.aircraft-btn {

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


.aircraft-btn:hover {

    text-decoration: none;

    transform:
        translateY(-2px);
}


.aircraft-btn-primary {

    color: white !important;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 7px 18px
        rgba(37,99,235,.2);
}


.aircraft-btn-secondary {

    color: var(--navy) !important;

    background: white;

    border:
        1px solid var(--border);
}


/* =========================================================
   MESSAGES
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
   STATISTICS
========================================================= */

.aircraft-stats {

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        15px;

    margin-bottom:
        22px;
}


.aircraft-stat {

    background:
        rgba(255,255,255,.94);

    border:
        1px solid var(--border);

    border-radius:
        15px;

    padding:
        18px;

    box-shadow:
        0 5px 20px
        rgba(11,37,69,.05);

    position:
        relative;

    overflow:
        hidden;

    transition:
        .2s ease;
}


.aircraft-stat::after {

    content: "";

    position: absolute;

    width: 80px;

    height: 80px;

    right: -25px;

    bottom: -30px;

    border-radius: 50%;

    background:
        var(--sky-light);
}


.aircraft-stat:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 28px
        rgba(11,37,69,.09);
}


.stat-icon {

    width:
        40px;

    height:
        40px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        11px;

    background:
        var(--sky-light);

    color:
        var(--sky);

    font-size:
        18px;

    margin-bottom:
        12px;
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
        1.55rem;

    font-weight:
        900;
}


/* =========================================================
   SEARCH
========================================================= */

.search-card {

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


.search-form {

    display:
        grid;

    grid-template-columns:
        1fr auto auto;

    gap:
        10px;

    align-items:
        center;
}


.search-input {

    width:
        100%;

    height:
        42px;

    padding:
        0 13px;

    border:
        1px solid var(--border);

    border-radius:
        9px;

    font-size:
        .85rem;

    transition:
        .2s ease;
}


.search-input:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.1);
}


.search-button {

    height:
        42px;

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

    font-size:
        .8rem;

    font-weight:
        800;

    cursor:
        pointer;
}


.clear-button {

    height:
        42px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        0 16px;

    border:
        1px solid var(--border);

    border-radius:
        9px;

    color:
        var(--muted);

    background:
        white;

    text-decoration:
        none;

    font-size:
        .8rem;

    font-weight:
        700;
}


.clear-button:hover {

    text-decoration:
        none;

    color:
        var(--navy);
}


/* =========================================================
   TABLE CARD
========================================================= */

.aircraft-table-card {

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


.table-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        17px 18px;

    border-bottom:
        1px solid var(--border);
}


.table-header h2 {

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


.table-scroll {

    overflow-x:
        auto;
}


.aircraft-table {

    width:
        100%;

    min-width:
        1050px;

    border-collapse:
        collapse;
}


.aircraft-table th {

    padding:
        12px 14px;

    background:
        #f7f9fc;

    color:
        #475467;

    border-bottom:
        1px solid var(--border);

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
}


.aircraft-table td {

    padding:
        15px 14px;

    border-bottom:
        1px solid #f0f2f5;

    font-size:
        .78rem;

    vertical-align:
        middle;
}


.aircraft-table tbody tr {

    transition:
        .15s ease;
}


.aircraft-table tbody tr:hover {

    background:
        #f8fbff;
}


.aircraft-table tbody tr:last-child td {

    border-bottom:
        none;
}


/* =========================================================
   AIRCRAFT INFO
========================================================= */

.aircraft-number {

    color:
        var(--navy);

    font-size:
        .88rem;

    font-weight:
        900;
}


.aircraft-model {

    margin-top:
        4px;

    color:
        var(--muted);

    font-size:
        .68rem;
}


.airline-name {

    color:
        #344054;

    font-size:
        .75rem;

    font-weight:
        700;
}


/* =========================================================
   CAPACITY
========================================================= */

.capacity-number {

    color:
        var(--navy);

    font-size:
        .9rem;

    font-weight:
        900;
}


.capacity-label {

    color:
        var(--muted);

    font-size:
        .65rem;
}


/* =========================================================
   SEAT MATCH
========================================================= */

.seat-match {

    min-width:
        130px;
}


.seat-count {

    display:
        flex;

    justify-content:
        space-between;

    margin-bottom:
        5px;

    font-size:
        .65rem;

    color:
        var(--muted);
}


.seat-count strong {

    color:
        var(--navy);
}


.seat-progress {

    height:
        6px;

    width:
        100%;

    max-width:
        120px;

    background:
        #e5e7eb;

    border-radius:
        999px;

    overflow:
        hidden;
}


.seat-progress-bar {

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


/* =========================================================
   FLIGHT COUNT
========================================================= */

.flight-count {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        5px 9px;

    border-radius:
        999px;

    background:
        #eff6ff;

    color:
        #1d4ed8;

    font-size:
        .65rem;

    font-weight:
        800;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display:
        flex;

    align-items:
        center;

    gap:
        7px;
}


.action {

    width:
        34px;

    height:
        34px;

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
        14px;

    transition:
        .2s ease;
}


.action:hover {

    text-decoration:
        none;

    transform:
        translateY(-2px);
}


.action-seats {

    background:
        #ecfdf3;

    color:
        #047857;
}


.action-view {

    background:
        #eff6ff;

    color:
        #2563eb;
}


.action-delete {

    border:
        none;

    background:
        #fef2f2;

    color:
        #dc2626;

    cursor:
        pointer;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {

    text-align:
        center;

    padding:
        65px 20px;

    color:
        var(--muted);
}


.empty-icon {

    font-size:
        42px;

    margin-bottom:
        10px;
}


.empty-state h3 {

    margin:
        0;

    color:
        var(--navy);
}


.empty-state p {

    margin:
        7px 0 18px;

    font-size:
        .8rem;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .aircraft-stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .search-form {

        grid-template-columns:
            1fr 1fr;
    }

}


@media(max-width:650px) {

    .aircraft-header {

        flex-direction:
            column;
    }


    .aircraft-actions {

        width:
            100%;
    }


    .aircraft-btn {

        flex:
            1;
    }


    .aircraft-stats {

        grid-template-columns:
            1fr;
    }


    .search-form {

        grid-template-columns:
            1fr;
    }

}

</style>


<main class="aircraft-page">


    <!-- =================================================
         HEADER
    ================================================= -->

    <div class="aircraft-header">


        <div class="aircraft-title">

            <h1>
                Aircraft Management
            </h1>

            <p>
                Manage VektorSky aircraft,
                capacity and seat inventory.
            </p>

        </div>


        <div class="aircraft-actions">


            <a
                href="dashboard.php"
                class="
                    aircraft-btn
                    aircraft-btn-secondary
                "
            >
                ← Dashboard
            </a>


            <a
                href="add_aircraft.php"
                class="
                    aircraft-btn
                    aircraft-btn-primary
                "
            >
                ✈ + Add Aircraft
            </a>


        </div>


    </div>


    <!-- =================================================
         MESSAGES
    ================================================= -->

    <?php if ($success): ?>

        <div class="
            message
            message-success
        ">

            ✓ <?= h($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div
            class="
                message
                message-error
            "
        >

            ⚠ <?= h($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         STATISTICS
    ================================================= -->

    <div class="aircraft-stats">


        <!-- TOTAL AIRCRAFT -->

        <div class="aircraft-stat">

            <div class="stat-icon">
                ✈
            </div>

            <div class="stat-label">
                Total Aircraft
            </div>

            <div class="stat-value">

                <?= number_format(
                    $totalAircraft
                ) ?>

            </div>

        </div>


        <!-- TOTAL CAPACITY -->

        <div class="aircraft-stat">

            <div class="stat-icon">
                💺
            </div>

            <div class="stat-label">
                Total Capacity
            </div>

            <div class="stat-value">

                <?= number_format(
                    $totalCapacity
                ) ?>

            </div>

        </div>


        <!-- SEAT RECORDS -->

        <div class="aircraft-stat">

            <div class="stat-icon">
                🪑
            </div>

            <div class="stat-label">
                Seat Records
            </div>

            <div class="stat-value">

                <?= number_format(
                    $actualSeatRecords
                ) ?>

            </div>

        </div>


        <!-- AIRCRAFT IN USE -->

        <div class="aircraft-stat">

            <div class="stat-icon">
                🛫
            </div>

            <div class="stat-label">
                Aircraft In Use
            </div>

            <div class="stat-value">

                <?= number_format(
                    $aircraftInUse
                ) ?>

            </div>

        </div>


    </div>


    <!-- =================================================
         SEARCH
    ================================================= -->

    <div class="search-card">


        <form
            method="GET"
            class="search-form"
        >


            <input
                type="text"
                name="search"
                class="search-input"
                value="<?= h($search) ?>"
                placeholder="
                    Search aircraft number,
                    model or airline...
                "
            >


            <button
                type="submit"
                class="search-button"
            >
                Search
            </button>


            <a
                href="aircraft.php"
                class="clear-button"
            >
                Clear
            </a>


        </form>


    </div>


    <!-- =================================================
         AIRCRAFT TABLE
    ================================================= -->

    <div class="aircraft-table-card">


        <div class="table-header">


            <h2>
                Aircraft Fleet
            </h2>


            <div class="result-count">

                <?= number_format(
                    count($aircraft)
                ) ?>

                aircraft(s)

            </div>


        </div>


        <div class="table-scroll">


            <?php if (
                empty($aircraft)
            ): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        ✈️
                    </div>


                    <h3>
                        No aircraft found
                    </h3>


                    <p>
                        Add an aircraft or change your search.
                    </p>


                    <a
                        href="add_aircraft.php"
                        class="
                            aircraft-btn
                            aircraft-btn-primary
                        "
                    >
                        + Add Aircraft
                    </a>


                </div>


            <?php else: ?>


                <table class="aircraft-table">


                    <thead>

                        <tr>

                            <th>
                                Aircraft
                            </th>

                            <th>
                                Airline
                            </th>

                            <th>
                                Capacity
                            </th>

                            <th>
                                Seat Inventory
                            </th>

                            <th>
                                Flights
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $aircraft
                        as $item
                    ): ?>


                        <?php

                        $capacity =
                            max(
                                1,
                                (int)
                                $item[
                                    'total_seats'
                                ]
                            );


                        $actualSeats =
                            (int)
                            $item[
                                'actual_seats'
                            ];


                        $seatPercentage =
                            min(
                                100,
                                round(
                                    (
                                        $actualSeats
                                        /
                                        $capacity
                                    ) * 100
                                )
                            );


                        $flightCount =
                            (int)
                            $item[
                                'flight_count'
                            ];


                        ?>


                        <tr>


                            <!-- AIRCRAFT -->

                            <td>

                                <div
                                    class="
                                        aircraft-number
                                    "
                                >

                                    <?= h(
                                        $item[
                                            'aircraft_no'
                                        ]
                                    ) ?>

                                </div>


                                <div
                                    class="
                                        aircraft-model
                                    "
                                >

                                    <?= h(
                                        $item[
                                            'model'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <!-- AIRLINE -->

                            <td>

                                <div
                                    class="
                                        airline-name
                                    "
                                >

                                    <?= h(
                                        $item[
                                            'airline_name'
                                        ]
                                        ?: 'VektorSky'
                                    ) ?>

                                </div>

                            </td>


                            <!-- CAPACITY -->

                            <td>

                                <div
                                    class="
                                        capacity-number
                                    "
                                >

                                    <?= number_format(
                                        $capacity
                                    ) ?>

                                </div>


                                <div
                                    class="
                                        capacity-label
                                    "
                                >

                                    seats

                                </div>

                            </td>


                            <!-- SEAT INVENTORY -->

                            <td>

                                <div
                                    class="
                                        seat-match
                                    "
                                >

                                    <div
                                        class="
                                            seat-count
                                        "
                                    >

                                        <span>

                                            <strong>

                                                <?= number_format(
                                                    $actualSeats
                                                ) ?>

                                            </strong>

                                            /
                                            <?= number_format(
                                                $capacity
                                            ) ?>

                                        </span>


                                        <span>

                                            <?= $seatPercentage ?>%

                                        </span>

                                    </div>


                                    <div
                                        class="
                                            seat-progress
                                        "
                                    >

                                        <div
                                            class="
                                                seat-progress-bar
                                            "
                                            style="
                                                width:
                                                <?= $seatPercentage ?>%;
                                            "
                                        ></div>

                                    </div>

                                </div>

                            </td>


                            <!-- FLIGHTS -->

                            <td>

                                <span
                                    class="
                                        flight-count
                                    "
                                >

                                    ✈

                                    <?= number_format(
                                        $flightCount
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div
                                    class="
                                        actions
                                    "
                                >


                                    <!-- MANAGE SEATS -->

                                    <a
                                        href="
                                            aircraft_seats.php?aircraft_id=<?= (int)$item['aircraft_id'] ?>
                                        "
                                        class="
                                            action
                                            action-seats
                                        "
                                        title="
                                            Manage Seats
                                        "
                                    >
                                        💺
                                    </a>


                                    <!-- VIEW FLIGHTS -->

                                    <a
                                        href="
                                            flights.php?search=<?= urlencode(
                                                $item[
                                                    'aircraft_no'
                                                ]
                                            ) ?>
                                        "
                                        class="
                                            action
                                            action-view
                                        "
                                        title="
                                            View Flights
                                        "
                                    >
                                        ✈
                                    </a>


                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        style="
                                            display:inline;
                                        "
                                    >

                                        <button
                                            type="submit"
                                            name="delete_aircraft"
                                            value="<?= (int)$item['aircraft_id'] ?>"
                                            class="
                                                action
                                                action-delete
                                            "
                                            title="
                                                Delete Aircraft
                                            "
                                            onclick="
                                                return confirm(
                                                    'Are you sure you want to delete this aircraft?'
                                                );
                                            "
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
| SMALL ENTRANCE ANIMATION
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        '.aircraft-stat, .aircraft-table-card'
    )
    .forEach(
        function(element, index) {

            element.style.opacity = '0';

            element.style.transform =
                'translateY(10px)';

            setTimeout(
                function() {

                    element.style.transition =
                        'opacity .4s ease, transform .4s ease';

                    element.style.opacity =
                        '1';

                    element.style.transform =
                        'translateY(0)';

                },
                index * 70
            );

        }
    );

</script>


<?php

include '../includes/footer.php';

?>