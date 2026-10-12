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

    $batchSize = 300;


    /*
    |--------------------------------------------------------------------------
    | PREPARE - FIND MISSING FLIGHTS
    |--------------------------------------------------------------------------
    */

    $findFlights = $pdo->prepare("

        SELECT
            f.flight_id,
            f.aircraft_id

        FROM flight f

        WHERE NOT EXISTS (

            SELECT 1

            FROM flight_seat fs

            WHERE fs.flight_id = f.flight_id

        )

        ORDER BY f.flight_id

        LIMIT :limit

    ");


    /*
    |--------------------------------------------------------------------------
    | PREPARE - INSERT SEATS
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

            :flight_id,

            s.seat_id,

            'Available',

            NULL,

            NULL

        FROM seat s

        WHERE s.aircraft_id = :aircraft_id

    ");


    /*
    |--------------------------------------------------------------------------
    | COUNTERS
    |--------------------------------------------------------------------------
    */

    $processedFlights = 0;

    $insertedSeats = 0;

    $batchNumber = 0;


    /*
    |--------------------------------------------------------------------------
    | REPAIR LOOP
    |--------------------------------------------------------------------------
    */

    while (true) {

        $batchNumber++;


        /*
        |--------------------------------------------------------------------------
        | FIND NEXT BATCH
        |--------------------------------------------------------------------------
        */

        $findFlights->bindValue(
            ':limit',
            $batchSize,
            PDO::PARAM_INT
        );

        $findFlights->execute();


        $flights =
            $findFlights->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | FINISHED
        |--------------------------------------------------------------------------
        */

        if (!$flights) {

            break;

        }


        /*
        |--------------------------------------------------------------------------
        | PROCESS BATCH
        |--------------------------------------------------------------------------
        */

        foreach ($flights as $flight) {

            $insertSeats->execute([

                ':flight_id' =>
                    (int)$flight['flight_id'],

                ':aircraft_id' =>
                    (int)$flight['aircraft_id']

            ]);


            $insertedSeats +=
                $insertSeats->rowCount();


            $processedFlights++;

        }


        /*
        |--------------------------------------------------------------------------
        | PROGRESS
        |--------------------------------------------------------------------------
        */

        echo
            "Batch "
            .
            $batchNumber
            .
            " | Flights repaired: "
            .
            $processedFlights
            .
            " | Seats inserted: "
            .
            $insertedSeats
            .
            PHP_EOL;


        /*
        |--------------------------------------------------------------------------
        | RELEASE MEMORY
        |--------------------------------------------------------------------------
        */

        unset($flights);

    }


    /*
    |--------------------------------------------------------------------------
    | FINAL CHECK
    |--------------------------------------------------------------------------
    */

    $remainingStmt =
        $pdo->query("

            SELECT
                COUNT(*)

            FROM flight f

            WHERE NOT EXISTS (

                SELECT 1

                FROM flight_seat fs

                WHERE fs.flight_id = f.flight_id

            )

        ");


    $remaining =
        (int)$remainingStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL SEATS
    |--------------------------------------------------------------------------
    */

    $totalSeatsStmt =
        $pdo->query("

            SELECT COUNT(*)

            FROM flight_seat

        ");


    $totalSeats =
        (int)$totalSeatsStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | RESULT
    |--------------------------------------------------------------------------
    */

    echo PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo "SEAT REPAIR COMPLETE"
        . PHP_EOL;

    echo "========================================"
        . PHP_EOL;

    echo PHP_EOL;

    echo "Flights repaired: "
        . $processedFlights
        . PHP_EOL;

    echo "Seats inserted: "
        . $insertedSeats
        . PHP_EOL;

    echo "Flights still without seats: "
        . $remaining
        . PHP_EOL;

    echo "Total flight seats: "
        . $totalSeats
        . PHP_EOL;


} catch (Throwable $e) {

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

    echo $e->getTraceAsString()
        . PHP_EOL;

}


echo "</pre>";