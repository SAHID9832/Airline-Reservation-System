<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$aircraftId = (int)($_GET['aircraft_id'] ?? 0);

if ($aircraftId <= 0) {
    header('Location: aircraft.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| GET AIRCRAFT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        aircraft_id,
        aircraft_no,
        model,
        total_seats
    FROM aircraft
    WHERE aircraft_id = ?
    LIMIT 1
");

$stmt->execute([$aircraftId]);

$aircraft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aircraft) {
    die('Aircraft not found.');
}


/*
|--------------------------------------------------------------------------
| DETERMINE AIRCRAFT LAYOUT
|--------------------------------------------------------------------------
*/

$model = strtolower(
    trim($aircraft['model'])
);

$totalSeats =
    (int)$aircraft['total_seats'];


/*
|--------------------------------------------------------------------------
| DEFAULT CONFIGURATION
|--------------------------------------------------------------------------
*/

$columns = [
    'A',
    'B',
    'C',
    'D',
    'E',
    'F'
];

$businessRows = 0;
$firstRows = 0;

$economyPrice = 499;
$businessPrice = 1499;
$firstPrice = 2499;


/*
|--------------------------------------------------------------------------
| AIRCRAFT-SPECIFIC CONFIGURATION
|--------------------------------------------------------------------------
*/

if (
    strpos($model, 'a320') !== false ||
    strpos($model, 'a321') !== false ||
    strpos($model, '737') !== false
) {

    /*
    6 seats per row
    */

    $columns = [
        'A',
        'B',
        'C',
        'D',
        'E',
        'F'
    ];

    $businessRows = 4;

} elseif (
    strpos($model, '777') !== false ||
    strpos($model, '787') !== false ||
    strpos($model, 'a330') !== false ||
    strpos($model, 'a350') !== false
) {

    /*
    Wide-body aircraft
    */

    $columns = [
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'J',
        'K'
    ];

    $businessRows = 6;

} elseif (
    strpos($model, '747') !== false
) {

    /*
    Boeing 747
    */

    $columns = [
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'J',
        'K'
    ];

    $businessRows = 6;
}


/*
|--------------------------------------------------------------------------
| GENERATE SEATS
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Check existing seats
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT seat_number
        FROM seat
        WHERE aircraft_id = ?
    ");

    $stmt->execute([
        $aircraftId
    ]);

    $existingSeats = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $existingSeats[
            strtoupper(
                $row['seat_number']
            )
        ] = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate required rows
    |--------------------------------------------------------------------------
    */

    $seatsPerRow =
        count($columns);

    $rows = (int)ceil(
        $totalSeats / $seatsPerRow
    );


    /*
    |--------------------------------------------------------------------------
    | Insert statement
    |--------------------------------------------------------------------------
    */

    $insert = $pdo->prepare("
        INSERT INTO seat
        (
            aircraft_id,
            seat_number,
            seat_class,
            status,
            seat_type,
            seat_price
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'Available',
            ?,
            ?
        )
    ");


    $created = 0;
    $seatCounter = 0;


    /*
    |--------------------------------------------------------------------------
    | Generate each seat
    |--------------------------------------------------------------------------
    */

    for (
        $rowNumber = 1;
        $rowNumber <= $rows;
        $rowNumber++
    ) {

        foreach ($columns as $column) {

            if (
                $seatCounter >=
                $totalSeats
            ) {
                break 2;
            }


            $seatNumber =
                $rowNumber . $column;


            /*
            Prevent duplicate seats
            */

            if (
                isset(
                    $existingSeats[
                        $seatNumber
                    ]
                )
            ) {

                $seatCounter++;

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Determine seat type
            |--------------------------------------------------------------------------
            */

            if (
                $column === 'A' ||
                $column === 'F'
            ) {

                $seatType = 'Window';

            } elseif (
                $column === 'C' ||
                $column === 'D'
            ) {

                $seatType = 'Aisle';

            } else {

                $seatType = 'Middle';
            }


            /*
            |--------------------------------------------------------------------------
            | Determine class
            |--------------------------------------------------------------------------
            */

            if (
                $rowNumber <=
                $firstRows
            ) {

                $seatClass =
                    'First';

                $seatPrice =
                    $firstPrice;

            } elseif (
                $rowNumber <=
                $businessRows
            ) {

                $seatClass =
                    'Business';

                $seatPrice =
                    $businessPrice;

            } else {

                $seatClass =
                    'Economy';

                $seatPrice =
                    $economyPrice;
            }


            /*
            |--------------------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------------------
            */

            $insert->execute([
                $aircraftId,
                $seatNumber,
                $seatClass,
                $seatType,
                $seatPrice
            ]);

            $created++;

            $seatCounter++;
        }
    }


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: aircraft_seats.php?aircraft_id=' .
        $aircraftId .
        '&generated=' .
        $created
    );

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        'Seat generation failed: ' .
        h($e->getMessage())
    );
}