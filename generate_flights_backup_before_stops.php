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

    $daysToGenerate = 90;


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
    | GET ONE TEMPLATE FOR EACH FLIGHT NUMBER
    |--------------------------------------------------------------------------
    */

    $templateStmt = $pdo->query("

        SELECT

            f.flight_id,

            f.flight_number,

            f.airline_name,

            f.source_airport_id,

            f.destination_airport_id,

            f.aircraft_id,

            f.departure_time,

            f.arrival_time,

            f.fare

        FROM flight f

        INNER JOIN (

            SELECT

                flight_number,

                MIN(flight_id) AS first_id

            FROM flight

            GROUP BY flight_number

        ) x

            ON x.first_id = f.flight_id

        ORDER BY
            f.flight_number

    ");


    $templates =
        $templateStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (!$templates) {

        throw new Exception(
            'No flight templates found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PREPARE INSERT
    |--------------------------------------------------------------------------
    */

    $insertFlight =
        $pdo->prepare("

            INSERT IGNORE INTO flight

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
    | GET SEATS
    |--------------------------------------------------------------------------
    */

    $seatStmt =
        $pdo->prepare("

            SELECT

                seat_id

            FROM seat

            WHERE aircraft_id = ?

            ORDER BY seat_id

        ");


    /*
    |--------------------------------------------------------------------------
    | INSERT FLIGHT SEAT
    |--------------------------------------------------------------------------
    */

    $insertSeat =
        $pdo->prepare("

            INSERT IGNORE INTO flight_seat

            (
                flight_id,
                seat_id,
                status,
                booked_by,
                booked_at
            )

            VALUES

            (
                ?,
                ?,
                'Available',
                NULL,
                NULL
            )

        ");


    /*
    |--------------------------------------------------------------------------
    | GENERATE FLIGHTS
    |--------------------------------------------------------------------------
    */

    $createdFlights = 0;

    $createdSeats = 0;


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


        foreach (
            $templates as $template
        ) {


            /*
            |--------------------------------------------------------------------------
            | TEMPLATE TIMES
            |--------------------------------------------------------------------------
            */

            $templateDeparture =
                new DateTime(
                    $template[
                        'departure_time'
                    ]
                );


            $templateArrival =
                new DateTime(
                    $template[
                        'arrival_time'
                    ]
                );


            /*
            |--------------------------------------------------------------------------
            | FLIGHT DURATION
            |--------------------------------------------------------------------------
            */

            $duration =
                $templateArrival->getTimestamp()
                -
                $templateDeparture->getTimestamp();


            /*
            |--------------------------------------------------------------------------
            | DAILY DEPARTURE
            |--------------------------------------------------------------------------
            */

            $departure =
                clone $currentDate;


            $departure->setTime(

                (int)
                $templateDeparture
                    ->format('H'),

                (int)
                $templateDeparture
                    ->format('i'),

                (int)
                $templateDeparture
                    ->format('s')

            );


            /*
            |--------------------------------------------------------------------------
            | DAILY ARRIVAL
            |--------------------------------------------------------------------------
            */

            $arrival =
                clone $departure;


            $arrival->modify(
                '+' . $duration . ' seconds'
            );


            /*
            |--------------------------------------------------------------------------
            | DAILY PRICE
            |--------------------------------------------------------------------------
            */

            $baseFare =
                (float)
                $template['fare'];


            $dayNumber =
                (int)
                $departure->format('z');


            $flightId =
                (int)
                $template['flight_id'];


            $variationSeed =
                (
                    $dayNumber
                    +
                    ($flightId * 17)
                ) % 21;


            $variation =
                (
                    $variationSeed - 10
                ) / 100;


            $fare =
                $baseFare *
                (1 + $variation);


            /*
            |--------------------------------------------------------------------------
            | ROUND PRICE
            |--------------------------------------------------------------------------
            */

            $fare =
                round(
                    $fare / 50
                ) * 50;


            if ($fare < 500) {

                $fare = 500;
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT FLIGHT
            |--------------------------------------------------------------------------
            */

            $insertFlight->execute([

                'flight_number' =>
                    $template[
                        'flight_number'
                    ],

                'airline_name' =>
                    $template[
                        'airline_name'
                    ],

                'source_airport_id' =>
                    $template[
                        'source_airport_id'
                    ],

                'destination_airport_id' =>
                    $template[
                        'destination_airport_id'
                    ],

                'aircraft_id' =>
                    $template[
                        'aircraft_id'
                    ],

                'departure_time' =>
                    $departure->format(
                        'Y-m-d H:i:s'
                    ),

                'arrival_time' =>
                    $arrival->format(
                        'Y-m-d H:i:s'
                    ),

                'fare' =>
                    $fare

            ]);


            /*
            |--------------------------------------------------------------------------
            | GET ACTUAL FLIGHT ID
            |--------------------------------------------------------------------------
            */

            $findFlight =
                $pdo->prepare("

                    SELECT flight_id

                    FROM flight

                    WHERE

                        flight_number = ?

                        AND

                        departure_time = ?

                    LIMIT 1

                ");


            $findFlight->execute([

                $template[
                    'flight_number'
                ],

                $departure->format(
                    'Y-m-d H:i:s'
                )

            ]);


            $newFlight =
                $findFlight->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$newFlight) {

                continue;
            }


            $newFlightId =
                (int)
                $newFlight['flight_id'];


            /*
            |--------------------------------------------------------------------------
            | GET AIRCRAFT SEATS
            |--------------------------------------------------------------------------
            */

            $seatStmt->execute([

                $template[
                    'aircraft_id'
                ]

            ]);


            $seats =
                $seatStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            /*
            |--------------------------------------------------------------------------
            | CREATE FLIGHT-SPECIFIC SEATS
            |--------------------------------------------------------------------------
            */

            foreach (
                $seats as $seat
            ) {

                $insertSeat->execute([

                    $newFlightId,

                    $seat['seat_id']

                ]);


                if (
                    $insertSeat->rowCount() > 0
                ) {

                    $createdSeats++;
                }
            }


            $createdFlights++;
        }
    }


    echo "====================================\n";
    echo "VektorSky Flight Generator\n";
    echo "====================================\n\n";

    echo "Templates found: "
        . count($templates)
        . "\n";

    echo "Flight instances processed: "
        . $createdFlights
        . "\n";

    echo "Flight seats created: "
        . $createdSeats
        . "\n";

    echo "\nGeneration completed successfully.\n";


} catch (Throwable $e) {

    echo "\nERROR:\n";

    echo $e->getMessage();

}

echo "</pre>";