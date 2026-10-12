<?php

require_once __DIR__ . '/config.php';

set_time_limit(0);

echo "<pre>";

echo "========================================\n";
echo "VEKTORSKY ROUTE SCHEDULE GENERATOR\n";
echo "========================================\n\n";


/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

$daysToGenerate = 45;

/*
| Number of flights per existing route per day.
|
| 1 = safest for database size.
|
| We'll generate one guaranteed flight per route/day.
|
*/

$flightsPerRoutePerDay = 1;


/*
|--------------------------------------------------------------------------
| START DATE
|--------------------------------------------------------------------------
*/

$startDate = new DateTime('today');

$endDate = clone $startDate;

$endDate->modify(
    '+' . $daysToGenerate . ' days'
);


echo "Start date: "
    . $startDate->format('Y-m-d')
    . "\n";

echo "End date:   "
    . $endDate->format('Y-m-d')
    . "\n\n";


/*
|--------------------------------------------------------------------------
| LOAD AIRCRAFT
|--------------------------------------------------------------------------
*/

$aircraftStmt =
    $pdo->query("
        SELECT
            aircraft_id,
            total_seats
        FROM aircraft
        ORDER BY aircraft_id
    ");

$aircraft =
    $aircraftStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


if (!$aircraft) {

    die(
        "ERROR: No aircraft found.\n"
    );

}


echo "Aircraft loaded: "
    . count($aircraft)
    . "\n";


/*
|--------------------------------------------------------------------------
| LOAD EXISTING ROUTES
|--------------------------------------------------------------------------
*/

$routeStmt =
    $pdo->query("
        SELECT DISTINCT

            source_airport_id,

            destination_airport_id

        FROM flight

        WHERE
            source_airport_id
            <>
            destination_airport_id

        ORDER BY
            source_airport_id,
            destination_airport_id
    ");

$routes =
    $routeStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


if (!$routes) {

    die(
        "ERROR: No routes found.\n"
    );

}


echo "Routes loaded: "
    . count($routes)
    . "\n\n";


/*
|--------------------------------------------------------------------------
| PREPARE FLIGHT INSERT
|--------------------------------------------------------------------------
*/

$insertFlight =
    $pdo->prepare("
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
            ?,
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
| CHECK EXISTING FLIGHT
|--------------------------------------------------------------------------
*/

$checkFlight =
    $pdo->prepare("
        SELECT flight_id
        FROM flight
        WHERE
            flight_number = ?
            AND departure_time = ?
        LIMIT 1
    ");


/*
|--------------------------------------------------------------------------
| FLIGHT STOP INSERT
|--------------------------------------------------------------------------
*/

$insertStop =
    $pdo->prepare("
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
| SEAT INSERT
|--------------------------------------------------------------------------
*/

$insertSeats =
    $pdo->prepare("
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
| AIRPORTS FOR STOP SELECTION
|--------------------------------------------------------------------------
*/

$airportStmt =
    $pdo->query("
        SELECT
            airport_id
        FROM airport
        ORDER BY airport_id
    ");

$airportIds =
    $airportStmt->fetchAll(
        PDO::FETCH_COLUMN
    );


echo "Airports loaded: "
    . count($airportIds)
    . "\n\n";


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function randomAircraft(
    array $aircraft
): array {

    return $aircraft[
        array_rand($aircraft)
    ];

}


function createFlightNumber(
    int $counter
): string {

    return 'VS'
        .
        str_pad(
            (string)$counter,
            5,
            '0',
            STR_PAD_LEFT
        );

}


function calculateFare(
    int $flightIndex
): float {

    /*
    |--------------------------------------------------------------------------
    | Base fare
    |--------------------------------------------------------------------------
    */

    $base =
        2500
        +
        (($flightIndex * 137) % 5000);


    /*
    |--------------------------------------------------------------------------
    | Round to nearest 50
    |--------------------------------------------------------------------------
    */

    $base =
        round(
            $base / 50
        ) * 50;


    return (float)$base;

}


/*
|--------------------------------------------------------------------------
| COUNTERS
|--------------------------------------------------------------------------
*/

$flightCounter =
    200000;


$newFlights = 0;
$newSeats = 0;
$newStops = 0;


/*
|--------------------------------------------------------------------------
| BEGIN
|--------------------------------------------------------------------------
*/

foreach (
    $routes as $routeIndex => $route
) {

    $sourceId =
        (int)$route[
            'source_airport_id'
        ];

    $destinationId =
        (int)$route[
            'destination_airport_id'
        ];


    /*
    |--------------------------------------------------------------------------
    | Generate each date
    |--------------------------------------------------------------------------
    */

    $date =
        clone $startDate;


    while (
        $date < $endDate
    ) {


        for (
            $flightNumberIndex = 0;
            $flightNumberIndex < $flightsPerRoutePerDay;
            $flightNumberIndex++
        ) {


            /*
            |--------------------------------------------------------------------------
            | Different time every day
            |--------------------------------------------------------------------------
            */

            $hour =
                5
                +
                (
                    (
                        $routeIndex * 7
                        +
                        $date->format('z') * 3
                    )
                    % 19
                );


            $minuteOptions = [

                0,
                10,
                15,
                20,
                30,
                40,
                45,
                50

            ];


            $minute =
                $minuteOptions[
                    (
                        $routeIndex
                        +
                        $date->format('z')
                    )
                    %
                    count(
                        $minuteOptions
                    )
                ];


            /*
            |--------------------------------------------------------------------------
            | Departure
            |--------------------------------------------------------------------------
            */

            $departure =
                clone $date;


            $departure->setTime(
                $hour,
                $minute,
                0
            );


            /*
            |--------------------------------------------------------------------------
            | Duration
            |--------------------------------------------------------------------------
            */

            $durationMinutes =
                80
                +
                (
                    (
                        $routeIndex * 37
                        +
                        $date->format('z') * 11
                    )
                    % 360
                );


            /*
            |--------------------------------------------------------------------------
            | Arrival
            |--------------------------------------------------------------------------
            */

            $arrival =
                clone $departure;


            $arrival->modify(
                '+' .
                $durationMinutes .
                ' minutes'
            );


            /*
            |--------------------------------------------------------------------------
            | Flight number
            |--------------------------------------------------------------------------
            */

            $flightNumber =
                createFlightNumber(
                    $flightCounter
                );


            $flightCounter++;


            /*
            |--------------------------------------------------------------------------
            | Check duplicate
            |--------------------------------------------------------------------------
            */

            $checkFlight->execute([

                $flightNumber,

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
            | Fare
            |--------------------------------------------------------------------------
            */

            $fare =
                calculateFare(
                    $routeIndex
                    +
                    (int)$date->format('z')
            );


            /*
            |--------------------------------------------------------------------------
            | Aircraft
            |--------------------------------------------------------------------------
            */

            $plane =
                randomAircraft(
                    $aircraft
                );


            $aircraftId =
                (int)$plane[
                    'aircraft_id'
                ];


            /*
            |--------------------------------------------------------------------------
            | Insert flight
            |--------------------------------------------------------------------------
            */

            $insertFlight->execute([

                $flightNumber,

                'VektorSky',

                $sourceId,

                $destinationId,

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


            $newFlights++;


            /*
            |--------------------------------------------------------------------------
            | CREATE SEATS
            |--------------------------------------------------------------------------
            */

            $insertSeats->execute([

                $flightId,

                $aircraftId

            ]);


            $seatCount =
                $insertSeats->rowCount();


            $newSeats +=
                $seatCount;


            /*
            |--------------------------------------------------------------------------
            | STOPS
            |--------------------------------------------------------------------------
            |
            | Distribution:
            |
            | 85% nonstop
            | 10% 1 stop
            | 4% 2 stops
            | 1% 3 stops
            |
            */

            $random =
                random_int(
                    1,
                    100
                );


            if (
                $random <= 85
            ) {

                $stopCount = 0;

            } elseif (
                $random <= 95
            ) {

                $stopCount = 1;

            } elseif (
                $random <= 99
            ) {

                $stopCount = 2;

            } else {

                $stopCount = 3;

            }


            /*
            |--------------------------------------------------------------------------
            | Create stops
            |--------------------------------------------------------------------------
            */

            if (
                $stopCount > 0
            ) {


                $possibleStops =
                    array_values(
                        array_filter(
                            $airportIds,
                            function ($airportId)
                            use (
                                $sourceId,
                                $destinationId
                            ) {

                                return
                                    (int)$airportId
                                    !==
                                    $sourceId

                                    &&

                                    (int)$airportId
                                    !==
                                    $destinationId;

                            }
                        )
                    );


                shuffle(
                    $possibleStops
                );


                $stopDuration =
                    max(
                        45,
                        intdiv(
                            $durationMinutes,
                            $stopCount + 1
                        )
                    );


                for (
                    $s = 1;
                    $s <= $stopCount;
                    $s++
                ) {


                    $stopAirportId =
                        (int)$possibleStops[
                            $s - 1
                        ];


                    $stopArrival =
                        clone $departure;


                    $stopArrival->modify(
                        '+' .
                        (
                            $stopDuration
                            *
                            $s
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


                    $newStops++;

                }

            }

        }


        $date->modify(
            '+1 day'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Progress
    |--------------------------------------------------------------------------
    */

    if (
        ($routeIndex + 1) % 500 === 0
    ) {

        echo
            "Routes processed: "
            .
            ($routeIndex + 1)
            .
            " / "
            .
            count($routes)
            .
            "\n";

    }

}


/*
|--------------------------------------------------------------------------
| FINAL COUNTS
|--------------------------------------------------------------------------
*/

$totalFlights =
    $pdo->query(
        "SELECT COUNT(*) FROM flight"
    )->fetchColumn();


$totalSeats =
    $pdo->query(
        "SELECT COUNT(*) FROM flight_seat"
    )->fetchColumn();


$totalStops =
    $pdo->query(
        "SELECT COUNT(*) FROM flight_stop"
    )->fetchColumn();


echo "\n";
echo "========================================\n";
echo "GENERATION COMPLETE\n";
echo "========================================\n";

echo
    "New flights: "
    .
    $newFlights
    .
    "\n";

echo
    "New seats: "
    .
    $newSeats
    .
    "\n";

echo
    "New stops: "
    .
    $newStops
    .
    "\n\n";

echo
    "Database totals:\n";

echo
    "Flights: "
    .
    $totalFlights
    .
    "\n";

echo
    "Flight seats: "
    .
    $totalSeats
    .
    "\n";

echo
    "Flight stops: "
    .
    $totalStops
    .
    "\n";

echo "</pre>";