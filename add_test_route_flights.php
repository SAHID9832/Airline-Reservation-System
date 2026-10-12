<?php

require_once __DIR__ . '/config.php';

set_time_limit(0);

echo "<pre>";

echo "========================================\n";
echo "ADDING BLR -> BOM FLIGHTS\n";
echo "========================================\n\n";


/*
|--------------------------------------------------------------------------
| ROUTE
|--------------------------------------------------------------------------
*/

$sourceAirportId = 2;   // BLR
$destinationAirportId = 3; // BOM


/*
|--------------------------------------------------------------------------
| GENERATE NEXT 45 DAYS
|--------------------------------------------------------------------------
*/

$startDate = new DateTime('2026-08-27');

$days = 45;


/*
|--------------------------------------------------------------------------
| AIRCRAFT
|--------------------------------------------------------------------------
*/

$aircraftStmt = $pdo->query("
    SELECT
        aircraft_id
    FROM aircraft
    ORDER BY aircraft_id
");

$aircraftIds =
    $aircraftStmt->fetchAll(
        PDO::FETCH_COLUMN
    );


if (!$aircraftIds) {

    die(
        "No aircraft found.\n"
    );

}


/*
|--------------------------------------------------------------------------
| FLIGHT NUMBER
|--------------------------------------------------------------------------
*/

$flightNumber = 600000;


/*
|--------------------------------------------------------------------------
| INSERT FLIGHT
|--------------------------------------------------------------------------
*/

$insertFlight = $pdo->prepare("
    INSERT INTO flight
    (
        flight_number,
        airline_name,
        source_airport_id,
        destination_airport_id,
        aircraft_id,
        departure_time,
        arrival_time,
        fare
    )
    VALUES
    (
        ?,
        'VektorSky',
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
");


/*
|--------------------------------------------------------------------------
| CHECK DUPLICATE
|--------------------------------------------------------------------------
*/

$checkFlight = $pdo->prepare("
    SELECT flight_id
    FROM flight
    WHERE
        flight_number = ?
        AND departure_time = ?
    LIMIT 1
");


/*
|--------------------------------------------------------------------------
| ADD SEATS
|--------------------------------------------------------------------------
*/

$insertSeats = $pdo->prepare("
    INSERT IGNORE INTO flight_seat
    (
        flight_id,
        seat_id,
        status,
        booked_by,
        booked_at
    )
    SELECT
        ?,
        seat_id,
        'Available',
        NULL,
        NULL
    FROM seat
    WHERE aircraft_id = ?
");


/*
|--------------------------------------------------------------------------
| ADD STOPS
|--------------------------------------------------------------------------
*/

$insertStop = $pdo->prepare("
    INSERT INTO flight_stop
    (
        flight_id,
        stop_number,
        airport_id,
        arrival_time,
        departure_time
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?
    )
");


/*
|--------------------------------------------------------------------------
| AIRPORTS FOR STOPS
|--------------------------------------------------------------------------
*/

$airportStmt = $pdo->prepare("
    SELECT airport_id
    FROM airport
    WHERE
        airport_id <> ?
        AND airport_id <> ?
    ORDER BY airport_id
");

$airportStmt->execute([
    $sourceAirportId,
    $destinationAirportId
]);

$stopAirports =
    $airportStmt->fetchAll(
        PDO::FETCH_COLUMN
    );


/*
|--------------------------------------------------------------------------
| COUNTERS
|--------------------------------------------------------------------------
*/

$totalFlights = 0;
$totalSeats = 0;
$totalStops = 0;


/*
|--------------------------------------------------------------------------
| GENERATE
|--------------------------------------------------------------------------
*/

for (
    $day = 0;
    $day < $days;
    $day++
) {

    $date =
        clone $startDate;

    $date->modify(
        '+' . $day . ' days'
    );


    /*
    |--------------------------------------------------------------------------
    | Different number of flights per day
    |--------------------------------------------------------------------------
    |
    | 2 or 3 flights every day.
    |
    */

    $flightsToday =
        ($day % 3 === 0)
            ? 3
            : 2;


    for (
        $i = 0;
        $i < $flightsToday;
        $i++
    ) {


        /*
        |--------------------------------------------------------------------------
        | Different departure times
        |--------------------------------------------------------------------------
        */

        $times = [

            ['06:15', 165],
            ['11:30', 170],
            ['18:45', 175]

        ];


        $time =
            $times[$i];


        $departure =
            clone $date;


        [$hour, $minute] =
            explode(
                ':',
                $time[0]
            );


        $departure->setTime(
            (int)$hour,
            (int)$minute,
            0
        );


        /*
        |--------------------------------------------------------------------------
        | Duration
        |--------------------------------------------------------------------------
        */

        $duration =
            $time[1];


        $arrival =
            clone $departure;


        $arrival->modify(
            '+' .
            $duration .
            ' minutes'
        );


        /*
        |--------------------------------------------------------------------------
        | Different fare
        |--------------------------------------------------------------------------
        */

        $baseFares = [

            4250,
            4650,
            3950

        ];


        $fare =
            $baseFares[$i];


        /*
        |--------------------------------------------------------------------------
        | Weekend adjustment
        |--------------------------------------------------------------------------
        */

        $dayOfWeek =
            (int)$departure->format('N');


        if (
            $dayOfWeek >= 6
        ) {

            $fare += 350;

        }


        /*
        |--------------------------------------------------------------------------
        | Small date variation
        |--------------------------------------------------------------------------
        */

        $fare +=
            (($day * 50) % 500);


        /*
        |--------------------------------------------------------------------------
        | Aircraft
        |--------------------------------------------------------------------------
        */

        $aircraftId =
            (int)$aircraftIds[
                (
                    $day + $i
                )
                %
                count($aircraftIds)
            ];


        /*
        |--------------------------------------------------------------------------
        | Unique flight number
        |--------------------------------------------------------------------------
        */

        $number =
            'VS'
            .
            str_pad(
                (string)$flightNumber,
                6,
                '0',
                STR_PAD_LEFT
            );


        $flightNumber++;


        /*
        |--------------------------------------------------------------------------
        | Duplicate check
        |--------------------------------------------------------------------------
        */

        $checkFlight->execute([

            $number,

            $departure->format(
                'Y-m-d H:i:s'
            )

        ]);


        if (
            $checkFlight->fetch()
        ) {

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | Insert flight
        |--------------------------------------------------------------------------
        */

        $insertFlight->execute([

            $number,

            $sourceAirportId,

            $destinationAirportId,

            $aircraftId,

            $departure->format(
                'Y-m-d H:i:s'
            ),

            $arrival->format(
                'Y-m-d H:i:s'
            ),

            $fare

        ]);


        $flightId =
            (int)$pdo->lastInsertId();


        $totalFlights++;


        /*
        |--------------------------------------------------------------------------
        | Seats
        |--------------------------------------------------------------------------
        */

        $insertSeats->execute([

            $flightId,

            $aircraftId

        ]);


        $totalSeats +=
            $insertSeats->rowCount();


        /*
        |--------------------------------------------------------------------------
        | Stops
        |--------------------------------------------------------------------------
        |
        | Mostly nonstop.
        |
        | Every 10th flight -> 1 stop
        | Every 20th flight -> 2 stops
        | Every 30th flight -> 3 stops
        |
        */

        $stopCount = 0;


        if (
            $totalFlights % 30 === 0
        ) {

            $stopCount = 3;

        } elseif (
            $totalFlights % 20 === 0
        ) {

            $stopCount = 2;

        } elseif (
            $totalFlights % 10 === 0
        ) {

            $stopCount = 1;

        }


        if (
            $stopCount > 0
        ) {

            shuffle(
                $stopAirports
            );


            /*
            | Make sure enough airports exist.
            */

            for (
                $s = 1;
                $s <= $stopCount;
                $s++
            ) {

                $stopAirportId =
                    (int)$stopAirports[
                        $s - 1
                    ];


                /*
                | Spread stops through journey.
                */

                $stopArrival =
                    clone $departure;


                $stopArrival->modify(
                    '+' .
                    (
                        60 * $s
                    ) .
                    ' minutes'
                );


                $stopDeparture =
                    clone $stopArrival;


                $stopDeparture->modify(
                    '+45 minutes'
                );


                $insertStop->execute([

                    $flightId,

                    $s,

                    $stopAirportId,

                    $stopArrival->format(
                        'Y-m-d H:i:s'
                    ),

                    $stopDeparture->format(
                        'Y-m-d H:i:s'
                    )

                ]);


                $totalStops++;

            }

        }

    }


    echo
        "Day "
        .
        ($day + 1)
        .
        "/"
        .
        $days
        .
        " completed - "
        .
        $date->format('Y-m-d')
        .
        "\n";

}


echo "\n";
echo "========================================\n";
echo "COMPLETE\n";
echo "========================================\n";

echo
    "New flights: "
    .
    $totalFlights
    .
    "\n";

echo
    "New seats: "
    .
    $totalSeats
    .
    "\n";

echo
    "New stops: "
    .
    $totalStops
    .
    "\n";


echo "</pre>";