<?php

require_once __DIR__ . '/config.php';

echo "DATABASE CHECK" . PHP_EOL;
echo "============================" . PHP_EOL;

$flights = $pdo
    ->query("SELECT COUNT(*) FROM flight")
    ->fetchColumn();

$flightSeats = $pdo
    ->query("SELECT COUNT(*) FROM flight_seat")
    ->fetchColumn();

$stops = $pdo
    ->query("SELECT COUNT(*) FROM flight_stop")
    ->fetchColumn();

$missingSeats = $pdo
    ->query("
        SELECT COUNT(*)
        FROM flight f
        WHERE NOT EXISTS (
            SELECT 1
            FROM flight_seat fs
            WHERE fs.flight_id = f.flight_id
        )
    ")
    ->fetchColumn();

echo "Flights: " . $flights . PHP_EOL;
echo "Flight seats: " . $flightSeats . PHP_EOL;
echo "Flight stops: " . $stops . PHP_EOL;
echo "Flights without seats: " . $missingSeats . PHP_EOL;

echo PHP_EOL;

if ((int)$missingSeats === 0) {
    echo "STATUS: ALL FLIGHTS HAVE SEATS" . PHP_EOL;
} else {
    echo "STATUS: SOME FLIGHTS STILL NEED SEATS" . PHP_EOL;
}