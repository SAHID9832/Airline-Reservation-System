<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| Vector Sky - Search Results
|--------------------------------------------------------------------------
|
| Supports:
|
| from
| to
| date
| travellers
| cabin
|
| Database:
|
| AIRPORT
| AIRCRAFT
| FLIGHT
| FLIGHT_SEAT
| FLIGHT_STOP
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$from = trim(
    (string)(
        $_GET['from']
        ?? $_POST['from']
        ?? ''
    )
);

$to = trim(
    (string)(
        $_GET['to']
        ?? $_POST['to']
        ?? ''
    )
);

$date = trim(
    (string)(
        $_GET['date']
        ?? $_POST['date']
        ?? ''
    )
);

$travellers = (int)(
    $_GET['travellers']
    ?? $_POST['travellers']
    ?? 1
);

$cabin = trim(
    (string)(
        $_GET['cabin']
        ?? $_POST['cabin']
        ?? 'Economy'
    )
);


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($travellers < 1) {
    $travellers = 1;
}

if ($travellers > 9) {
    $travellers = 9;
}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {

    function h($value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/*
|--------------------------------------------------------------------------
| Time helper
|--------------------------------------------------------------------------
*/

function format_time_value(
    string $datetime
): string {

    return date(
        'H:i',
        strtotime($datetime)
    );

}


/*
|--------------------------------------------------------------------------
| Date helper
|--------------------------------------------------------------------------
*/

function format_date_value(
    string $datetime
): string {

    return date(
        'D, d M Y',
        strtotime($datetime)
    );

}


/*
|--------------------------------------------------------------------------
| Duration helper
|--------------------------------------------------------------------------
*/

function duration_minutes(
    string $departure,
    string $arrival
): int {

    $start =
        strtotime($departure);

    $end =
        strtotime($arrival);

    if (
        !$start ||
        !$end ||
        $end <= $start
    ) {

        return 0;

    }

    return (int)(
        ($end - $start)
        / 60
    );

}


/*
|--------------------------------------------------------------------------
| Duration display
|--------------------------------------------------------------------------
*/

function duration_text(
    string $departure,
    string $arrival
): string {

    $minutes =
        duration_minutes(
            $departure,
            $arrival
        );

    if ($minutes <= 0) {
        return '-';
    }

    $hours =
        intdiv(
            $minutes,
            60
        );

    $mins =
        $minutes % 60;

    if ($hours > 0 && $mins > 0) {

        return $hours
            . 'h '
            . $mins
            . 'm';

    }

    if ($hours > 0) {

        return $hours
            . 'h';

    }

    return $mins . 'm';

}


/*
|--------------------------------------------------------------------------
| PAGE VARIABLES
|--------------------------------------------------------------------------
*/

$flights = [];

$error = '';

$searchPerformed =
    (
        $from !== ''
        ||
        $to !== ''
        ||
        $date !== ''
    );


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

try {

    $conditions = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | FROM AIRPORT
    |--------------------------------------------------------------------------
    */

    if ($from !== '') {

        $conditions[] = "

            (

                UPPER(src.airport_code)
                    LIKE :from_code

                OR

                UPPER(src.city)
                    LIKE :from_city

                OR

                UPPER(src.airport_name)
                    LIKE :from_name

                OR

                UPPER(COALESCE(src.keywords, ''))
                    LIKE :from_keywords

                OR

                UPPER(COALESCE(src.icao_code, ''))
                    LIKE :from_icao

            )

        ";

        $fromSearch =
            '%' .
            strtoupper($from) .
            '%';


        $params[':from_code'] =
            $fromSearch;

        $params[':from_city'] =
            $fromSearch;

        $params[':from_name'] =
            $fromSearch;

        $params[':from_keywords'] =
            $fromSearch;

        $params[':from_icao'] =
            $fromSearch;

    }


    /*
    |--------------------------------------------------------------------------
    | TO AIRPORT
    |--------------------------------------------------------------------------
    */

    if ($to !== '') {

        $conditions[] = "

            (

                UPPER(dst.airport_code)
                    LIKE :to_code

                OR

                UPPER(dst.city)
                    LIKE :to_city

                OR

                UPPER(dst.airport_name)
                    LIKE :to_name

                OR

                UPPER(COALESCE(dst.keywords, ''))
                    LIKE :to_keywords

                OR

                UPPER(COALESCE(dst.icao_code, ''))
                    LIKE :to_icao

            )

        ";

        $toSearch =
            '%' .
            strtoupper($to) .
            '%';


        $params[':to_code'] =
            $toSearch;

        $params[':to_city'] =
            $toSearch;

        $params[':to_name'] =
            $toSearch;

        $params[':to_keywords'] =
            $toSearch;

        $params[':to_icao'] =
            $toSearch;

    }


    /*
    |--------------------------------------------------------------------------
    | DATE
    |--------------------------------------------------------------------------
    */

    if ($date !== '') {

        $conditions[] = "

            DATE(f.departure_time)
            = :flight_date

        ";

        $params[':flight_date'] =
            $date;

    }


    /*
    |--------------------------------------------------------------------------
    | ONLY FUTURE FLIGHTS
    |--------------------------------------------------------------------------
    |
    | If a specific date is selected, keep flights on that date.
    |
    */

    if (
        $date === ''
        ||
        $date === date('Y-m-d')
    ) {

        $conditions[] = "

            f.departure_time >= NOW()

        ";

    }


    /*
    |--------------------------------------------------------------------------
    | WHERE
    |--------------------------------------------------------------------------
    */

    $where = '';

    if (!empty($conditions)) {

        $where =
            'WHERE '
            .
            implode(
                ' AND ',
                $conditions
            );

    }


    /*
    |--------------------------------------------------------------------------
    | FLIGHT QUERY
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT

            f.flight_id,
            f.flight_number,
            f.airline_name,

            f.source_airport_id,
            f.destination_airport_id,

            f.aircraft_id,

            f.departure_time,
            f.arrival_time,

            f.fare,

            src.airport_code
                AS source_code,

            src.airport_name
                AS source_airport_name,

            src.city
                AS source_city,

            src.country
                AS source_country,

            dst.airport_code
                AS destination_code,

            dst.airport_name
                AS destination_airport_name,

            dst.city
                AS destination_city,

            dst.country
                AS destination_country,

            ac.aircraft_no,

            ac.model
                AS aircraft_model,

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

        INNER JOIN airport src

            ON
                f.source_airport_id
                =
                src.airport_id

        INNER JOIN airport dst

            ON
                f.destination_airport_id
                =
                dst.airport_id

        INNER JOIN aircraft ac

            ON
                f.aircraft_id
                =
                ac.aircraft_id

        LEFT JOIN flight_seat fs

            ON
                fs.flight_id
                =
                f.flight_id

        $where

        GROUP BY

            f.flight_id,
            f.flight_number,
            f.airline_name,

            f.source_airport_id,
            f.destination_airport_id,

            f.aircraft_id,

            f.departure_time,
            f.arrival_time,

            f.fare,

            src.airport_code,
            src.airport_name,
            src.city,
            src.country,

            dst.airport_code,
            dst.airport_name,
            dst.city,
            dst.country,

            ac.aircraft_no,
            ac.model,
            ac.total_seats

        HAVING

            available_seats >= :travellers

        ORDER BY

            f.departure_time ASC

    ";


    $params[':travellers'] =
        $travellers;


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
    | LOAD STOPS
    |--------------------------------------------------------------------------
    */

    if (!empty($flights)) {

        $flightIds =
            array_map(
                'intval',
                array_column(
                    $flights,
                    'flight_id'
                )
            );


        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($flightIds),
                    '?'
                )
            );


        $stopSql = "

            SELECT

                fs.flight_id,

                fs.stop_number,

                fs.arrival_time,

                fs.departure_time,

                a.airport_id,

                a.airport_code,

                a.airport_name,

                a.city,

                a.country

            FROM flight_stop fs

            INNER JOIN airport a

                ON
                    a.airport_id
                    =
                    fs.airport_id

            WHERE

                fs.flight_id IN
                ($placeholders)

            ORDER BY

                fs.flight_id ASC,
                fs.stop_number ASC

        ";


        $stopStmt =
            $pdo->prepare(
                $stopSql
            );


        $stopStmt->execute(
            $flightIds
        );


        $stopRows =
            $stopStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | Group stops
        |--------------------------------------------------------------------------
        */

        $stopsByFlight = [];


        foreach (
            $stopRows
            as $stop
        ) {

            $flightId =
                (int)
                $stop['flight_id'];


            if (
                !isset(
                    $stopsByFlight[
                        $flightId
                    ]
                )
            ) {

                $stopsByFlight[
                    $flightId
                ] = [];

            }


            $stopsByFlight[
                $flightId
            ][] =
                $stop;

        }


        /*
        |--------------------------------------------------------------------------
        | Attach stops
        |--------------------------------------------------------------------------
        */

        foreach (
            $flights
            as &$flight
        ) {

            $flightId =
                (int)
                $flight['flight_id'];


            $flight['stops'] =
                $stopsByFlight[
                    $flightId
                ]
                ?? [];


            $flight['stop_count'] =
                count(
                    $flight['stops']
                );

        }

        unset($flight);

    }


} catch (
    Throwable $e
) {

    $error =
        'Unable to search flights.';

    error_log(
        'SEARCH RESULTS ERROR: '
        .
        $e->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Search Flights';


?>
<!DOCTYPE html>

<html
    lang="en"
>

<head>

    <meta
        charset="UTF-8"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= h($pageTitle) ?>
        - Vector Sky
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f5f7fb;

            color:
                #172033;

        }


        a {
            text-decoration: none;
        }


        .navbar {

            height: 70px;

            background:
                #ffffff;

            border-bottom:
                1px solid #e5e7eb;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 6%;

        }


        .logo {

            font-size:
                23px;

            font-weight:
                800;

            color:
                #111827;

        }


        .logo span {
            color:
                #2563eb;
        }


        .back-link {

            color:
                #475569;

            font-size:
                14px;

            font-weight:
                600;

        }


        .page {

            max-width:
                1250px;

            margin:
                0 auto;

            padding:
                32px 20px 70px;

        }


        .page-header {

            display:
                flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                22px;

        }


        .page-header h1 {

            margin:
                0 0 6px;

            font-size:
                28px;

        }


        .page-header p {

            margin:
                0;

            color:
                #64748b;

        }


        .search-summary {

            background:
                #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius:
                14px;

            padding:
                18px;

            margin-bottom:
                22px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            flex-wrap:
                wrap;

        }


        .route-summary {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            font-weight:
                700;

        }


        .route-summary .code {

            background:
                #eff6ff;

            color:
                #1d4ed8;

            padding:
                7px 10px;

            border-radius:
                8px;

        }


        .search-meta {

            color:
                #64748b;

            font-size:
                13px;

        }


        .toolbar {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                15px;

            margin-bottom:
                18px;

        }


        .result-count {

            color:
                #475569;

            font-size:
                14px;

            font-weight:
                600;

        }


        .sort-box {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

        }


        .sort-box label {

            font-size:
                13px;

            color:
                #64748b;

        }


        .sort-box select {

            border:
                1px solid #d1d5db;

            border-radius:
                8px;

            padding:
                8px 10px;

            background:
                #ffffff;

        }


        .error {

            background:
                #fef2f2;

            border:
                1px solid #fecaca;

            color:
                #b91c1c;

            padding:
                14px;

            border-radius:
                10px;

            margin-bottom:
                20px;

        }


        .empty {

            background:
                #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius:
                14px;

            padding:
                45px 25px;

            text-align:
                center;

        }


        .empty h2 {

            margin:
                0 0 8px;

        }


        .empty p {

            margin:
                0;

            color:
                #64748b;

        }


        .flight-list {

            display:
                flex;

            flex-direction:
                column;

            gap:
                16px;

        }


        .flight-card {

            background:
                #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius:
                16px;

            padding:
                20px;

            box-shadow:
                0 4px 16px
                rgba(15, 23, 42, 0.05);

        }


        .flight-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding-bottom:
                16px;

            border-bottom:
                1px solid #eef2f7;

        }


        .airline-info {

            display:
                flex;

            align-items:
                center;

            gap:
                11px;

        }


        .airline-logo {

            width:
                42px;

            height:
                42px;

            border-radius:
                11px;

            background:
                #eff6ff;

            color:
                #2563eb;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-weight:
                800;

        }


        .airline-name {

            font-size:
                15px;

            font-weight:
                800;

        }


        .flight-number {

            font-size:
                12px;

            color:
                #64748b;

            margin-top:
                3px;

        }


        .aircraft {

            font-size:
                12px;

            color:
                #64748b;

        }


        .flight-main {

            display:
                grid;

            grid-template-columns:
                minmax(100px, 1fr)
                minmax(230px, 2fr)
                minmax(100px, 1fr)
                minmax(150px, auto);

            align-items:
                center;

            gap:
                20px;

            padding:
                22px 0;

        }


        .time-block {

            min-width:
                90px;

        }


        .time-block.right {

            text-align:
                right;

        }


        .time {

            font-size:
                25px;

            font-weight:
                800;

            color:
                #111827;

        }


        .airport-code {

            font-size:
                14px;

            font-weight:
                800;

            margin-top:
                5px;

        }


        .airport-city {

            font-size:
                11px;

            color:
                #64748b;

            margin-top:
                3px;

        }


        .route-center {

            text-align:
                center;

        }


        .duration {

            font-size:
                12px;

            color:
                #64748b;

            margin-bottom:
                8px;

        }


        .route-line {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

        }


        .route-line::before {

            content:
                '';

            flex:
                1;

            height:
                2px;

            background:
                #dbe2ea;

        }


        .route-line::after {

            content:
                '';

            flex:
                1;

            height:
                2px;

            background:
                #dbe2ea;

        }


        .plane {

            font-size:
                18px;

            color:
                #2563eb;

        }


        .stop-label {

            margin-top:
                8px;

            font-size:
                12px;

            font-weight:
                700;

        }


        .stop-label.non-stop {

            color:
                #15803d;

        }


        .stop-label.has-stops {

            color:
                #b45309;

        }


        .fare-box {

            text-align:
                right;

        }


        .fare {

            font-size:
                24px;

            font-weight:
                900;

            color:
                #111827;

        }


        .fare-small {

            display:
                block;

            font-size:
                11px;

            font-weight:
                500;

            color:
                #64748b;

            margin-top:
                3px;

        }


        .select-button {

            display:
                inline-block;

            margin-top:
                10px;

            background:
                #2563eb;

            color:
                #ffffff;

            padding:
                10px 18px;

            border-radius:
                9px;

            font-size:
                13px;

            font-weight:
                700;

            transition:
                0.15s;

        }


        .select-button:hover {

            background:
                #1d4ed8;

        }


        .sold-out {

            display:
                inline-block;

            margin-top:
                10px;

            padding:
                10px 18px;

            border-radius:
                9px;

            background:
                #e5e7eb;

            color:
                #64748b;

            font-size:
                13px;

            font-weight:
                700;

        }


        .flight-info-row {

            display:
                flex;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                8px;

            padding:
                13px 0;

            border-top:
                1px solid #eef2f7;

        }


        .tag {

            display:
                inline-flex;

            align-items:
                center;

            padding:
                6px 9px;

            border-radius:
                7px;

            background:
                #f8fafc;

            border:
                1px solid #e2e8f0;

            color:
                #475569;

            font-size:
                11px;

        }


        .tag.non-stop {

            color:
                #166534;

            background:
                #f0fdf4;

            border-color:
                #bbf7d0;

        }


        .tag.stop {

            color:
                #92400e;

            background:
                #fffbeb;

            border-color:
                #fde68a;

        }


        .seats {

            margin-left:
                auto;

            font-size:
                12px;

            color:
                #64748b;

        }


        /*
        |--------------------------------------------------------------------------
        | STOPS
        |--------------------------------------------------------------------------
        */

        .flight-stops {

            margin-top:
                2px;

            padding:
                14px;

            background:
                #f8fafc;

            border:
                1px solid #e2e8f0;

            border-radius:
                11px;

        }


        .stops-heading {

            font-size:
                12px;

            font-weight:
                800;

            color:
                #475569;

            margin-bottom:
                10px;

        }


        .stop-list {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                9px;

        }


        .stop-item {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            background:
                #ffffff;

            border:
                1px solid #e2e8f0;

            border-radius:
                9px;

            padding:
                9px 11px;

        }


        .stop-number {

            width:
                22px;

            height:
                22px;

            border-radius:
                50%;

            background:
                #2563eb;

            color:
                #ffffff;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                10px;

            font-weight:
                800;

        }


        .stop-airport {

            display:
                flex;

            flex-direction:
                column;

        }


        .stop-airport-code {

            font-size:
                12px;

            font-weight:
                800;

        }


        .stop-airport-city {

            font-size:
                10px;

            color:
                #64748b;

        }


        .stop-times {

            font-size:
                10px;

            color:
                #64748b;

            margin-left:
                5px;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 900px
        ) {

            .flight-main {

                grid-template-columns:
                    1fr
                    1fr
                    1fr;

            }


            .route-center {

                grid-column:
                    1 / -1;

                grid-row:
                    2;

            }


            .fare-box {

                grid-column:
                    1 / -1;

                text-align:
                    left;

            }


            .time-block.right {

                text-align:
                    right;

            }

        }


        @media (
            max-width: 600px
        ) {

            .page {

                padding:
                    20px 12px 50px;

            }


            .navbar {

                padding:
                    0 15px;

            }


            .page-header {

                display:
                    block;

            }


            .toolbar {

                display:
                    block;

            }


            .sort-box {

                margin-top:
                    10px;

            }


            .flight-main {

                grid-template-columns:
                    1fr 1fr;

                gap:
                    14px;

            }


            .route-center {

                grid-column:
                    1 / -1;

            }


            .fare-box {

                grid-column:
                    1 / -1;

            }


            .flight-top {

                align-items:
                    flex-start;

            }


            .aircraft {

                text-align:
                    right;

            }


            .seats {

                margin-left:
                    0;

            }

        }

    </style>

</head>


<body>


<header class="navbar">

    <a
        href="index.php"
        class="logo"
    >
        Vector<span>Sky</span>
    </a>


    <a
        href="index.php"
        class="back-link"
    >
        &larr; Back to Search
    </a>

</header>


<main class="page">


    <div class="page-header">

        <div>

            <h1>
                Search Results
            </h1>

            <p>
                Compare available flights and choose the best option.
            </p>

        </div>

    </div>


    <?php if ($error): ?>

        <div class="error">

            <?= h($error) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $from !== ''
        ||
        $to !== ''
        ||
        $date !== ''
    ): ?>

        <div class="search-summary">

            <div class="route-summary">

                <?php if ($from !== ''): ?>

                    <span class="code">
                        <?= h($from) ?>
                    </span>

                <?php endif; ?>


                <span>
                    &rarr;
                </span>


                <?php if ($to !== ''): ?>

                    <span class="code">
                        <?= h($to) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="search-meta">

                <?php if ($date !== ''): ?>

                    <?= h(
                        date(
                            'D, d M Y',
                            strtotime($date)
                        )
                    ) ?>

                    &nbsp; | &nbsp;

                <?php endif; ?>


                <?= h($travellers) ?>

                <?= $travellers === 1
                    ? 'Passenger'
                    : 'Passengers'
                ?>

                &nbsp; | &nbsp;

                <?= h($cabin) ?>

            </div>

        </div>

    <?php endif; ?>


    <div class="toolbar">

        <div class="result-count">

            <?php if (!empty($flights)): ?>

                <?= count($flights) ?>

                flight(s) found

            <?php elseif ($searchPerformed): ?>

                No flights found

            <?php else: ?>

                Available flights

            <?php endif; ?>

        </div>


        <?php if (
            count($flights) > 1
        ): ?>

            <div class="sort-box">

                <label
                    for="sortFlights"
                >
                    Sort:
                </label>


                <select
                    id="sortFlights"
                >

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

        <?php endif; ?>

    </div>


    <div
        class="flight-list"
        id="flightList"
    >


        <?php if (
            !empty($flights)
        ): ?>


            <?php foreach (
                $flights
                as $flight
            ): ?>


                <?php

                $stopCount =
                    (int)(
                        $flight['stop_count']
                        ?? 0
                    );


                $departure =
                    $flight[
                        'departure_time'
                    ];


                $arrival =
                    $flight[
                        'arrival_time'
                    ];


                $duration =
                    duration_minutes(
                        $departure,
                        $arrival
                    );


                $availableSeats =
                    (int)(
                        $flight[
                            'available_seats'
                        ]
                        ?? 0
                    );


                $totalSeats =
                    (int)(
                        $flight[
                            'total_seats'
                        ]
                        ?? 0
                    );

                ?>


                <article
                    class="flight-card"
                    data-price="<?= h($flight['fare']) ?>"
                    data-departure="<?= h($departure) ?>"
                    data-duration="<?= h($duration) ?>"
                >


                    <!-- TOP -->

                    <div class="flight-top">


                        <div
                            class="airline-info"
                        >

                            <div
                                class="airline-logo"
                            >
                                VS
                            </div>


                            <div>

                                <div
                                    class="airline-name"
                                >

                                    <?= h(
                                        $flight[
                                            'airline_name'
                                        ]
                                    ) ?>

                                </div>


                                <div
                                    class="flight-number"
                                >

                                    Flight
                                    <?= h(
                                        $flight[
                                            'flight_number'
                                        ]
                                    ) ?>

                                </div>

                            </div>

                        </div>


                        <div
                            class="aircraft"
                        >

                            <?= h(
                                $flight[
                                    'aircraft_model'
                                ]
                            ) ?>

                            <?php if (
                                !empty(
                                    $flight[
                                        'aircraft_no'
                                    ]
                                )
                            ): ?>

                                &nbsp;·&nbsp;

                                <?= h(
                                    $flight[
                                        'aircraft_no'
                                    ]
                                ) ?>

                            <?php endif; ?>

                        </div>


                    </div>


                    <!-- MAIN ROUTE -->

                    <div class="flight-main">


                        <!-- DEPARTURE -->

                        <div class="time-block">

                            <div class="time">

                                <?= h(
                                    format_time_value(
                                        $departure
                                    )
                                ) ?>

                            </div>


                            <div
                                class="airport-code"
                            >

                                <?= h(
                                    $flight[
                                        'source_code'
                                    ]
                                ) ?>

                            </div>


                            <div
                                class="airport-city"
                            >

                                <?= h(
                                    $flight[
                                        'source_city'
                                    ]
                                ) ?>

                            </div>

                        </div>


                        <!-- ROUTE -->

                        <div
                            class="route-center"
                        >

                            <div
                                class="duration"
                            >

                                <?= h(
                                    duration_text(
                                        $departure,
                                        $arrival
                                    )
                                ) ?>

                            </div>


                            <div
                                class="route-line"
                            >

                                <span
                                    class="plane"
                                >
                                    &#9992;
                                </span>

                            </div>


                            <?php if (
                                $stopCount === 0
                            ): ?>

                                <div
                                    class="stop-label non-stop"
                                >
                                    Non-stop
                                </div>

                            <?php elseif (
                                $stopCount === 1
                            ): ?>

                                <div
                                    class="stop-label has-stops"
                                >
                                    1 Stop
                                </div>

                            <?php else: ?>

                                <div
                                    class="stop-label has-stops"
                                >

                                    <?= h(
                                        $stopCount
                                    ) ?>

                                    Stops

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- ARRIVAL -->

                        <div
                            class="time-block right"
                        >

                            <div class="time">

                                <?= h(
                                    format_time_value(
                                        $arrival
                                    )
                                ) ?>

                            </div>


                            <div
                                class="airport-code"
                            >

                                <?= h(
                                    $flight[
                                        'destination_code'
                                    ]
                                ) ?>

                            </div>


                            <div
                                class="airport-city"
                            >

                                <?= h(
                                    $flight[
                                        'destination_city'
                                    ]
                                ) ?>

                            </div>

                        </div>


                        <!-- FARE -->

                        <div class="fare-box">

                            <div class="fare">

                                &#8377;

                                <?= number_format(
                                    (float)
                                    $flight['fare'],
                                    2
                                ) ?>

                            </div>


                            <span
                                class="fare-small"
                            >
                                per person
                            </span>


                            <?php if (
                                $availableSeats >=
                                $travellers
                            ): ?>

                                <a
                                    class="select-button"
                                    href="flight_details.php?id=<?= urlencode(
                                        (string)
                                        $flight[
                                            'flight_id'
                                        ]
                                    ) ?>"
                                >
                                    Select Flight
                                </a>

                            <?php else: ?>

                                <span
                                    class="sold-out"
                                >
                                    Sold Out
                                </span>

                            <?php endif; ?>

                        </div>


                    </div>


                    <!-- INFORMATION -->

                    <div
                        class="flight-info-row"
                    >


                        <div class="tag">

                            <?= h(
                                format_date_value(
                                    $departure
                                )
                            ) ?>

                        </div>


                        <div class="tag">

                            <?= h(
                                $flight[
                                    'aircraft_model'
                                ]
                            ) ?>

                        </div>


                        <div class="tag">

                            <?= h(
                                $cabin
                            ) ?>

                        </div>


                        <?php if (
                            $stopCount === 0
                        ): ?>

                            <div
                                class="tag non-stop"
                            >
                                Non-stop
                            </div>

                        <?php else: ?>

                            <div
                                class="tag stop"
                            >

                                <?= h(
                                    $stopCount
                                ) ?>

                                <?= $stopCount === 1
                                    ? 'Stop'
                                    : 'Stops'
                                ?>

                            </div>

                        <?php endif; ?>


                        <div class="seats">

                            <?= h(
                                $availableSeats
                            ) ?>

                            seats available

                        </div>


                    </div>


                    <!-- STOP DETAILS -->

                    <?php if (
                        $stopCount > 0
                    ): ?>


                        <div
                            class="flight-stops"
                        >


                            <div
                                class="stops-heading"
                            >

                                Connecting
                                Airports

                            </div>


                            <div
                                class="stop-list"
                            >


                                <?php foreach (
                                    $flight[
                                        'stops'
                                    ]
                                    as $stop
                                ): ?>


                                    <div
                                        class="stop-item"
                                    >


                                        <div
                                            class="stop-number"
                                        >

                                            <?= h(
                                                $stop[
                                                    'stop_number'
                                                ]
                                            ) ?>

                                        </div>


                                        <div
                                            class="stop-airport"
                                        >

                                            <span
                                                class="stop-airport-code"
                                            >

                                                <?= h(
                                                    $stop[
                                                        'airport_code'
                                                    ]
                                                ) ?>

                                            </span>


                                            <span
                                                class="stop-airport-city"
                                            >

                                                <?= h(
                                                    $stop[
                                                        'city'
                                                    ]
                                                ) ?>

                                            </span>

                                        </div>


                                        <div
                                            class="stop-times"
                                        >

                                            Arrive

                                            <?= h(
                                                format_time_value(
                                                    $stop[
                                                        'arrival_time'
                                                    ]
                                                )
                                            ) ?>

                                            <br>

                                            Depart

                                            <?= h(
                                                format_time_value(
                                                    $stop[
                                                        'departure_time'
                                                    ]
                                                )
                                            ) ?>

                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            </div>


                        </div>


                    <?php endif; ?>


                </article>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty">

                <h2>
                    No flights found
                </h2>


                <p>

                    Try another airport,
                    date, or passenger count.

                </p>

            </div>


        <?php endif; ?>


    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| SORT FLIGHTS
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


if (
    sortSelect &&
    flightList
) {

    sortSelect.addEventListener(
        'change',
        function () {

            const cards =
                Array.from(
                    flightList.querySelectorAll(
                        '.flight-card'
                    )
                );


            const mode =
                this.value;


            cards.sort(
                function (
                    a,
                    b
                ) {

                    if (
                        mode ===
                        'price'
                    ) {

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


                    if (
                        mode ===
                        'duration'
                    ) {

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


            cards.forEach(
                function (card) {

                    flightList.appendChild(
                        card
                    );

                }
            );

        }
    );

}

</script>


</body>

</html>