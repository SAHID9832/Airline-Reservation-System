<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$flightId = isset($_GET['flight_id'])
    ? (int)$_GET['flight_id']
    : 0;

if ($flightId <= 0) {
    header('Location: flights.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET FLIGHT + AIRCRAFT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        f.flight_id,
        f.flight_number,
        f.aircraft_id,
        a.aircraft_no,
        a.model
    FROM flight f
    INNER JOIN aircraft a
        ON f.aircraft_id = a.aircraft_id
    WHERE f.flight_id = ?
    LIMIT 1
");

$stmt->execute([$flightId]);

$flight = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flight) {
    die('Flight not found.');
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING FLIGHT SEATS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM flight_seat
    WHERE flight_id = ?
");

$stmt->execute([$flightId]);

$existingSeats =
    (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| GET AIRCRAFT SEATS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        seat_id
    FROM seat
    WHERE aircraft_id = ?
    ORDER BY seat_id
");

$stmt->execute([
    $flight['aircraft_id']
]);

$aircraftSeats =
    $stmt->fetchAll(PDO::FETCH_COLUMN);


/*
|--------------------------------------------------------------------------
| SYNC
|--------------------------------------------------------------------------
*/

$added = 0;
$alreadyExists = 0;

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | INSERT MISSING SEATS
    |--------------------------------------------------------------------------
    */

    $checkSeat = $pdo->prepare("
        SELECT flight_seat_id
        FROM flight_seat
        WHERE flight_id = ?
        AND seat_id = ?
        LIMIT 1
    ");


    $insertSeat = $pdo->prepare("
        INSERT INTO flight_seat
        (
            flight_id,
            seat_id,
            status
        )
        VALUES
        (
            ?,
            ?,
            'Available'
        )
    ");


    foreach (
        $aircraftSeats
        as $seatId
    ) {

        $checkSeat->execute([
            $flightId,
            $seatId
        ]);


        if ($checkSeat->fetch()) {

            $alreadyExists++;

            continue;
        }


        $insertSeat->execute([
            $flightId,
            $seatId
        ]);

        $added++;
    }


    $pdo->commit();


} catch (Throwable $e) {

    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    die(
        'Synchronization failed: ' .
        h($e->getMessage())
    );
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

header(
    'Location: flights.php?sync=success'
);

exit;