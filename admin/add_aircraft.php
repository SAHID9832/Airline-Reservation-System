<?php

require_once '../config.php';
require_once 'admin_guard.php';
require_once '../includes/functions.php';

$page_title = 'Add Aircraft';

$error = '';

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$aircraftNo = '';
$model = '';
$airlineName = '';
$totalSeats = 180;


/*
|--------------------------------------------------------------------------
| HANDLE FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aircraftNo = trim($_POST['aircraft_no'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $airlineName = trim($_POST['airline_name'] ?? '');
    $totalSeats = (int)($_POST['total_seats'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($aircraftNo === '') {

        $error = 'Please enter an aircraft number.';

    } elseif ($model === '') {

        $error = 'Please enter the aircraft model.';

    } elseif ($airlineName === '') {

        $error = 'Please enter the airline name.';

    } elseif ($totalSeats < 1 || $totalSeats > 500) {

        $error = 'Total seats must be between 1 and 500.';
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE AIRCRAFT NUMBER
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT aircraft_id
            FROM aircraft
            WHERE aircraft_no = ?
            LIMIT 1
        ");

        $stmt->execute([
            $aircraftNo
        ]);

        if ($stmt->fetch()) {

            $error =
                'An aircraft with this aircraft number already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE AIRCRAFT + SEATS
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            | Start transaction
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | INSERT AIRCRAFT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO aircraft
                (
                    aircraft_no,
                    model,
                    total_seats,
                    airline_name
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $aircraftNo,
                $model,
                $totalSeats,
                $airlineName
            ]);


            $aircraftId =
                (int)$pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | GENERATE SEATS
            |--------------------------------------------------------------------------
            |
            | We create a normal 6-seat row:
            |
            | A B C D E F
            |
            | Example:
            |
            | 1A 1B 1C 1D 1E 1F
            | 2A 2B 2C 2D 2E 2F
            |
            */

            $letters = [
                'A',
                'B',
                'C',
                'D',
                'E',
                'F'
            ];


            /*
            | Calculate number of rows
            */

            $rows =
                (int)ceil(
                    $totalSeats / 6
                );


            $seatInsert = $pdo->prepare("
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


            $seatCounter = 0;


            for (
                $row = 1;
                $row <= $rows;
                $row++
            ) {

                foreach (
                    $letters
                    as $letter
                ) {

                    if (
                        $seatCounter >=
                        $totalSeats
                    ) {
                        break;
                    }


                    $seatNumber =
                        $row . $letter;


                    /*
                    |--------------------------------------------------------------------------
                    | SEAT CLASS / TYPE / PRICE
                    |--------------------------------------------------------------------------
                    |
                    | A/F = Window
                    | B/E = Standard
                    | C/D = Aisle
                    |
                    | First 2 rows = Business
                    | Remaining = Economy
                    */

                    if ($row <= 2) {

                        $seatClass =
                            'Business';

                        $seatType =
                            'Business';

                        $seatPrice =
                            1499.00;

                    } else {

                        $seatClass =
                            'Economy';


                        if (
                            $letter === 'A'
                            ||
                            $letter === 'F'
                        ) {

                            $seatType =
                                'Window';

                            $seatPrice =
                                699.00;

                        } elseif (
                            $letter === 'C'
                            ||
                            $letter === 'D'
                        ) {

                            $seatType =
                                'Aisle';

                            $seatPrice =
                                599.00;

                        } else {

                            $seatType =
                                'Standard';

                            $seatPrice =
                                499.00;
                        }
                    }


                    $seatInsert->execute([
                        $aircraftId,
                        $seatNumber,
                        $seatClass,
                        $seatType,
                        $seatPrice
                    ]);


                    $seatCounter++;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY SEATS
            |--------------------------------------------------------------------------
            */

            $verify = $pdo->prepare("
                SELECT COUNT(*)
                FROM seat
                WHERE aircraft_id = ?
            ");

            $verify->execute([
                $aircraftId
            ]);

            $createdSeats =
                (int)$verify->fetchColumn();


            if (
                $createdSeats !==
                $totalSeats
            ) {

                throw new Exception(
                    'Seat generation failed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $_SESSION['admin_success'] =
                'Aircraft ' .
                $aircraftNo .
                ' created successfully with ' .
                $createdSeats .
                ' seats.';


            header(
                'Location: aircraft.php'
            );

            exit;

        } catch (
            Throwable $e
        ) {

            /*
            | Rollback if transaction is active
            */

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $error =
                'Unable to create aircraft. ' .
                $e->getMessage();
        }
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    VektorSky | Add Aircraft
</title>


<style>

/* =========================================================
   BASE
========================================================= */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Inter,
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #eef5ff,
            #f8fbff,
            #eefafc
        );

    color: #101828;

    min-height: 100vh;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    height: 70px;

    padding:
        0 5%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(255,255,255,.88);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid #e5e7eb;

    position: sticky;

    top: 0;

    z-index: 100;
}


.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #101828;

    text-decoration: none;

    font-size: 21px;

    font-weight: 900;
}


.logo-icon {

    width: 40px;

    height: 40px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.22);
}


.logo span {

    color: #2563eb;
}


.nav-back {

    color: #475467;

    text-decoration: none;

    font-size: 12px;

    font-weight: 800;

    transition: .2s;
}


.nav-back:hover {

    color: #2563eb;

    text-decoration: none;
}


/* =========================================================
   PAGE
========================================================= */

.page {

    max-width: 1100px;

    margin: 0 auto;

    padding:
        35px 20px 70px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    margin-bottom: 25px;
}


.page-header h1 {

    margin: 0;

    color: #0b2545;

    font-size: 30px;

    font-weight: 900;
}


.page-header p {

    margin:
        7px 0 0;

    color: #667085;

    font-size: 13px;
}


/* =========================================================
   LAYOUT
========================================================= */

.content {

    display: grid;

    grid-template-columns:
        1fr 330px;

    gap: 22px;

    align-items: start;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background:
        rgba(255,255,255,.94);

    border:
        1px solid #e4e7ec;

    border-radius:
        20px;

    padding:
        25px;

    box-shadow:
        0 12px 40px
        rgba(16,24,40,.06);
}


.card-title {

    margin:
        0 0 5px;

    color:
        #0b2545;

    font-size:
        17px;

    font-weight:
        900;
}


.card-description {

    margin:
        0 0 22px;

    color:
        #667085;

    font-size:
        11px;
}


/* =========================================================
   ERROR
========================================================= */

.error {

    padding:
        13px 15px;

    margin-bottom:
        20px;

    border-radius:
        10px;

    background:
        #fef2f2;

    border:
        1px solid #fecaca;

    color:
        #b91c1c;

    font-size:
        12px;

    font-weight:
        700;
}


/* =========================================================
   FORM
========================================================= */

.form-row {

    margin-bottom:
        18px;
}


.form-row label {

    display:
        block;

    margin-bottom:
        7px;

    color:
        #344054;

    font-size:
        11px;

    font-weight:
        800;
}


.form-row input,
.form-row select {

    width:
        100%;

    height:
        46px;

    padding:
        0 13px;

    border:
        1px solid #d0d5dd;

    border-radius:
        10px;

    background:
        white;

    color:
        #101828;

    font-size:
        13px;

    transition:
        .2s ease;
}


.form-row input:focus,
.form-row select:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.1);
}


.form-row small {

    display:
        block;

    margin-top:
        6px;

    color:
        #98a2b3;

    font-size:
        9px;
}


/* =========================================================
   GRID
========================================================= */

.form-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        15px;
}


/* =========================================================
   BUTTONS
========================================================= */

.form-actions {

    display:
        flex;

    gap:
        10px;

    margin-top:
        25px;
}


.btn {

    height:
        46px;

    padding:
        0 20px;

    border:
        none;

    border-radius:
        10px;

    font-size:
        12px;

    font-weight:
        900;

    cursor:
        pointer;

    text-decoration:
        none;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    transition:
        .2s ease;
}


.btn-primary {

    flex:
        1;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.2);
}


.btn-primary:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.28);
}


.btn-secondary {

    color:
        #475467;

    background:
        white;

    border:
        1px solid #d0d5dd;
}


.btn-secondary:hover {

    background:
        #f8fafc;

    text-decoration:
        none;
}


/* =========================================================
   PREVIEW
========================================================= */

.preview-card {

    background:
        linear-gradient(
            145deg,
            #0b2545,
            #123d70
        );

    color:
        white;

    border:
        none;

    overflow:
        hidden;

    position:
        relative;
}


.preview-card::before {

    content: "✈";

    position:
        absolute;

    right:
        -20px;

    top:
        -35px;

    font-size:
        130px;

    opacity:
        .07;

    transform:
        rotate(-15deg);
}


.preview-title {

    color:
        white;

    font-size:
        16px;

    font-weight:
        900;

    margin-bottom:
        18px;
}


.preview-item {

    padding:
        13px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);
}


.preview-item:last-child {

    border-bottom:
        none;
}


.preview-label {

    color:
        #a9c1e0;

    font-size:
        9px;

    font-weight:
        700;
}


.preview-value {

    margin-top:
        4px;

    color:
        white;

    font-size:
        14px;

    font-weight:
        900;
}


/* =========================================================
   SEAT PREVIEW
========================================================= */

.seat-preview {

    display:
        grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap:
        5px;

    margin-top:
        10px;
}


.mini-seat {

    height:
        25px;

    border-radius:
        5px 5px 3px 3px;

    background:
        rgba(255,255,255,.18);

    border:
        1px solid
        rgba(255,255,255,.18);

    font-size:
        7px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:850px) {

    .content {

        grid-template-columns:
            1fr;
    }


    .preview-card {

        order:
            -1;
    }

}


@media(max-width:600px) {

    .page {

        padding:
            25px 12px 50px;
    }


    .form-grid {

        grid-template-columns:
            1fr;
    }


    .form-actions {

        flex-direction:
            column;
    }


    .btn {

        width:
            100%;
    }

}

</style>

</head>


<body>


<header class="navbar">


    <a
        href="dashboard.php"
        class="logo"
    >

        <span class="logo-icon">
            ✈
        </span>

        Vektor<span>Sky</span>

    </a>


    <a
        href="aircraft.php"
        class="nav-back"
    >
        ← Aircraft Management
    </a>


</header>


<main class="page">


    <div class="page-header">

        <h1>
            Add New Aircraft
        </h1>

        <p>
            Register an aircraft and automatically generate its seat inventory.
        </p>

    </div>


    <div class="content">


        <!-- =================================================
             FORM
        ================================================= -->

        <section class="card">


            <h2 class="card-title">
                Aircraft Information
            </h2>


            <p class="card-description">

                Enter the aircraft details below.
                Seats will be generated automatically.

            </p>


            <?php if ($error): ?>

                <div class="error">

                    ⚠ <?= h($error) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                id="aircraftForm"
            >


                <!-- AIRCRAFT NUMBER -->

                <div class="form-row">

                    <label for="aircraft_no">
                        Aircraft Number
                    </label>

                    <input
                        type="text"
                        id="aircraft_no"
                        name="aircraft_no"
                        value="<?= h($aircraftNo) ?>"
                        placeholder="Example: VS-A320-04"
                        maxlength="20"
                        required
                    >

                    <small>
                        Use a unique aircraft registration number.
                    </small>

                </div>


                <!-- MODEL -->

                <div class="form-row">

                    <label for="model">
                        Aircraft Model
                    </label>

                    <select
                        id="model"
                        name="model"
                        required
                    >

                        <option value="">
                            Select aircraft model
                        </option>

                        <?php

                        $models = [

                            'Airbus A320',
                            'Airbus A321',
                            'Airbus A330',
                            'Airbus A350',
                            'Boeing 737',
                            'Boeing 737 MAX',
                            'Boeing 777',
                            'Boeing 787'

                        ];

                        ?>


                        <?php foreach (
                            $models
                            as $modelOption
                        ): ?>

                            <option
                                value="<?= h(
                                    $modelOption
                                ) ?>"
                                <?= $model ===
                                    $modelOption
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= h(
                                    $modelOption
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- AIRLINE -->

                <div class="form-row">

                    <label for="airline_name">
                        Airline Name
                    </label>

                    <input
                        type="text"
                        id="airline_name"
                        name="airline_name"
                        value="<?= h($airlineName) ?>"
                        placeholder="Example: VektorSky"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- CAPACITY -->

                <div class="form-row">

                    <label for="total_seats">
                        Total Seats
                    </label>

                    <input
                        type="number"
                        id="total_seats"
                        name="total_seats"
                        value="<?= h($totalSeats) ?>"
                        min="1"
                        max="500"
                        required
                    >

                    <small>
                        Seats will be generated automatically in 6-seat rows.
                    </small>

                </div>


                <!-- ACTIONS -->

                <div class="form-actions">


                    <a
                        href="aircraft.php"
                        class="
                            btn
                            btn-secondary
                        "
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        class="
                            btn
                            btn-primary
                        "
                    >
                        ✈ Create Aircraft
                    </button>


                </div>


            </form>


        </section>


        <!-- =================================================
             PREVIEW
        ================================================= -->

        <aside class="card preview-card">


            <div class="preview-title">

                Aircraft Preview

            </div>


            <div class="preview-item">

                <div class="preview-label">
                    AIRCRAFT
                </div>

                <div
                    class="preview-value"
                    id="previewAircraft"
                >
                    VS-A320-04
                </div>

            </div>


            <div class="preview-item">

                <div class="preview-label">
                    MODEL
                </div>

                <div
                    class="preview-value"
                    id="previewModel"
                >
                    Airbus A320
                </div>

            </div>


            <div class="preview-item">

                <div class="preview-label">
                    AIRLINE
                </div>

                <div
                    class="preview-value"
                    id="previewAirline"
                >
                    VektorSky
                </div>

            </div>


            <div class="preview-item">

                <div class="preview-label">
                    SEAT CAPACITY
                </div>

                <div
                    class="preview-value"
                    id="previewCapacity"
                >
                    180 seats
                </div>

            </div>


            <div class="preview-item">

                <div class="preview-label">
                    SEAT MAP
                </div>


                <div
                    class="seat-preview"
                    id="seatPreview"
                >

                    <?php for (
                        $i = 0;
                        $i < 12;
                        $i++
                    ): ?>

                        <div class="mini-seat">
                            💺
                        </div>

                    <?php endfor; ?>

                </div>

            </div>


            <div class="preview-item">

                <div class="preview-label">
                    AUTOMATIC GENERATION
                </div>

                <div
                    class="preview-value"
                    style="
                        font-size:11px;
                        line-height:1.5;
                    "
                >
                    Economy + Business
                    seats with automatic
                    pricing
                </div>

            </div>


        </aside>


    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| LIVE PREVIEW
|--------------------------------------------------------------------------
*/

const aircraftInput =
    document.getElementById(
        'aircraft_no'
    );


const modelInput =
    document.getElementById(
        'model'
    );


const airlineInput =
    document.getElementById(
        'airline_name'
    );


const seatsInput =
    document.getElementById(
        'total_seats'
    );


const previewAircraft =
    document.getElementById(
        'previewAircraft'
    );


const previewModel =
    document.getElementById(
        'previewModel'
    );


const previewAirline =
    document.getElementById(
        'previewAirline'
    );


const previewCapacity =
    document.getElementById(
        'previewCapacity'
    );


const seatPreview =
    document.getElementById(
        'seatPreview'
    );


/*
|--------------------------------------------------------------------------
| UPDATE PREVIEW
|--------------------------------------------------------------------------
*/

function updatePreview() {


    previewAircraft.textContent =
        aircraftInput.value ||
        'VS-A320-04';


    previewModel.textContent =
        modelInput.value ||
        'Airbus A320';


    previewAirline.textContent =
        airlineInput.value ||
        'VektorSky';


    const capacity =
        parseInt(
            seatsInput.value
        ) || 0;


    previewCapacity.textContent =
        capacity +
        ' seats';


    /*
    | Generate miniature preview
    */

    seatPreview.innerHTML = '';


    const previewCount =
        Math.min(
            capacity,
            24
        );


    for (
        let i = 0;
        i < previewCount;
        i++
    ) {

        const seat =
            document.createElement(
                'div'
            );

        seat.className =
            'mini-seat';

        seat.textContent =
            '💺';

        seatPreview.appendChild(
            seat
        );
    }

}


/*
|--------------------------------------------------------------------------
| EVENTS
|--------------------------------------------------------------------------
*/

aircraftInput.addEventListener(
    'input',
    updatePreview
);


modelInput.addEventListener(
    'change',
    updatePreview
);


airlineInput.addEventListener(
    'input',
    updatePreview
);


seatsInput.addEventListener(
    'input',
    updatePreview
);


/*
|--------------------------------------------------------------------------
| FORM VALIDATION
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'aircraftForm'
    )
    .addEventListener(
        'submit',
        function(event) {

            const capacity =
                parseInt(
                    seatsInput.value
                );


            if (
                capacity < 1 ||
                capacity > 500
            ) {

                event.preventDefault();

                alert(
                    'Total seats must be between 1 and 500.'
                );

                return;
            }


            const confirmed =
                confirm(
                    'Create this aircraft and generate ' +
                    capacity +
                    ' seats?'
                );


            if (!confirmed) {

                event.preventDefault();
            }

        }
    );


/*
|--------------------------------------------------------------------------
| INITIAL PREVIEW
|--------------------------------------------------------------------------
*/

updatePreview();

</script>


</body>

</html>