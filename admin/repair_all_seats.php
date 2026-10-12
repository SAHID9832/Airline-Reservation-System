<?php

declare(strict_types=1);

require_once '../config.php';
require_once '../includes/functions.php';
require_once 'admin_guard.php';

$page_title = 'Repair Aircraft Seats';

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function esc($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| AIRCRAFT SEAT LAYOUT
|--------------------------------------------------------------------------
*/

function getAircraftLayout(string $model): array
{
    $model = strtolower(trim($model));

    /*
    |--------------------------------------------------------------------------
    | ATR / Regional
    |--------------------------------------------------------------------------
    */

    $regional = [
        'atr 42',
        'atr 72',
        'embraer e170',
        'embraer e175',
        'embraer e190',
        'embraer e195',
        'embraer e195-e2'
    ];

    foreach ($regional as $name) {

        if (strpos($model, $name) !== false) {

            return [
                'columns' => [
                    'A',
                    'B',
                    'C',
                    'D'
                ],
                'aisles' => [2],
                'business_rows' => 2
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Airbus A319 / A320 / A321
    | Boeing 737
    |--------------------------------------------------------------------------
    */

    $narrow = [
        'airbus a319',
        'airbus a320',
        'airbus a321',
        'boeing 737'
    ];

    foreach ($narrow as $name) {

        if (strpos($model, $name) !== false) {

            return [
                'columns' => [
                    'A',
                    'B',
                    'C',
                    'D',
                    'E',
                    'F'
                ],
                'aisles' => [
                    3
                ],
                'business_rows' => 3
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Boeing 767 / Airbus A330
    | 2 + 3 + 2
    |--------------------------------------------------------------------------
    */

    $sevenWide = [
        'boeing 767',
        'airbus a330'
    ];

    foreach ($sevenWide as $name) {

        if (strpos($model, $name) !== false) {

            return [
                'columns' => [
                    'A',
                    'B',
                    'C',
                    'D',
                    'E',
                    'F',
                    'G'
                ],
                'aisles' => [
                    2,
                    5
                ],
                'business_rows' => 4
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Boeing 787 / Airbus A350
    | 3 + 3 + 3
    |--------------------------------------------------------------------------
    */

    $nineWide = [
        'boeing 787',
        'airbus a350'
    ];

    foreach ($nineWide as $name) {

        if (strpos($model, $name) !== false) {

            return [
                'columns' => [
                    'A',
                    'B',
                    'C',
                    'D',
                    'E',
                    'F',
                    'G',
                    'H',
                    'J'
                ],
                'aisles' => [
                    3,
                    6
                ],
                'business_rows' => 4
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Boeing 777 / 747 / Airbus A380
    | 3 + 4 + 3
    |--------------------------------------------------------------------------
    */

    $tenWide = [
        'boeing 777',
        'boeing 747',
        'airbus a380'
    ];

    foreach ($tenWide as $name) {

        if (strpos($model, $name) !== false) {

            return [
                'columns' => [
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
                ],
                'aisles' => [
                    3,
                    7
                ],
                'business_rows' => 5
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DEFAULT
    |--------------------------------------------------------------------------
    */

    return [
        'columns' => [
            'A',
            'B',
            'C',
            'D',
            'E',
            'F'
        ],
        'aisles' => [
            3
        ],
        'business_rows' => 3
    ];
}


/*
|--------------------------------------------------------------------------
| SEAT TYPE
|--------------------------------------------------------------------------
*/

function getSeatType(
    string $column,
    array $columns,
    array $aisles
): string {

    $position =
        array_search(
            $column,
            $columns,
            true
        );


    /*
    |--------------------------------------------------------------------------
    | Window
    |--------------------------------------------------------------------------
    */

    if (
        $position === 0
        ||
        $position === count($columns) - 1
    ) {

        return 'Window';
    }


    /*
    |--------------------------------------------------------------------------
    | Aisle
    |--------------------------------------------------------------------------
    */

    foreach ($aisles as $aisle) {

        if (
            $position === $aisle - 1
            ||
            $position === $aisle
        ) {

            return 'Aisle';
        }
    }


    return 'Middle';
}


/*
|--------------------------------------------------------------------------
| SEAT PRICE
|--------------------------------------------------------------------------
*/

function getSeatPrice(
    string $seatClass,
    string $seatType
): float {

    if (
        strtolower($seatClass)
        === 'business'
    ) {

        return 1499.00;
    }


    if (
        strtolower($seatType)
        === 'window'
    ) {

        return 699.00;
    }


    if (
        strtolower($seatType)
        === 'aisle'
    ) {

        return 499.00;
    }


    return 399.00;
}


/*
|--------------------------------------------------------------------------
| REPAIR ALL AIRCRAFT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['repair_all'])
) {

    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Get every aircraft
        |--------------------------------------------------------------------------
        */

        $aircraftStmt = $pdo->query("
            SELECT
                aircraft_id,
                aircraft_no,
                model,
                total_seats
            FROM aircraft
            ORDER BY aircraft_id
        ");

        $aircrafts =
            $aircraftStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        $totalCreated = 0;
        $aircraftFixed = 0;
        $flightSeatsCreated = 0;


        /*
        |--------------------------------------------------------------------------
        | Process every aircraft
        |--------------------------------------------------------------------------
        */

        foreach (
            $aircrafts as $aircraft
        ) {

            $aircraftId =
                (int)$aircraft['aircraft_id'];

            $capacity =
                (int)$aircraft['total_seats'];


            if ($capacity <= 0) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Existing seats
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    seat_id,
                    seat_number
                FROM seat
                WHERE aircraft_id = ?
            ");

            $stmt->execute([
                $aircraftId
            ]);

            $existingSeats =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            /*
            |--------------------------------------------------------------------------
            | Existing seat numbers
            |--------------------------------------------------------------------------
            */

            $existingNumbers = [];

            foreach (
                $existingSeats as $seat
            ) {

                $existingNumbers[
                    strtoupper(
                        trim(
                            $seat['seat_number']
                        )
                    )
                ] = true;
            }


            $currentCount =
                count($existingSeats);


            /*
            |--------------------------------------------------------------------------
            | Already complete
            |--------------------------------------------------------------------------
            */

            if (
                $currentCount >= $capacity
            ) {

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Layout
            |--------------------------------------------------------------------------
            */

            $layout =
                getAircraftLayout(
                    $aircraft['model']
                );


            $columns =
                $layout['columns'];

            $aisles =
                $layout['aisles'];

            $businessRows =
                (int)$layout['business_rows'];


            /*
            |--------------------------------------------------------------------------
            | Add missing seats
            |--------------------------------------------------------------------------
            */

            $addedForAircraft = 0;

            $row = 1;


            while (
                $currentCount <
                $capacity
            ) {

                foreach (
                    $columns as $column
                ) {

                    if (
                        $currentCount >=
                        $capacity
                    ) {

                        break;
                    }


                    $seatNumber =
                        $row . $column;


                    /*
                    |--------------------------------------------------------------------------
                    | Never duplicate existing seat
                    |--------------------------------------------------------------------------
                    */

                    if (
                        isset(
                            $existingNumbers[
                                $seatNumber
                            ]
                        )
                    ) {

                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Business / Economy
                    |--------------------------------------------------------------------------
                    */

                    $seatClass =
                        $row <= $businessRows
                            ? 'Business'
                            : 'Economy';


                    /*
                    |--------------------------------------------------------------------------
                    | Seat type
                    |--------------------------------------------------------------------------
                    */

                    $seatType =
                        getSeatType(
                            $column,
                            $columns,
                            $aisles
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Price
                    |--------------------------------------------------------------------------
                    */

                    $seatPrice =
                        getSeatPrice(
                            $seatClass,
                            $seatType
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Insert
                    |--------------------------------------------------------------------------
                    */

                    $insert =
                        $pdo->prepare("
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


                    $insert->execute([
                        $aircraftId,
                        $seatNumber,
                        $seatClass,
                        $seatType,
                        $seatPrice
                    ]);


                    $existingNumbers[
                        $seatNumber
                    ] = true;


                    $currentCount++;

                    $addedForAircraft++;

                    $totalCreated++;
                }


                $row++;


                /*
                |--------------------------------------------------------------------------
                | Safety
                |--------------------------------------------------------------------------
                */

                if ($row > 1000) {

                    throw new Exception(
                        'Seat generation stopped for aircraft ' .
                        $aircraft['aircraft_no']
                    );
                }
            }


            if (
                $addedForAircraft > 0
            ) {

                $aircraftFixed++;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE FLIGHT SEAT INVENTORY
        |--------------------------------------------------------------------------
        |
        | Every flight gets every seat belonging
        | to its aircraft.
        |
        */

        $flightStmt = $pdo->query("
            SELECT
                f.flight_id,
                f.aircraft_id
            FROM flight f
            INNER JOIN aircraft a
                ON f.aircraft_id = a.aircraft_id
        ");

        $flights =
            $flightStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        $seatStmt = $pdo->query("
            SELECT
                seat_id,
                aircraft_id
            FROM seat
        ");

        $allSeats =
            $seatStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | Group seats by aircraft
        |--------------------------------------------------------------------------
        */

        $seatsByAircraft = [];


        foreach (
            $allSeats as $seat
        ) {

            $id =
                (int)$seat['aircraft_id'];

            if (
                !isset(
                    $seatsByAircraft[$id]
                )
            ) {

                $seatsByAircraft[$id] = [];
            }


            $seatsByAircraft[$id][] =
                (int)$seat['seat_id'];
        }


        /*
        |--------------------------------------------------------------------------
        | Prepared statements
        |--------------------------------------------------------------------------
        */

        $checkFlightSeat =
            $pdo->prepare("
                SELECT
                    flight_seat_id
                FROM flight_seat
                WHERE
                    flight_id = ?
                    AND seat_id = ?
                LIMIT 1
            ");


        $insertFlightSeat =
            $pdo->prepare("
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


        /*
        |--------------------------------------------------------------------------
        | Create missing flight-seat records
        |--------------------------------------------------------------------------
        */

        foreach (
            $flights as $flight
        ) {

            $flightId =
                (int)$flight['flight_id'];

            $aircraftId =
                (int)$flight['aircraft_id'];


            if (
                empty(
                    $seatsByAircraft[
                        $aircraftId
                    ]
                )
            ) {

                continue;
            }


            foreach (
                $seatsByAircraft[
                    $aircraftId
                ] as $seatId
            ) {

                $checkFlightSeat->execute([
                    $flightId,
                    $seatId
                ]);


                $exists =
                    $checkFlightSeat
                        ->fetchColumn();


                if (
                    $exists !== false
                ) {

                    continue;
                }


                $insertFlightSeat->execute([
                    $flightId,
                    $seatId
                ]);


                $flightSeatsCreated++;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        $message =
            'Repair completed successfully. ' .
            $aircraftFixed .
            ' aircraft updated, ' .
            $totalCreated .
            ' seats created, and ' .
            $flightSeatsCreated .
            ' flight-seat records created.';


    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        $error =
            'Repair failed: ' .
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT AIRCRAFT STATUS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        a.aircraft_id,
        a.aircraft_no,
        a.model,
        a.total_seats,
        COUNT(s.seat_id) AS current_seats
    FROM aircraft a
    LEFT JOIN seat s
        ON s.aircraft_id = a.aircraft_id
    GROUP BY
        a.aircraft_id,
        a.aircraft_no,
        a.model,
        a.total_seats
    ORDER BY
        a.aircraft_id
");

$aircraftStatus =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


include '../includes/header.php';

?>

<style>

.repair-page {
    max-width: 1200px;
    margin: auto;
    padding: 35px 20px 70px;
}

.repair-hero {
    background:
        linear-gradient(
            135deg,
            #071c34,
            #0b4f86,
            #168bd0
        );
    color: white;
    padding: 35px;
    border-radius: 24px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
    box-shadow:
        0 20px 50px
        rgba(7,28,52,.2);
}

.repair-hero::after {
    content: "✈";
    position: absolute;
    right: 40px;
    top: 5px;
    font-size: 120px;
    opacity: .08;
    transform: rotate(-15deg);
}

.repair-hero h1 {
    color: white;
    margin: 0 0 8px;
    font-size: 2rem;
}

.repair-hero p {
    color: #d9edff;
    margin: 0;
}

.repair-warning {
    background: #fff8e8;
    border: 1px solid #f1d48a;
    color: #805b00;
    padding: 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    line-height: 1.6;
}

.repair-success {
    background: #eaf8ef;
    border: 1px solid #b7e2c2;
    color: #176b35;
    padding: 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-weight: 700;
}

.repair-error {
    background: #fff0f0;
    border: 1px solid #efb5b5;
    color: #a12222;
    padding: 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-weight: 700;
}

.repair-action {
    background: white;
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow:
        0 8px 25px
        rgba(11,37,69,.05);
}

.repair-button {
    border: 0;
    background:
        linear-gradient(
            135deg,
            #168bd0,
            #1c7ed6
        );
    color: white;
    padding: 14px 22px;
    border-radius: 11px;
    font-weight: 800;
    cursor: pointer;
    font-size: .9rem;
    transition: .2s ease;
}

.repair-button:hover {
    transform: translateY(-2px);
    box-shadow:
        0 8px 20px
        rgba(28,126,214,.3);
}

.repair-table {
    background: white;
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    box-shadow:
        0 8px 25px
        rgba(11,37,69,.05);
}

.repair-table-wrap {
    overflow-x: auto;
}

.repair-table table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

.repair-table th {
    background: var(--navy);
    color: white;
    padding: 14px;
    text-align: left;
    font-size: .72rem;
}

.repair-table td {
    padding: 13px 14px;
    border-bottom: 1px solid var(--border);
    font-size: .8rem;
}

.repair-table tr:last-child td {
    border-bottom: 0;
}

.ok {
    color: #16803c;
    font-weight: 800;
}

.missing {
    color: #b06b00;
    font-weight: 900;
}

.progress {
    width: 130px;
    height: 7px;
    background: #e9edf2;
    border-radius: 999px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background:
        linear-gradient(
            90deg,
            #1c7ed6,
            #38bdf8
        );
}

</style>


<main class="repair-page">


    <section class="repair-hero">

        <h1>
            🔧 Aircraft Seat Repair
        </h1>

        <p>
            Safely complete missing seats for every aircraft
            and create missing flight-seat inventory.
        </p>

    </section>


    <?php if ($message): ?>

        <div class="repair-success">
            ✓ <?= esc($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="repair-error">
            ⚠ <?= esc($error) ?>
        </div>

    <?php endif; ?>


    <div class="repair-warning">

        <strong>Important:</strong>

        This repair does <strong>not</strong> delete
        existing seats.

        Existing booking seats and booked
        <code>flight_seat</code> records are preserved.

        Only missing seats and missing flight-seat
        records are added.

    </div>


    <section class="repair-action">

        <h2>
            Repair Complete Fleet
        </h2>

        <p class="muted">

            The system will compare each aircraft's
            <strong>total_seats</strong> with its existing
            seat records and create only the missing seats.

        </p>


        <form
            method="POST"
            onsubmit="
                return confirm(
                    'Start the complete fleet seat repair? Existing seats and bookings will NOT be deleted.'
                );
            "
        >

            <button
                type="submit"
                name="repair_all"
                class="repair-button"
            >

                ✈ Repair All Aircraft Seats

            </button>

        </form>

    </section>


    <section class="repair-table">

        <div class="repair-table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Aircraft
                        </th>

                        <th>
                            Model
                        </th>

                        <th>
                            Capacity
                        </th>

                        <th>
                            Current
                        </th>

                        <th>
                            Missing
                        </th>

                        <th>
                            Progress
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $aircraftStatus
                    as $aircraft
                ): ?>


                    <?php

                    $capacity =
                        (int)$aircraft[
                            'total_seats'
                        ];

                    $current =
                        (int)$aircraft[
                            'current_seats'
                        ];

                    $missing =
                        max(
                            0,
                            $capacity -
                            $current
                        );

                    $percentage =
                        $capacity > 0
                            ? min(
                                100,
                                (
                                    $current /
                                    $capacity
                                ) * 100
                            )
                            : 0;

                    ?>


                    <tr>

                        <td>

                            <?= (int)
                                $aircraft[
                                    'aircraft_id'
                                ] ?>

                        </td>


                        <td>

                            <strong>

                                <?= esc(
                                    $aircraft[
                                        'aircraft_no'
                                    ]
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <?= esc(
                                $aircraft[
                                    'model'
                                ]
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $capacity
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $current
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                $missing > 0
                            ): ?>

                                <span class="missing">

                                    <?= number_format(
                                        $missing
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="ok">
                                    ✓ Complete
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="
                                        width:
                                        <?= $percentage ?>%;
                                    "
                                ></div>

                            </div>

                        </td>

                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </section>


</main>


<?php

include '../includes/footer.php';

?>