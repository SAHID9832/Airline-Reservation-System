<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

echo "<pre>";

try {

    /*
    |--------------------------------------------------------------------------
    | SETTINGS
    |--------------------------------------------------------------------------
    */

    /*
     * IMPORTANT:
     *
     * Your database already contains 167,756 flights.
     *
     * Keep this relatively small when adding more data.
     *
     * You can increase it later after testing.
     */

    $daysToGenerate = 30;

    /*
     * Number of destinations generated for each airport per day.
     *
     * DO NOT make this very large.
     */

    $routesPerAirport = 2;


    /*
    |--------------------------------------------------------------------------
    | START
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
    | LOAD AIRPORTS
    |--------------------------------------------------------------------------
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
            airport_id IS NOT NULL
            AND airport_code IS NOT NULL
            AND city IS NOT NULL
        ORDER BY airport_id
    ");

    $airports =
        $airportStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (
        count($airports) < 2
    ) {

        throw new RuntimeException(
            'At least two airports are required.'
        );

    }


    echo "Airports loaded: "
        . count($airports)
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | LOAD AIRCRAFT
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


    if (
        empty($aircraftIds)
    ) {

        throw new RuntimeException(
            'No aircraft found.'
        );

    }


    echo "Aircraft loaded: "
        . count($aircraftIds)
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | DISTANCE FUNCTION
    |--------------------------------------------------------------------------
    */

    function calculateDistanceKm(
        ?float $lat1,
        ?float $lon1,
        ?float $lat2,
        ?float $lon2
    ): float {

        if (
            $lat1 === null ||
            $lon1 === null ||
            $lat2 === null ||
            $lon2 === null
        ) {

            return 1000;

        }


        $earthRadius = 6371;


        $lat1 =
            deg2rad($lat1);

        $lon1 =
            deg2rad($lon1);

        $lat2 =
            deg2rad($lat2);

        $lon2 =
            deg2rad($lon2);


        $dLat =
            $lat2 - $lat1;

        $dLon =
            $lon2 - $lon1;


        $a =
            sin($dLat / 2) ** 2
            +
            cos($lat1)
            *
            cos($lat2)
            *
            sin($dLon / 2) ** 2;


        $c =
            2 *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );


        return
            $earthRadius * $c;

    }


    /*
    |--------------------------------------------------------------------------
    | FARE FUNCTION
    |--------------------------------------------------------------------------
    */

    function calculateFare(
        float $distance,
        int $stops
    ): float {

        $baseFare = 1800;


        $distanceFare =
            $distance * 2.10;


        $stopFare =
            $stops * 600;


        $variation =
            random_int(
                -250,
                650
            );


        $fare =
            $baseFare
            +
            $distanceFare
            +
            $stopFare
            +
            $variation;


        $fare =
            round(
                $fare / 50
            ) * 50;


        return max(
            1800,
            $fare
        );

    }


    /*
    |--------------------------------------------------------------------------
    | DURATION FUNCTION
    |--------------------------------------------------------------------------
    */

    function calculateDurationMinutes(
        float $distance,
        int $stops
    ): int {

        $minutes =
            ($distance / 750) * 60;


        /*
         * Takeoff / landing / airport handling.
         */

        $minutes += 50;


        /*
         * Layovers.
         */

        $minutes +=
            $stops * 75;


        /*
         * Small variation.
         */

        $minutes +=
            random_int(
                -10,
                20
            );


        return max(
            55,
            (int)round($minutes)
        );

    }


    /*
    |--------------------------------------------------------------------------
    | FIND EXISTING FLIGHT
    |--------------------------------------------------------------------------
    |
    | This checks the actual unique key:
    |
    | flight_number + departure_time
    |
    */

    $existingFlightStmt =
        $pdo->prepare("

            SELECT
                flight_id

            FROM flight

            WHERE
                flight_number = ?

                AND departure_time = ?

            LIMIT 1

        ");


    /*
    |--------------------------------------------------------------------------
    | INSERT FLIGHT
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


    /*
    |--------------------------------------------------------------------------
    | INSERT STOP
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
                :flight_id,
                :stop_number,
                :airport_id,
                :arrival_time,
                :departure_time
            )

        ");


    /*
    |--------------------------------------------------------------------------
    | INSERT FLIGHT SEATS
    |--------------------------------------------------------------------------
    */

    $insertFlightSeats =
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
                seat_id,
                'Available',
                NULL,
                NULL

            FROM seat

            WHERE aircraft_id = :aircraft_id

        ");


    /*
    |--------------------------------------------------------------------------
    | FIND MISSING SEATS FOR EXISTING FLIGHTS
    |--------------------------------------------------------------------------
    |
    | This repairs flights created during the previous run.
    |
    */

    echo PHP_EOL;

    echo "Checking existing flights for missing seats..."
        . PHP_EOL;


    $repairSeatStmt =
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
                f.flight_id,
                s.seat_id,
                'Available',
                NULL,
                NULL

            FROM flight f

            INNER JOIN seat s
                ON s.aircraft_id =
                   f.aircraft_id

            LEFT JOIN flight_seat fs
                ON fs.flight_id =
                   f.flight_id

                AND fs.seat_id =
                    s.seat_id

            WHERE
                fs.flight_seat_id IS NULL

        ");


    $repairSeatStmt->execute();


    echo "Missing flight seats repaired."
        . PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | FLIGHT NUMBER SEQUENCE
    |--------------------------------------------------------------------------
    |
    | Start from a large number so we don't collide with VS101,
    | VS102, VS1287, etc.
    |
    */

    $flightSequence =
        time() * 1000;


    /*
    |--------------------------------------------------------------------------
    | COUNTERS
    |--------------------------------------------------------------------------
    */

    $newFlights = 0;

    $newStops = 0;

    $newSeats = 0;

    $nonStopFlights = 0;

    $oneStopFlights = 0;

    $twoStopFlights = 0;

    $threeStopFlights = 0;


    $airportCount =
        count($airports);


    /*
    |--------------------------------------------------------------------------
    | GENERATION
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
        |--------------------------------------------------------------------------
        | EVERY AIRPORT
        |--------------------------------------------------------------------------
        */

        foreach (
            $airports
            as $sourceIndex => $source
        ) {


            /*
            |--------------------------------------------------------------------------
            | DESTINATION CANDIDATES
            |--------------------------------------------------------------------------
            */

            $candidates = [];


            /*
             * Spread destinations through the complete airport list.
             */

            $offsets = [

                1,
                2,
                3,
                5,
                8,
                13,
                21,
                34,
                55,
                89

            ];


            shuffle(
                $offsets
            );


            foreach (
                $offsets as $offset
            ) {


                $destinationIndex =
                    (
                        $sourceIndex
                        +
                        $offset
                        +
                        ($day * 3)
                    )
                    %
                    $airportCount;


                if (
                    $destinationIndex
                    ===
                    $sourceIndex
                ) {

                    continue;

                }


                $destination =
                    $airports[
                        $destinationIndex
                    ];


                if (
                    (int)
                    $destination['airport_id']
                    ===
                    (int)
                    $source['airport_id']
                ) {

                    continue;

                }


                $candidates[] =
                    $destination;


                if (
                    count($candidates)
                    >=
                    $routesPerAirport
                ) {

                    break;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | ROUTES
            |--------------------------------------------------------------------------
            */

            foreach (
                $candidates
                as $destination
            ) {


                /*
                |--------------------------------------------------------------------------
                | DISTANCE
                |--------------------------------------------------------------------------
                */

                $distance =
                    calculateDistanceKm(

                        $source['latitude'] !== null
                            ? (float)$source['latitude']
                            : null,

                        $source['longitude'] !== null
                            ? (float)$source['longitude']
                            : null,

                        $destination['latitude'] !== null
                            ? (float)$destination['latitude']
                            : null,

                        $destination['longitude'] !== null
                            ? (float)$destination['longitude']
                            : null

                    );


                /*
                |--------------------------------------------------------------------------
                | STOPS
                |--------------------------------------------------------------------------
                |
                | Distribution:
                |
                | 70% nonstop
                | 20% one stop
                | 8% two stops
                | 2% three stops
                |
                */

                $random =
                    random_int(
                        1,
                        100
                    );


                if (
                    $random <= 70
                ) {

                    $stops = 0;

                } elseif (
                    $random <= 90
                ) {

                    $stops = 1;

                } elseif (
                    $random <= 98
                ) {

                    $stops = 2;

                } else {

                    $stops = 3;

                }


                /*
                |--------------------------------------------------------------------------
                | SELECT STOP AIRPORTS
                |--------------------------------------------------------------------------
                */

                $stopAirports = [];


                if (
                    $stops > 0
                ) {


                    /*
                    |--------------------------------------------------------------------------
                    | Try to find stop airports.
                    |--------------------------------------------------------------------------
                    */

                    $attempts = 0;


                    while (
                        count($stopAirports)
                        <
                        $stops
                        &&
                        $attempts < 100
                    ) {


                        $attempts++;


                        $stopIndex =
                            random_int(
                                0,
                                $airportCount - 1
                            );


                        $stopAirport =
                            $airports[
                                $stopIndex
                            ];


                        /*
                        | Don't use source.
                        */

                        if (
                            (int)
                            $stopAirport['airport_id']
                            ===
                            (int)
                            $source['airport_id']
                        ) {

                            continue;

                        }


                        /*
                        | Don't use destination.
                        */

                        if (
                            (int)
                            $stopAirport['airport_id']
                            ===
                            (int)
                            $destination['airport_id']
                        ) {

                            continue;

                        }


                        /*
                        | Don't duplicate stop airports.
                        */

                        $duplicate = false;


                        foreach (
                            $stopAirports
                            as $existingStop
                        ) {

                            if (
                                (int)
                                $existingStop['airport_id']
                                ===
                                (int)
                                $stopAirport['airport_id']
                            ) {

                                $duplicate = true;

                                break;

                            }

                        }


                        if (
                            !$duplicate
                        ) {

                            $stopAirports[] =
                                $stopAirport;

                        }

                    }


                    /*
                    | If insufficient airports were found,
                    | reduce the stop count.
                    */

                    $stops =
                        count(
                            $stopAirports
                        );

                }


                /*
                |--------------------------------------------------------------------------
                | DURATION
                |--------------------------------------------------------------------------
                */

                $durationMinutes =
                    calculateDurationMinutes(
                        $distance,
                        $stops
                    );


                /*
                |--------------------------------------------------------------------------
                | DEPARTURE TIME
                |--------------------------------------------------------------------------
                */

                $departureHour =
                    random_int(
                        5,
                        22
                    );


                $minutes =
                    [

                        0,
                        10,
                        15,
                        20,
                        30,
                        40,
                        45,
                        50

                    ];


                $departureMinute =
                    $minutes[
                        array_rand(
                            $minutes
                        )
                    ];


                $departure =
                    clone $currentDate;


                $departure->setTime(

                    $departureHour,

                    $departureMinute,

                    0

                );


                /*
                |--------------------------------------------------------------------------
                | DO NOT CREATE PAST FLIGHTS
                |--------------------------------------------------------------------------
                */

                if (
                    $departure <=
                    new DateTime()
                ) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | ARRIVAL
                |--------------------------------------------------------------------------
                */

                $arrival =
                    clone $departure;


                $arrival->modify(

                    '+'
                    .
                    $durationMinutes
                    .
                    ' minutes'

                );


                /*
                |--------------------------------------------------------------------------
                | UNIQUE FLIGHT NUMBER
                |--------------------------------------------------------------------------
                */

                $flightSequence++;


                $flightNumber =
                    'VS'
                    .
                    $flightSequence;


                /*
                |--------------------------------------------------------------------------
                | FINAL DUPLICATE CHECK
                |--------------------------------------------------------------------------
                */

                $existingFlightStmt->execute([

                    $flightNumber,

                    $departure->format(
                        'Y-m-d H:i:s'
                    )

                ]);


                if (
                    $existingFlightStmt
                        ->fetchColumn()
                ) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | AIRCRAFT
                |--------------------------------------------------------------------------
                */

                $aircraftId =
                    $aircraftIds[
                        array_rand(
                            $aircraftIds
                        )
                    ];


                /*
                |--------------------------------------------------------------------------
                | FARE
                |--------------------------------------------------------------------------
                */

                $fare =
                    calculateFare(
                        $distance,
                        $stops
                    );


                /*
                |--------------------------------------------------------------------------
                | INSERT FLIGHT
                |--------------------------------------------------------------------------
                */

                try {

                    $insertFlight->execute([

                        ':flight_number' =>
                            $flightNumber,

                        ':airline_name' =>
                            'VektorSky',

                        ':source_airport_id' =>
                            $source['airport_id'],

                        ':destination_airport_id' =>
                            $destination['airport_id'],

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

                } catch (
                    PDOException $e
                ) {

                    /*
                    | If a duplicate somehow occurs,
                    | skip it instead of stopping the
                    | complete generation.
                    */

                    if (
                        (int)
                        $e->errorInfo[1]
                        ===
                        1062
                    ) {

                        continue;

                    }


                    throw $e;

                }


                /*
                |--------------------------------------------------------------------------
                | FLIGHT ID
                |--------------------------------------------------------------------------
                */

                $flightId =
                    (int)
                    $pdo->lastInsertId();


                $newFlights++;


                /*
                |--------------------------------------------------------------------------
                | FLIGHT SEATS
                |--------------------------------------------------------------------------
                */

                $insertFlightSeats->execute([

                    ':flight_id' =>
                        $flightId,

                    ':aircraft_id' =>
                        $aircraftId

                ]);


                $newSeats +=
                    $insertFlightSeats
                        ->rowCount();


                /*
                |--------------------------------------------------------------------------
                | STOP COUNTERS
                |--------------------------------------------------------------------------
                */

                if (
                    $stops === 0
                ) {

                    $nonStopFlights++;

                } elseif (
                    $stops === 1
                ) {

                    $oneStopFlights++;

                } elseif (
                    $stops === 2
                ) {

                    $twoStopFlights++;

                } elseif (
                    $stops === 3
                ) {

                    $threeStopFlights++;

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT STOPS
                |--------------------------------------------------------------------------
                */

                if (
                    $stops > 0
                ) {


                    $segmentMinutes =
                        (int)
                        (
                            $durationMinutes
                            /
                            ($stops + 1)
                        );


                    for (
                        $stopNumber = 1;
                        $stopNumber <= $stops;
                        $stopNumber++
                    ) {


                        $stopAirport =
                            $stopAirports[
                                $stopNumber - 1
                            ];


                        /*
                        | Arrival at stop.
                        */

                        $stopArrival =
                            clone $departure;


                        $stopArrival->modify(

                            '+'
                            .
                            (
                                $segmentMinutes
                                *
                                $stopNumber
                            )
                            .
                            ' minutes'

                        );


                        /*
                        | Layover.
                        */

                        $layover =
                            random_int(
                                45,
                                120
                            );


                        $stopDeparture =
                            clone $stopArrival;


                        $stopDeparture->modify(

                            '+'
                            .
                            $layover
                            .
                            ' minutes'

                        );


                        /*
                        | Insert stop.
                        */

                        $insertStop->execute([

                            ':flight_id' =>
                                $flightId,

                            ':stop_number' =>
                                $stopNumber,

                            ':airport_id' =>
                                $stopAirport['airport_id'],

                            ':arrival_time' =>
                                $stopArrival->format(
                                    'Y-m-d H:i:s'
                                ),

                            ':departure_time' =>
                                $stopDeparture->format(
                                    'Y-m-d H:i:s'
                                )

                        ]);


                        $newStops++;

                    }

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | PROGRESS
        |--------------------------------------------------------------------------
        */

        echo
            "Day "
            .
            ($day + 1)
            .
            "/"
            .
            $daysToGenerate
            .
            " completed"
            .
            PHP_EOL;

    }


    /*
    |--------------------------------------------------------------------------
    | FINAL DATABASE COUNTS
    |--------------------------------------------------------------------------
    */

    $totalFlights =
        (int)
        $pdo
            ->query(
                "SELECT COUNT(*) FROM flight"
            )
            ->fetchColumn();


    $totalStops =
        (int)
        $pdo
            ->query(
                "SELECT COUNT(*) FROM flight_stop"
            )
            ->fetchColumn();


    $totalFlightSeats =
        (int)
        $pdo
            ->query(
                "SELECT COUNT(*) FROM flight_seat"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | COMPLETE
    |--------------------------------------------------------------------------
    */

    echo PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo "GENERATION COMPLETE"
        . PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo PHP_EOL;

    echo "New flights: "
        . $newFlights
        . PHP_EOL;

    echo "New flight seats: "
        . $newSeats
        . PHP_EOL;

    echo "New stop records: "
        . $newStops
        . PHP_EOL;

    echo PHP_EOL;

    echo "Non-stop flights: "
        . $nonStopFlights
        . PHP_EOL;

    echo "1-stop flights: "
        . $oneStopFlights
        . PHP_EOL;

    echo "2-stop flights: "
        . $twoStopFlights
        . PHP_EOL;

    echo "3-stop flights: "
        . $threeStopFlights
        . PHP_EOL;

    echo PHP_EOL;

    echo "DATABASE TOTALS"
        . PHP_EOL;

    echo "Flights: "
        . $totalFlights
        . PHP_EOL;

    echo "Flight seats: "
        . $totalFlightSeats
        . PHP_EOL;

    echo "Flight stops: "
        . $totalStops
        . PHP_EOL;


} catch (
    Throwable $e
) {

    echo PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo "ERROR"
        . PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo $e->getMessage()
        . PHP_EOL;

    echo PHP_EOL;

    if (
        $e instanceof PDOException
    ) {

        echo "SQLSTATE: "
            .
            $e->getCode()
            .
            PHP_EOL;

    }

}


echo "</pre>";