<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

echo "<pre>";

/*
|--------------------------------------------------------------------------
| VektorSky Flight Generator
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| This script does NOT delete:
|
| - existing flights
| - existing bookings
| - existing flight seats
|
| It adds additional flight services and stop information.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

$daysToGenerate = 90;

/*
 * Number of new flight services to create per day.
 *
 * We intentionally keep this reasonable because the airport
 * table contains 9,052 airports.
 */
$flightsPerDay = 24;


/*
|--------------------------------------------------------------------------
| START DATE
|--------------------------------------------------------------------------
*/

$startDate = new DateTime();

$startDate->setTime(
    0,
    0,
    0
);


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Check required tables
    |--------------------------------------------------------------------------
    */

    $requiredTables = [

        'airport',
        'aircraft',
        'seat',
        'flight',
        'flight_seat',
        'flight_stop'

    ];


    foreach ($requiredTables as $table) {

        $check =
            $pdo->query(
                "SHOW TABLES LIKE " .
                $pdo->quote($table)
            );

        if (!$check->fetchColumn()) {

            throw new Exception(
                "Required table does not exist: " .
                $table
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Load airports
    |--------------------------------------------------------------------------
    |
    | We use the complete airport dataset.
    |
    | We exclude airports without a usable airport code.
    |
    */

    $airportStmt = $pdo->query("

        SELECT

            airport_id,
            airport_code,
            airport_name,
            city,
            country,
            latitude,
            longitude

        FROM airport

        WHERE

            airport_code IS NOT NULL

            AND airport_code <> ''

        ORDER BY airport_id

    ");


    $airports =
        $airportStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (
        count($airports) < 20
    ) {

        throw new Exception(
            'Not enough airports found.'
        );

    }


    echo "Airports loaded: "
        . count($airports)
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | Load aircraft
    |--------------------------------------------------------------------------
    */

    $aircraftStmt =
        $pdo->query("

            SELECT

                aircraft_id,
                aircraft_no,
                model,
                total_seats

            FROM aircraft

            ORDER BY aircraft_id

        ");


    $aircraft =
        $aircraftStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (!$aircraft) {

        throw new Exception(
            'No aircraft found.'
        );

    }


    echo "Aircraft loaded: "
        . count($aircraft)
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | Get existing maximum flight number
    |--------------------------------------------------------------------------
    */

    $maxNumberStmt =
        $pdo->query("

            SELECT

                MAX(
                    CAST(
                        SUBSTRING(
                            flight_number,
                            3
                        )
                        AS UNSIGNED
                    )
                )

            FROM flight

            WHERE
                flight_number REGEXP '^VS[0-9]+$'

        ");


    $maxNumber =
        (int)
        $maxNumberStmt->fetchColumn();


    if ($maxNumber < 100) {

        $maxNumber = 100;

    }


    /*
    |--------------------------------------------------------------------------
    | Existing route templates
    |--------------------------------------------------------------------------
    |
    | We use your existing routes as the initial network.
    |
    */

    $existingRouteStmt =
        $pdo->query("

            SELECT DISTINCT

                source_airport_id,
                destination_airport_id

            FROM flight

            WHERE
                source_airport_id <>
                destination_airport_id

        ");


    $existingRoutes =
        $existingRouteStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | If existing routes are too few,
    | create additional routes from the airport pool.
    |--------------------------------------------------------------------------
    */

    $routePairs = [];


    foreach ($existingRoutes as $route) {

        $key =
            $route['source_airport_id']
            . '-'
            . $route['destination_airport_id'];


        $routePairs[$key] = [

            'source' =>
                (int)
                $route['source_airport_id'],

            'destination' =>
                (int)
                $route['destination_airport_id']

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | Airport IDs
    |--------------------------------------------------------------------------
    */

    $airportIds = [];


    foreach ($airports as $airport) {

        $airportIds[] =
            (int)
            $airport['airport_id'];

    }


    /*
    |--------------------------------------------------------------------------
    | Add more random routes
    |--------------------------------------------------------------------------
    |
    | We create a controlled network rather than every possible
    | airport-to-airport combination.
    |
    */

    $targetRoutes = 120;


    $attempts = 0;


    while (
        count($routePairs) < $targetRoutes
        &&
        $attempts < 5000
    ) {

        $attempts++;


        $source =
            $airportIds[
                random_int(
                    0,
                    count($airportIds) - 1
                )
            ];


        $destination =
            $airportIds[
                random_int(
                    0,
                    count($airportIds) - 1
                )
            ];


        if (
            $source ===
            $destination
        ) {

            continue;

        }


        $key =
            $source
            . '-'
            . $destination;


        if (
            isset(
                $routePairs[$key]
            )
        ) {

            continue;

        }


        $routePairs[$key] = [

            'source' =>
                $source,

            'destination' =>
                $destination

        ];

    }


    $routes =
        array_values(
            $routePairs
        );


    echo "Routes available: "
        . count($routes)
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | Airport lookup
    |--------------------------------------------------------------------------
    */

    $airportById = [];


    foreach ($airports as $airport) {

        $airportById[
            (int)
            $airport['airport_id']
        ] =
            $airport;

    }


    /*
    |--------------------------------------------------------------------------
    | Prepared statements
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

                :flight_number,
                :airline_name,

                :source_airport_id,
                :destination_airport_id,

                :aircraft_id,

                :departure_time,
                :arrival_time,

                :fare

            )

        ");


    $insertFlightSeat =
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

                :flight_id,
                s.seat_id,
                'Available',
                NULL,
                NULL

            FROM seat s

            WHERE
                s.aircraft_id = :aircraft_id

        ");


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

                :flight_id,
                :stop_number,
                :airport_id,
                :arrival_time,
                :departure_time

            )

        ");


    /*
    |--------------------------------------------------------------------------
    | Existing flight number protection
    |--------------------------------------------------------------------------
    */

    $existingNumbersStmt =
        $pdo->query("

            SELECT flight_number

            FROM flight

        ");


    $existingNumbers = [];


    while (
        $row =
            $existingNumbersStmt->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $existingNumbers[
            strtoupper(
                trim(
                    $row['flight_number']
                )
            )
        ] = true;

    }


    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    $createdFlights = 0;

    $createdSeats = 0;

    $createdStops = 0;

    $nonStopFlights = 0;

    $oneStopFlights = 0;

    $twoStopFlights = 0;

    $threeStopFlights = 0;


    /*
    |--------------------------------------------------------------------------
    | Generate daily schedules
    |--------------------------------------------------------------------------
    */

    for (
        $day = 0;
        $day < $daysToGenerate;
        $day++
    ) {


        $currentDate =
            clone $startDate;


        $currentDate->modify(
            '+' . $day . ' days'
        );


        /*
        | Select a deterministic group of routes for this day.
        |
        | This means different days will have different route
        | combinations.
        */

        $routeStart =
            (
                $day * $flightsPerDay
            )
            %
            count($routes);


        for (
            $service = 0;
            $service < $flightsPerDay;
            $service++
        ) {


            $routeIndex =
                (
                    $routeStart
                    +
                    $service
                )
                %
                count($routes);


            $route =
                $routes[
                    $routeIndex
                ];


            $sourceId =
                (int)
                $route['source'];


            $destinationId =
                (int)
                $route['destination'];


            if (
                !isset(
                    $airportById[
                        $sourceId
                    ]
                )

                ||

                !isset(
                    $airportById[
                        $destinationId
                    ]
                )
            ) {

                continue;

            }


            $sourceAirport =
                $airportById[
                    $sourceId
                ];


            $destinationAirport =
                $airportById[
                    $destinationId
                ];


            /*
            |--------------------------------------------------------------------------
            | Choose aircraft
            |--------------------------------------------------------------------------
            */

            $aircraftRow =
                $aircraft[
                    (
                        $day
                        +
                        $service
                    )
                    %
                    count($aircraft)
                ];


            $aircraftId =
                (int)
                $aircraftRow[
                    'aircraft_id'
                ];


            /*
            |--------------------------------------------------------------------------
            | Departure time
            |--------------------------------------------------------------------------
            |
            | Different flights get different times.
            |
            */

            $hourOptions = [

                5,
                6,
                7,
                8,
                9,
                10,
                11,
                12,
                13,
                14,
                15,
                16,
                17,
                18,
                19,
                20,
                21,
                22

            ];


            $hour =
                $hourOptions[
                    (
                        $service * 3
                        +
                        $day
                    )
                    %
                    count($hourOptions)
                ];


            $minuteOptions = [

                0,
                10,
                15,
                20,
                25,
                30,
                35,
                40,
                45,
                50

            ];


            $minute =
                $minuteOptions[
                    (
                        $service
                        +
                        $day
                    )
                    %
                    count($minuteOptions)
                ];


            $departure =
                clone $currentDate;


            $departure->setTime(
                $hour,
                $minute,
                0
            );


            /*
            |--------------------------------------------------------------------------
            | Determine stops
            |--------------------------------------------------------------------------
            |
            | 0 stops = majority
            | 1 stop  = some
            | 2 stops = few
            | 3 stops = very few
            |
            */

            $stopRoll =
                (
                    $day
                    +
                    $service * 7
                )
                %
                100;


            if ($stopRoll < 70) {

                $stopCount = 0;

            } elseif ($stopRoll < 93) {

                $stopCount = 1;

            } elseif ($stopRoll < 99) {

                $stopCount = 2;

            } else {

                $stopCount = 3;

            }


            /*
            |--------------------------------------------------------------------------
            | Select stop airports
            |--------------------------------------------------------------------------
            */

            $stopAirports = [];


            if ($stopCount > 0) {


                $candidateAttempts = 0;


                while (
                    count($stopAirports)
                    <
                    $stopCount

                    &&

                    $candidateAttempts
                    <
                    500
                ) {

                    $candidateAttempts++;


                    $candidateId =
                        $airportIds[
                            random_int(
                                0,
                                count($airportIds) - 1
                            )
                        ];


                    if (
                        $candidateId ===
                        $sourceId
                    ) {

                        continue;

                    }


                    if (
                        $candidateId ===
                        $destinationId
                    ) {

                        continue;

                    }


                    if (
                        in_array(
                            $candidateId,
                            $stopAirports,
                            true
                        )
                    ) {

                        continue;

                    }


                    $stopAirports[] =
                        $candidateId;

                }


                /*
                | If we couldn't find enough airports,
                | convert this to a non-stop flight.
                */

                if (
                    count($stopAirports)
                    !==
                    $stopCount
                ) {

                    $stopCount = 0;

                    $stopAirports = [];

                }

            }


            /*
            |--------------------------------------------------------------------------
            | Flight duration
            |--------------------------------------------------------------------------
            |
            | Base duration is different for every service.
            |
            */

            $baseDurationMinutes =

                75
                +
                (
                    (
                        $service * 17
                        +
                        $day * 5
                    )
                    %
                    180
                );


            /*
            |--------------------------------------------------------------------------
            | Add extra time for stops
            |--------------------------------------------------------------------------
            */

            $durationMinutes =

                $baseDurationMinutes
                +
                (
                    $stopCount * 70
                );


            /*
            | Extra variation
            */

            $durationMinutes +=

                (
                    $service % 5
                )
                * 8;


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
            | Fare
            |--------------------------------------------------------------------------
            */

            $baseFare =

                2800

                +

                (
                    $baseDurationMinutes
                    * 9
                )

                +

                (
                    $stopCount
                    * -250
                );


            /*
            | Different fare for each service.
            */

            $fareVariation =

                (
                    (
                        $day * 37
                        +
                        $service * 113
                    )
                    %
                    1400
                )
                - 400;


            $fare =
                $baseFare
                +
                $fareVariation;


            /*
            | Keep fares in a sensible range.
            */

            if (
                $fare < 2499
            ) {

                $fare = 2499;

            }


            if (
                $fare > 14999
            ) {

                $fare = 14999;

            }


            $fare =
                round(
                    $fare / 50
                )
                * 50;


            /*
            |--------------------------------------------------------------------------
            | Flight number
            |--------------------------------------------------------------------------
            */

            do {

                $maxNumber++;


                $flightNumber =
                    'VS'
                    .
                    $maxNumber;

            } while (
                isset(
                    $existingNumbers[
                        $flightNumber
                    ]
                )
            );


            $existingNumbers[
                $flightNumber
            ] = true;


            /*
            |--------------------------------------------------------------------------
            | Insert flight
            |--------------------------------------------------------------------------
            */

            $insertFlight->execute([

                ':flight_number' =>
                    $flightNumber,

                ':airline_name' =>
                    'Vector Sky',

                ':source_airport_id' =>
                    $sourceId,

                ':destination_airport_id' =>
                    $destinationId,

                ':aircraft_id' =>
                    $aircraftId,

                ':departure_time' =>
                    $departure->format(
                        'Y-m-d H:i:s'
                    ),

                ':arrival_time' =>
                    $arrival->format(
                        'Y-m-d H:i:s'
                    ),

                ':fare' =>
                    number_format(
                        $fare,
                        2,
                        '.',
                        ''
                    )

            ]);


            $flightId =
                (int)
                $pdo->lastInsertId();


            if (
                $flightId <= 0
            ) {

                continue;

            }


            $createdFlights++;


            /*
            |--------------------------------------------------------------------------
            | Add seats
            |--------------------------------------------------------------------------
            */

            $insertFlightSeat->execute([

                ':flight_id' =>
                    $flightId,

                ':aircraft_id' =>
                    $aircraftId

            ]);


            $createdSeats +=
                $insertFlightSeat->rowCount();


            /*
            |--------------------------------------------------------------------------
            | Create stops
            |--------------------------------------------------------------------------
            */

            if (
                $stopCount === 0
            ) {

                $nonStopFlights++;

            }


            elseif (
                $stopCount === 1
            ) {

                $oneStopFlights++;

            }


            elseif (
                $stopCount === 2
            ) {

                $twoStopFlights++;

            }


            else {

                $threeStopFlights++;

            }


            /*
            |--------------------------------------------------------------------------
            | Calculate stop timing
            |--------------------------------------------------------------------------
            */

            if (
                $stopCount > 0
            ) {


                $segmentCount =
                    $stopCount + 1;


                /*
                | Divide total journey time into segments.
                */

                $segmentMinutes =

                    intdiv(
                        $durationMinutes,
                        $segmentCount
                    );


                $previousTime =
                    clone $departure;


                foreach (
                    $stopAirports
                    as $index =>
                    $stopAirportId
                ) {


                    /*
                    | Travel to stop.
                    */

                    $stopArrival =
                        clone $previousTime;


                    $stopArrival->modify(

                        '+' .

                        $segmentMinutes .

                        ' minutes'

                    );


                    /*
                    | Layover:
                    | 35–55 minutes.
                    */

                    $layoverMinutes =

                        35
                        +
                        (
                            (
                                $day
                                +
                                $service
                                +
                                $index
                            )
                            %
                            21
                        );


                    $stopDeparture =
                        clone $stopArrival;


                    $stopDeparture->modify(

                        '+' .

                        $layoverMinutes .

                        ' minutes'

                    );


                    $insertStop->execute([

                        ':flight_id' =>
                            $flightId,

                        ':stop_number' =>
                            $index + 1,

                        ':airport_id' =>
                            $stopAirportId,

                        ':arrival_time' =>
                            $stopArrival->format(
                                'Y-m-d H:i:s'
                            ),

                        ':departure_time' =>
                            $stopDeparture->format(
                                'Y-m-d H:i:s'
                            )

                    ]);


                    $createdStops++;


                    $previousTime =
                        $stopDeparture;

                }

            }


        }


        /*
        |--------------------------------------------------------------------------
        | Progress
        |--------------------------------------------------------------------------
        */

        if (
            $day % 10 === 0
        ) {

            echo

                "Day "
                . ($day + 1)
                . "/"
                . $daysToGenerate
                . " completed"
                . PHP_EOL;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Final output
    |--------------------------------------------------------------------------
    */

    echo PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo "GENERATION COMPLETE"
        . PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo

        "New flights: "
        . $createdFlights
        . PHP_EOL;

    echo

        "New flight seats: "
        . $createdSeats
        . PHP_EOL;

    echo

        "New stop records: "
        . $createdStops
        . PHP_EOL;

    echo

        "Non-stop flights: "
        . $nonStopFlights
        . PHP_EOL;

    echo

        "1-stop flights: "
        . $oneStopFlights
        . PHP_EOL;

    echo

        "2-stop flights: "
        . $twoStopFlights
        . PHP_EOL;

    echo

        "3-stop flights: "
        . $threeStopFlights
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | Database totals
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


    echo PHP_EOL;

    echo "Database totals:"
        . PHP_EOL;

    echo

        "Flights: "
        . $totalFlights
        . PHP_EOL;

    echo

        "Flight seats: "
        . $totalSeats
        . PHP_EOL;

    echo

        "Flight stops: "
        . $totalStops
        . PHP_EOL;


} catch (
    Throwable $e
) {

    echo PHP_EOL;

    echo "ERROR:"
        . PHP_EOL;

    echo $e->getMessage()
        . PHP_EOL;

    echo PHP_EOL;

    echo $e->getTraceAsString()
        . PHP_EOL;

}


echo "</pre>";