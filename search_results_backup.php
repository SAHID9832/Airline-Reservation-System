<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| Search inputs
|--------------------------------------------------------------------------
|
| Supports both GET and POST so this page works with your existing
| search form regardless of whether it submits GET or POST.
|
*/

$from = trim($_GET['from'] ?? $_POST['from'] ?? '');
$to   = trim($_GET['to'] ?? $_POST['to'] ?? '');

$date = trim($_GET['date'] ?? $_POST['date'] ?? '');

$travellers = (int)(
    $_GET['travellers']
    ?? $_POST['travellers']
    ?? 1
);

$cabin = trim(
    $_GET['cabin']
    ?? $_POST['cabin']
    ?? 'Economy'
);

if ($travellers < 1) {
    $travellers = 1;
}


/*
|--------------------------------------------------------------------------
| Search flights
|--------------------------------------------------------------------------
*/

$flights = [];

$error = '';

try {

    /*
    |--------------------------------------------------------------------------
    | Build dynamic WHERE conditions
    |--------------------------------------------------------------------------
    */

    $conditions = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | From airport
    |--------------------------------------------------------------------------
    |
    | Allows:
    |
    | BLR
    | Bengaluru
    | Bangalore
    | Kempegowda
    |
    */

    if ($from !== '') {

        $conditions[] = "
            (
                src.airport_code LIKE :from_code
                OR src.city LIKE :from_city
                OR src.airport_name LIKE :from_name
                OR src.keywords LIKE :from_keywords
                OR src.icao_code LIKE :from_icao
            )
        ";

        $fromSearch = '%' . $from . '%';

        $params['from_code'] = $fromSearch;
        $params['from_city'] = $fromSearch;
        $params['from_name'] = $fromSearch;
        $params['from_keywords'] = $fromSearch;
        $params['from_icao'] = $fromSearch;
    }


    /*
    |--------------------------------------------------------------------------
    | To airport
    |--------------------------------------------------------------------------
    */

    if ($to !== '') {

        $conditions[] = "
            (
                dst.airport_code LIKE :to_code
                OR dst.city LIKE :to_city
                OR dst.airport_name LIKE :to_name
                OR dst.keywords LIKE :to_keywords
                OR dst.icao_code LIKE :to_icao
            )
        ";

        $toSearch = '%' . $to . '%';

        $params['to_code'] = $toSearch;
        $params['to_city'] = $toSearch;
        $params['to_name'] = $toSearch;
        $params['to_keywords'] = $toSearch;
        $params['to_icao'] = $toSearch;
    }


    /*
    |--------------------------------------------------------------------------
    | Date
    |--------------------------------------------------------------------------
    */

    if ($date !== '') {

        $conditions[] = "
            DATE(f.departure_time) = :flight_date
        ";

        $params['flight_date'] = $date;
    }


    /*
    |--------------------------------------------------------------------------
    | Only future flights
    |--------------------------------------------------------------------------
    */

    $conditions[] = "
        f.departure_time >= NOW()
    ";


    /*
    |--------------------------------------------------------------------------
    | Build WHERE
    |--------------------------------------------------------------------------
    */

    $where = '';

    if (!empty($conditions)) {

        $where =
            'WHERE '
            . implode(
                ' AND ',
                $conditions
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Get flights
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

            src.airport_id AS source_airport_id,
            src.airport_code AS source_code,
            src.airport_name AS source_airport_name,
            src.city AS source_city,

            dst.airport_id AS destination_airport_id,
            dst.airport_code AS destination_code,
            dst.airport_name AS destination_airport_name,
            dst.city AS destination_city,

            ac.aircraft_id,
            ac.aircraft_no,
            ac.model AS aircraft_model,
            ac.total_seats,

            COALESCE(
                SUM(
                    CASE
                        WHEN fs.status = 'Available'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS available_seats

        FROM flight f

        JOIN airport src
            ON f.source_airport_id = src.airport_id

        JOIN airport dst
            ON f.destination_airport_id = dst.airport_id

        JOIN aircraft ac
            ON f.aircraft_id = ac.aircraft_id

        LEFT JOIN flight_seat fs
            ON fs.flight_id = f.flight_id

        $where

        GROUP BY

            f.flight_id,
            f.flight_number,
            f.airline_name,
            f.departure_time,
            f.arrival_time,
            f.fare,

            src.airport_id,
            src.airport_code,
            src.airport_name,
            src.city,

            dst.airport_id,
            dst.airport_code,
            dst.airport_name,
            dst.city,

            ac.aircraft_id,
            ac.aircraft_no,
            ac.model,
            ac.total_seats

        HAVING
            available_seats >= :travellers

        ORDER BY
            f.departure_time ASC
    ";

    $params['travellers'] = $travellers;

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $flights = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (Throwable $e) {

    $error =
        'Unable to search flights. '
        . $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function format_time($datetime)
{
    return date(
        'H:i',
        strtotime($datetime)
    );
}


function format_date($datetime)
{
    return date(
        'D, d M',
        strtotime($datetime)
    );
}


function flight_duration(
    $departure,
    $arrival
) {

    $start =
        new DateTime($departure);

    $end =
        new DateTime($arrival);

    $seconds =
        $end->getTimestamp()
        -
        $start->getTimestamp();

    $hours =
        floor($seconds / 3600);

    $minutes =
        floor(
            ($seconds % 3600) / 60
        );

    return
        $hours . 'h '
        . $minutes . 'm';
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
    VektorSky | Available Flights
</title>


<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background:
        #f5f8fc;

    color:
        #0b1736;
}


/*
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
*/

.navbar {

    height: 80px;

    background: white;

    border-bottom:
        1px solid #e5eaf2;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 5%;

    position: sticky;

    top: 0;

    z-index: 100;
}

.logo {

    display: flex;

    align-items: center;

    gap: 12px;

    font-size: 26px;

    font-weight: 900;

    color:
        #0b1736;
}

.logo-icon {

    width: 52px;

    height: 52px;

    border-radius: 17px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #1769ff,
            #04a7d9
        );

    color: white;

    font-size: 25px;

    box-shadow:
        0 8px 22px
        rgba(37,99,235,.25);
}

.logo span {

    color:
        #1769ff;
}

.menu {

    font-size: 28px;

    cursor: pointer;
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.container {

    width:
        min(
            1180px,
            calc(100% - 40px)
        );

    margin:
        35px auto 60px;
}


/*
|--------------------------------------------------------------------------
| SEARCH SUMMARY
|--------------------------------------------------------------------------
*/

.search-summary {

    background:
        white;

    border:
        1px solid #e1e7f0;

    border-radius:
        22px;

    padding:
        22px 28px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 32px;

    box-shadow:
        0 8px 30px
        rgba(16,24,40,.04);
}

.route {

    display: flex;

    align-items: center;

    gap: 18px;
}

.airport {

    font-size: 24px;

    font-weight: 900;
}

.airport small {

    display: block;

    font-size: 11px;

    color:
        #667085;

    font-weight: 500;

    margin-top: 4px;
}

.route-arrow {

    width: 42px;

    height: 42px;

    border-radius: 50%;

    background:
        #edf4ff;

    display: flex;

    align-items: center;

    justify-content: center;

    color:
        #1769ff;

    font-size: 20px;
}

.search-info {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    justify-content: flex-end;
}

.info-pill {

    padding:
        11px 16px;

    border-radius:
        11px;

    background:
        #f0f4f9;

    color:
        #19345d;

    font-size: 13px;

    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.results-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;
}

.results-title h1 {

    margin: 0;

    font-size: 30px;

    font-weight: 900;
}

.results-title p {

    margin:
        5px 0 0;

    color:
        #667085;

    font-size: 14px;
}

.sort-box {

    display: flex;

    align-items: center;

    gap: 10px;

    color:
        #667085;

    font-size: 13px;

    font-weight: 700;
}

.sort-box select {

    padding:
        13px 18px;

    border:
        1px solid #cbd5e1;

    border-radius:
        12px;

    background:
        white;

    font-size: 15px;

    color:
        #101828;

    outline: none;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.error {

    background:
        #fff1f2;

    border:
        1px solid #fecdd3;

    color:
        #be123c;

    padding:
        16px 20px;

    border-radius:
        14px;

    margin-bottom:
        20px;

    font-size: 13px;

    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| FLIGHT CARD
|--------------------------------------------------------------------------
*/

.flight-card {

    background:
        white;

    border:
        1px solid #e0e7f0;

    border-radius:
        22px;

    padding:
        28px 30px;

    margin-bottom:
        18px;

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;

    box-shadow:
        0 7px 25px
        rgba(16,24,40,.04);
}

.flight-card:hover {

    transform:
        translateY(-2px);

    border-color:
        #b8cffd;

    box-shadow:
        0 14px 40px
        rgba(37,99,235,.10);
}


/*
|--------------------------------------------------------------------------
| AIRLINE
|--------------------------------------------------------------------------
*/

.flight-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom:
        25px;
}

.airline-info {

    display: flex;

    align-items: center;

    gap: 13px;
}

.airline-logo {

    width: 54px;

    height: 54px;

    border-radius: 15px;

    background:
        linear-gradient(
            135deg,
            #1769ff,
            #03a9d9
        );

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

    font-weight: 900;
}

.airline-name {

    font-size: 16px;

    font-weight: 900;
}

.flight-number {

    color:
        #667085;

    font-size: 12px;

    margin-top: 4px;
}


/*
|--------------------------------------------------------------------------
| TIMES
|--------------------------------------------------------------------------
*/

.flight-route {

    display: grid;

    grid-template-columns:
        150px
        1fr
        150px;

    align-items: center;

    gap: 20px;

    margin-bottom:
        25px;
}

.time-block {

    text-align: left;
}

.time-block.right {

    text-align: right;
}

.time {

    font-size: 30px;

    font-weight: 900;

    color:
        #081735;
}

.location {

    color:
        #53657f;

    font-size: 13px;

    margin-top: 5px;
}

.route-line {

    position: relative;

    height: 50px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;
}

.line {

    width: 100%;

    height: 2px;

    background:
        #cbd5e1;

    position: absolute;

    top: 28px;
}

.plane {

    position: relative;

    z-index: 2;

    background:
        white;

    padding:
        0 12px;

    font-size: 20px;

}

.duration {

    position: relative;

    z-index: 3;

    background:
        white;

    padding:
        0 10px;

    font-size: 12px;

    font-weight: 800;

    color:
        #475467;

}


/*
|--------------------------------------------------------------------------
| TAGS
|--------------------------------------------------------------------------
*/

.tags {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    padding-bottom:
        22px;

    border-bottom:
        1px solid #e5eaf0;
}

.tag {

    padding:
        10px 14px;

    background:
        #f3f6fa;

    border-radius:
        10px;

    color:
        #244166;

    font-size: 12px;

    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| BOTTOM
|--------------------------------------------------------------------------
*/

.flight-bottom {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding-top:
        22px;

    gap: 20px;
}

.availability {

    color:
        #078a3e;

    font-size: 13px;

    font-weight: 800;

    margin-top: 8px;
}

.fare-label {

    color:
        #667085;

    font-size: 12px;

    margin-bottom: 3px;
}

.fare {

    font-size: 32px;

    font-weight: 900;

    color:
        #0b1736;
}

.fare small {

    font-size: 12px;

    font-weight: 500;

    color:
        #667085;

    margin-left: 5px;
}


/*
|--------------------------------------------------------------------------
| SELECT BUTTON
|--------------------------------------------------------------------------
*/

.select-flight {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 250px;

    height: 56px;

    padding:
        0 30px;

    border-radius:
        13px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    color: white;

    text-decoration: none;

    font-size: 15px;

    font-weight: 900;

    transition:
        .2s ease;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.20);
}

.select-flight:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.30);
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    background:
        white;

    border:
        1px solid #e1e7f0;

    border-radius:
        22px;

    padding:
        70px 30px;

    text-align: center;
}

.empty-icon {

    font-size: 55px;

    margin-bottom: 15px;
}

.empty h2 {

    margin: 0;

    font-size: 23px;
}

.empty p {

    color:
        #667085;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:800px) {

    .container {

        width:
            calc(100% - 24px);
    }

    .search-summary {

        flex-direction:
            column;

        align-items:
            flex-start;
    }

    .search-info {

        justify-content:
            flex-start;
    }

    .results-header {

        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 15px;
    }

    .flight-route {

        grid-template-columns:
            100px
            1fr
            100px;

        gap: 10px;
    }

    .time {

        font-size: 23px;
    }

    .flight-bottom {

        flex-direction:
            column;

        align-items:
            stretch;
    }

    .select-flight {

        width: 100%;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="navbar">

    <div class="logo">

        <div class="logo-icon">
            ✈
        </div>

        Vektor<span>Sky</span>

    </div>

    <div class="menu">
        ☰
    </div>

</header>


<main class="container">


<!-- =========================================================
     SEARCH SUMMARY
========================================================= -->

<section class="search-summary">

    <div class="route">

        <div class="airport">

            <?= h(
                strtoupper(
                    $from ?: 'FROM'
                )
            ) ?>

            <small>
                Departure
            </small>

        </div>


        <div class="route-arrow">
            →
        </div>


        <div class="airport">

            <?= h(
                strtoupper(
                    $to ?: 'TO'
                )
            ) ?>

            <small>
                Arrival
            </small>

        </div>

    </div>


    <div class="search-info">

        <?php if ($date): ?>

            <div class="info-pill">
                📅
                <?= h(
                    date(
                        'Y-m-d',
                        strtotime($date)
                    )
                ) ?>
            </div>

        <?php endif; ?>


        <div class="info-pill">
            👤
            <?= h($travellers) ?>
            <?= $travellers === 1
                ? 'Traveller'
                : 'Travellers'
            ?>
        </div>


        <div class="info-pill">
            💺
            <?= h($cabin) ?>
        </div>

    </div>

</section>


<!-- =========================================================
     RESULTS HEADER
========================================================= -->

<div class="results-header">

    <div class="results-title">

        <h1>
            Available Flights
        </h1>

        <p>

            <?= count($flights) ?>

            flight<?= count($flights) === 1
                ? ''
                : 's'
            ?>

            found for your search

        </p>

    </div>


    <div class="sort-box">

        <span>
            Sort:
        </span>

        <select id="sortFlights">

            <option value="departure">
                Departure
            </option>

            <option value="price">
                Lowest Price
            </option>

            <option value="duration">
                Shortest Duration
            </option>

        </select>

    </div>

</div>


<!-- =========================================================
     ERROR
========================================================= -->

<?php if ($error): ?>

    <div class="error">

        <?= h($error) ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     FLIGHTS
========================================================= -->

<div id="flightList">


<?php if (!empty($flights)): ?>


    <?php foreach ($flights as $flight): ?>


        <article
            class="flight-card"
            data-price="<?= h($flight['fare']) ?>"
            data-departure="<?= h($flight['departure_time']) ?>"
            data-duration="<?= h(
                strtotime(
                    $flight['arrival_time']
                )
                -
                strtotime(
                    $flight['departure_time']
                )
            ) ?>"
        >


            <!-- =================================================
                 TOP
            ================================================== -->

            <div class="flight-top">

                <div class="airline-info">

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


                <div class="flight-number">

                    <?= h(
                        $flight['aircraft_model']
                    ) ?>

                </div>

            </div>


            <!-- =================================================
                 ROUTE
            ================================================== -->

            <div class="flight-route">


                <div class="time-block">

                    <div class="time">

                        <?= h(
                            format_time(
                                $flight['departure_time']
                            )
                        ) ?>

                    </div>

                    <div class="location">

                        <?= h(
                            $flight['source_code']
                        ) ?>

                        ·

                        <?= h(
                            $flight['source_city']
                        ) ?>

                    </div>

                </div>


                <div class="route-line">

                    <div class="duration">

                        <?= h(
                            flight_duration(
                                $flight['departure_time'],
                                $flight['arrival_time']
                            )
                        ) ?>

                    </div>

                    <div class="line"></div>

                    <div class="plane">
                        ✈
                    </div>

                </div>


                <div class="time-block right">

                    <div class="time">

                        <?= h(
                            format_time(
                                $flight['arrival_time']
                            )
                        ) ?>

                    </div>

                    <div class="location">

                        <?= h(
                            $flight['destination_code']
                        ) ?>

                        ·

                        <?= h(
                            $flight['destination_city']
                        ) ?>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 TAGS
            ================================================== -->

            <div class="tags">

                <div class="tag">

                    📅

                    <?= h(
                        format_date(
                            $flight['departure_time']
                        )
                    ) ?>

                </div>


                <div class="tag">

                    ✈

                    <?= h(
                        $flight['aircraft_model']
                    ) ?>

                </div>


                <div class="tag">

                    💺

                    <?= h($cabin) ?>

                </div>


                <div class="tag">

                    Flight
                    <?= h(
                        $flight['flight_number']
                    ) ?>

                </div>

            </div>


            <!-- =================================================
                 BOTTOM
            ================================================== -->

            <div class="flight-bottom">


                <div>

                    <div class="fare-label">
                        Starting from
                    </div>

                    <div class="fare">

                        ₹<?= number_format(
                            (float)$flight['fare'],
                            0
                        ) ?>

                        <small>
                            / traveller
                        </small>

                    </div>


                    <div class="availability">

                        <?php if (
                            (int)$flight['available_seats']
                            > 0
                        ): ?>

                            ✓

                            <?= h(
                                $flight['available_seats']
                            ) ?>

                            seats available

                        <?php else: ?>

                            ✕ No seats available

                        <?php endif; ?>

                    </div>

                </div>


                <!-- =================================================
                     IMPORTANT:
                     Go to flight_details.php
                     NOT book.php
                ================================================== -->

                <?php if (
                    (int)$flight['available_seats']
                    >= $travellers
                ): ?>

                    <a
                        class="select-flight"
                        href="flight_details.php?id=<?= (int)$flight['flight_id'] ?>"
                    >

                        Select Flight →

                    </a>

                <?php else: ?>

                    <span
                        class="select-flight"
                        style="
                            background:#98a2b3;
                            box-shadow:none;
                            cursor:not-allowed;
                            pointer-events:none;
                        "
                    >

                        Sold Out

                    </span>

                <?php endif; ?>


            </div>


        </article>


    <?php endforeach; ?>


<?php else: ?>


    <!-- =========================================================
         NO FLIGHTS
    ========================================================== -->

    <div class="empty">

        <div class="empty-icon">
            ✈️
        </div>

        <h2>
            No flights found
        </h2>

        <p>
            Try another date, airport or route.
        </p>

        <a
            href="index.php"
            class="select-flight"
            style="
                display:inline-flex;
                margin-top:20px;
                min-width:220px;
            "
        >

            Search Again

        </a>

    </div>


<?php endif; ?>


</div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| Sort flights
|--------------------------------------------------------------------------
*/

const sortSelect =
    document.getElementById(
        'sortFlights'
    );

const flightList =
    document.getElementById(
        'flightList'
    );


sortSelect.addEventListener(
    'change',
    function () {

        const cards =
            Array.from(
                flightList.querySelectorAll(
                    '.flight-card'
                )
            );


        if (this.value === 'price') {

            cards.sort(
                (a, b) => {

                    return (
                        parseFloat(
                            a.dataset.price
                        )
                        -
                        parseFloat(
                            b.dataset.price
                        )
                    );

                }
            );

        }


        else if (
            this.value === 'duration'
        ) {

            cards.sort(
                (a, b) => {

                    return (
                        parseInt(
                            a.dataset.duration
                        )
                        -
                        parseInt(
                            b.dataset.duration
                        )
                    );

                }
            );

        }


        else {

            cards.sort(
                (a, b) => {

                    return (
                        new Date(
                            a.dataset.departure
                        )
                        -
                        new Date(
                            b.dataset.departure
                        )
                    );

                }
            );

        }


        cards.forEach(
            card => flightList.appendChild(card)
        );

    }
);

</script>


</body>

</html>