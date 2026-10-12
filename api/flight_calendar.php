<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');


try {

    $from = trim(
        $_GET['from'] ?? ''
    );

    $to = trim(
        $_GET['to'] ?? ''
    );

    $start = trim(
        $_GET['start'] ?? date('Y-m-01')
    );

    $end = trim(
        $_GET['end']
        ??
        date(
            'Y-m-d',
            strtotime('+2 months')
        )
    );


    if (
        $from === '' ||
        $to === ''
    ) {

        echo json_encode([

            'success' => false,

            'dates' => [],

            'message' =>
                'From and To are required.'

        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | FIND SOURCE AIRPORT
    |--------------------------------------------------------------------------
    */

    $airportSql = "

        SELECT

            airport_id,
            airport_code,
            airport_name,
            city,
            country

        FROM airport

        WHERE

            airport_code LIKE :code

            OR city LIKE :city

            OR airport_name LIKE :name

            OR keywords LIKE :keywords

            OR icao_code LIKE :icao

        ORDER BY

            CASE

                WHEN UPPER(airport_code)
                    = UPPER(:exact_code)
                    THEN 0

                WHEN UPPER(city)
                    = UPPER(:exact_city)
                    THEN 1

                WHEN UPPER(city)
                    LIKE UPPER(:city_prefix)
                    THEN 2

                WHEN UPPER(airport_name)
                    LIKE UPPER(:name_prefix)
                    THEN 3

                ELSE 4

            END,

            airport_id ASC

        LIMIT 1

    ";


    $stmt =
        $pdo->prepare(
            $airportSql
        );


    $searchFrom =
        '%' . $from . '%';


    $stmt->execute([

        ':code' =>
            $searchFrom,

        ':city' =>
            $searchFrom,

        ':name' =>
            $searchFrom,

        ':keywords' =>
            $searchFrom,

        ':icao' =>
            $searchFrom,

        ':exact_code' =>
            $from,

        ':exact_city' =>
            $from,

        ':city_prefix' =>
            $from . '%',

        ':name_prefix' =>
            $from . '%'

    ]);


    $source =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | FIND DESTINATION AIRPORT
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare(
            $airportSql
        );


    $searchTo =
        '%' . $to . '%';


    $stmt->execute([

        ':code' =>
            $searchTo,

        ':city' =>
            $searchTo,

        ':name' =>
            $searchTo,

        ':keywords' =>
            $searchTo,

        ':icao' =>
            $searchTo,

        ':exact_code' =>
            $to,

        ':exact_city' =>
            $to,

        ':city_prefix' =>
            $to . '%',

        ':name_prefix' =>
            $to . '%'

    ]);


    $destination =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | AIRPORT NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (
        !$source ||
        !$destination
    ) {

        echo json_encode([

            'success' =>
                true,

            'dates' =>
                [],

            'message' =>
                'Airport or city not found.'

        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SAME AIRPORT
    |--------------------------------------------------------------------------
    */

    if (
        (int)$source['airport_id']
        ===
        (int)$destination['airport_id']
    ) {

        echo json_encode([

            'success' =>
                false,

            'dates' =>
                [],

            'message' =>
                'From and To cannot be the same.'

        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | FIND REAL FLIGHTS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Prices come from your actual flight table.
    |
    */

    $sql = "

        SELECT

            DATE(f.departure_time)
                AS flight_date,

            MIN(f.fare)
                AS lowest_fare,

            COUNT(*)
                AS flight_count

        FROM flight f

        WHERE

            f.source_airport_id =
                :source_id

            AND

            f.destination_airport_id =
                :destination_id

            AND

            DATE(f.departure_time)
                BETWEEN :start_date
                AND :end_date

            AND

            f.departure_time >= NOW()

        GROUP BY

            DATE(f.departure_time)

        ORDER BY

            flight_date ASC

    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute([

        ':source_id' =>
            $source['airport_id'],

        ':destination_id' =>
            $destination['airport_id'],

        ':start_date' =>
            $start,

        ':end_date' =>
            $end

    ]);


    $dates = [];


    while (
        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $dates[
            $row['flight_date']
        ] = [

            'price' =>
                (float)$row['lowest_fare'],

            'flight_count' =>
                (int)$row['flight_count']

        ];

    }


    echo json_encode([

        'success' =>
            true,

        'from' => [

            'airport_id' =>
                (int)$source['airport_id'],

            'airport_code' =>
                $source['airport_code'],

            'airport_name' =>
                $source['airport_name'],

            'city' =>
                $source['city']

        ],

        'to' => [

            'airport_id' =>
                (int)$destination['airport_id'],

            'airport_code' =>
                $destination['airport_code'],

            'airport_name' =>
                $destination['airport_name'],

            'city' =>
                $destination['city']

        ],

        'dates' =>
            $dates

    ], JSON_UNESCAPED_UNICODE);


} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        'success' =>
            false,

        'dates' =>
            [],

        'message' =>
            $e->getMessage()

    ]);

}