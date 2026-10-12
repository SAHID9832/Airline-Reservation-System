<?php

/*
|--------------------------------------------------------------------------
| VektorSky - Flight Calendar API
|--------------------------------------------------------------------------
|
| Returns all available flight dates and lowest fares for:
|
| Any city/airport -> Any city/airport
|
| Supports:
| - Airport code
| - Airport ID
| - City name
| - Airport name
|
| Example:
|
| api/calendar.php
| ?source=Bengaluru
| &destination=Mumbai
| &month=2026-08
|
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';


/*
|--------------------------------------------------------------------------
| JSON helper
|--------------------------------------------------------------------------
*/

function jsonResponse(
    array $data,
    int $status = 200
): void {

    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Resolve airport
|--------------------------------------------------------------------------
*/

function resolveAirport(
    PDO $pdo,
    string $value
): ?array {

    $value =
        trim($value);


    if ($value === '') {

        return null;

    }


    /*
    |--------------------------------------------------------------------------
    | 1. Airport ID
    |--------------------------------------------------------------------------
    */

    if (
        ctype_digit($value)
    ) {

        $stmt =
            $pdo->prepare("
                SELECT
                    airport_id,
                    airport_code,
                    airport_name,
                    city,
                    country
                FROM airport
                WHERE airport_id = ?
                LIMIT 1
            ");

        $stmt->execute([
            (int)$value
        ]);

        $airport =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($airport) {

            return $airport;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | 2. Exact airport code
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("
            SELECT
                airport_id,
                airport_code,
                airport_name,
                city,
                country
            FROM airport
            WHERE UPPER(airport_code) = UPPER(?)
            LIMIT 1
        ");

    $stmt->execute([
        $value
    ]);

    $airport =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($airport) {

        return $airport;

    }


    /*
    |--------------------------------------------------------------------------
    | 3. Exact city
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("
            SELECT
                airport_id,
                airport_code,
                airport_name,
                city,
                country
            FROM airport
            WHERE LOWER(city) = LOWER(?)
            ORDER BY airport_id
            LIMIT 1
        ");

    $stmt->execute([
        $value
    ]);

    $airport =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($airport) {

        return $airport;

    }


    /*
    |--------------------------------------------------------------------------
    | 4. "Bengaluru (BLR)"
    |--------------------------------------------------------------------------
    */

    if (
        preg_match(
            '/\(([A-Za-z]{3})\)/',
            $value,
            $matches
        )
    ) {

        $code =
            strtoupper(
                $matches[1]
            );


        $stmt =
            $pdo->prepare("
                SELECT
                    airport_id,
                    airport_code,
                    airport_name,
                    city,
                    country
                FROM airport
                WHERE UPPER(airport_code) = ?
                LIMIT 1
            ");

        $stmt->execute([
            $code
        ]);

        $airport =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($airport) {

            return $airport;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | 5. Partial city / airport name
    |--------------------------------------------------------------------------
    */

    $like =
        '%' .
        $value .
        '%';


    $stmt =
        $pdo->prepare("
            SELECT
                airport_id,
                airport_code,
                airport_name,
                city,
                country
            FROM airport

            WHERE
                city LIKE ?
                OR airport_name LIKE ?
                OR airport_code LIKE ?

            ORDER BY

                CASE

                    WHEN LOWER(city) = LOWER(?)
                    THEN 0

                    WHEN LOWER(city) LIKE LOWER(?)
                    THEN 1

                    ELSE 2

                END,

                airport_name

            LIMIT 1
        ");

    $stmt->execute([
        $like,
        $like,
        $like,
        $value,
        $value . '%'
    ]);


    $airport =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    return $airport ?: null;
}


/*
|--------------------------------------------------------------------------
| Read request
|--------------------------------------------------------------------------
*/

$source =
    trim(
        $_GET['source']
        ??
        $_GET['from']
        ??
        ''
    );


$destination =
    trim(
        $_GET['destination']
        ??
        $_GET['to']
        ??
        ''
    );


$month =
    trim(
        $_GET['month']
        ??
        date('Y-m')
    );


/*
|--------------------------------------------------------------------------
| Validate month
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^\d{4}-\d{2}$/',
        $month
    )
) {

    jsonResponse([
        'success' => false,
        'message' => 'Invalid month.'
    ], 400);

}


/*
|--------------------------------------------------------------------------
| Validate airports
|--------------------------------------------------------------------------
*/

if (
    $source === '' ||
    $destination === ''
) {

    jsonResponse([
        'success' => false,
        'message' =>
            'Source and destination are required.',
        'dates' => []
    ], 400);

}


try {


    /*
    |--------------------------------------------------------------------------
    | Resolve source
    |--------------------------------------------------------------------------
    */

    $sourceAirport =
        resolveAirport(
            $pdo,
            $source
        );


    if (!$sourceAirport) {

        jsonResponse([
            'success' => false,
            'message' =>
                'Source airport/city not found.',
            'dates' => []
        ], 404);

    }


    /*
    |--------------------------------------------------------------------------
    | Resolve destination
    |--------------------------------------------------------------------------
    */

    $destinationAirport =
        resolveAirport(
            $pdo,
            $destination
        );


    if (!$destinationAirport) {

        jsonResponse([
            'success' => false,
            'message' =>
                'Destination airport/city not found.',
            'dates' => []
        ], 404);

    }


    /*
    |--------------------------------------------------------------------------
    | Same airport
    |--------------------------------------------------------------------------
    */

    if (
        (int)$sourceAirport['airport_id']
        ===
        (int)$destinationAirport['airport_id']
    ) {

        jsonResponse([
            'success' => false,
            'message' =>
                'Source and destination cannot be the same.',
            'dates' => []
        ], 400);

    }


    /*
    |--------------------------------------------------------------------------
    | Month boundaries
    |--------------------------------------------------------------------------
    */

    $monthStart =
        $month . '-01';


    $nextMonth =
        date(
            'Y-m-d',
            strtotime(
                $monthStart .
                ' +1 month'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Get ALL flights for route
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We do NOT filter by flight_stop.
    |
    | A flight with:
    |
    | 0 stops
    | 1 stop
    | 2 stops
    | 3 stops
    |
    | is still represented by the same flight record.
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
                :source_airport_id

            AND

            f.destination_airport_id =
                :destination_airport_id

            AND

            f.departure_time >=
                :month_start

            AND

            f.departure_time <
                :next_month

        GROUP BY

            DATE(
                f.departure_time
            )

        ORDER BY

            flight_date ASC

    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute([

        ':source_airport_id' =>
            $sourceAirport['airport_id'],

        ':destination_airport_id' =>
            $destinationAirport['airport_id'],

        ':month_start' =>
            $monthStart,

        ':next_month' =>
            $nextMonth

    ]);


    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Build calendar dates
    |--------------------------------------------------------------------------
    */

    $dates = [];


    foreach (
        $rows
        as $row
    ) {

        $dates[] = [

            'date' =>
                $row['flight_date'],

            'lowest_fare' =>
                (float)$row['lowest_fare'],

            'flight_count' =>
                (int)$row['flight_count']

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    jsonResponse([

        'success' => true,

        'source' => [

            'airport_id' =>
                (int)$sourceAirport['airport_id'],

            'airport_code' =>
                $sourceAirport['airport_code'],

            'airport_name' =>
                $sourceAirport['airport_name'],

            'city' =>
                $sourceAirport['city'],

            'country' =>
                $sourceAirport['country']

        ],

        'destination' => [

            'airport_id' =>
                (int)$destinationAirport['airport_id'],

            'airport_code' =>
                $destinationAirport['airport_code'],

            'airport_name' =>
                $destinationAirport['airport_name'],

            'city' =>
                $destinationAirport['city'],

            'country' =>
                $destinationAirport['country']

        ],

        'month' =>
            $month,

        'dates' =>
            $dates

    ]);


} catch (
    Throwable $e
) {


    /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

    jsonResponse([

        'success' => false,

        'message' =>
            'Calendar database error.',

        'error' =>
            $e->getMessage(),

        'dates' => []

    ], 500);

}