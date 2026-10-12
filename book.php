<?php

/*
|--------------------------------------------------------------------------
| VektorSky - Passenger Details
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Passenger ID
|--------------------------------------------------------------------------
*/

$passengerId =
    $_SESSION['passenger_id']
    ?? $_SESSION['user_id']
    ?? null;

if (!$passengerId) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Flight ID
|--------------------------------------------------------------------------
*/

$flightId =
    isset($_GET['flight_id'])
        ? (int) $_GET['flight_id']
        : 0;

if ($flightId <= 0) {

    die(
        '<h2 style="font-family:Arial;text-align:center;margin-top:80px;">
            Invalid flight selected.
            <br><br>
            <a href="index.php">Go back to flight search</a>
        </h2>'
    );
}


/*
|--------------------------------------------------------------------------
| Get Flight
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT

            f.flight_id,
            f.flight_number,
            f.airline_name,
            f.departure_time,
            f.arrival_time,
            f.fare,

            src.airport_code AS source_code,
            src.airport_name AS source_airport,
            src.city AS source_city,

            dst.airport_code AS destination_code,
            dst.airport_name AS destination_airport,
            dst.city AS destination_city,

            ac.aircraft_no,
            ac.model,
            ac.total_seats

        FROM flight f

        INNER JOIN airport src
            ON f.source_airport_id = src.airport_id

        INNER JOIN airport dst
            ON f.destination_airport_id = dst.airport_id

        INNER JOIN aircraft ac
            ON f.aircraft_id = ac.aircraft_id

        WHERE f.flight_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $flightId
    ]);

    $flight =
        $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        'Database error: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


if (!$flight) {

    die(
        '<h2 style="font-family:Arial;text-align:center;margin-top:80px;">
            Flight not found.
            <br><br>
            <a href="index.php">Go back to flight search</a>
        </h2>'
    );
}


/*
|--------------------------------------------------------------------------
| Get Passenger
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            passenger_id,
            name,
            email,
            phone,
            passport_no,
            date_of_birth,
            gender
        FROM passenger
        WHERE passenger_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $passengerId
    ]);

    $passenger =
        $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $passenger = [];
}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {

    function h($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Format Date / Time
|--------------------------------------------------------------------------
*/

$departureDateTime =
    new DateTime(
        $flight['departure_time']
    );

$arrivalDateTime =
    new DateTime(
        $flight['arrival_time']
    );


$departureDate =
    $departureDateTime->format(
        'd M Y'
    );

$arrivalDate =
    $arrivalDateTime->format(
        'd M Y'
    );

$departureTime =
    $departureDateTime->format(
        'h:i A'
    );

$arrivalTime =
    $arrivalDateTime->format(
        'h:i A'
    );


/*
|--------------------------------------------------------------------------
| Calculate Duration
|--------------------------------------------------------------------------
*/

$durationSeconds =
    $arrivalDateTime->getTimestamp()
    -
    $departureDateTime->getTimestamp();

$durationHours =
    floor(
        $durationSeconds / 3600
    );

$durationMinutes =
    floor(
        ($durationSeconds % 3600) / 60
    );

$duration =
    $durationHours . 'h '
    . $durationMinutes . 'm';


/*
|--------------------------------------------------------------------------
| Handle Passenger Form
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We do NOT send directly to payment.php.
|
| Passenger Details
|       ↓
| Seat Selection
|       ↓
| Payment
|
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name =
        trim(
            $_POST['name']
            ?? ''
        );

    $email =
        trim(
            $_POST['email']
            ?? ''
        );

    $phone =
        trim(
            $_POST['phone']
            ?? ''
        );

    $passportNo =
        trim(
            $_POST['passport_no']
            ?? ''
        );

    $dateOfBirth =
        trim(
            $_POST['date_of_birth']
            ?? ''
        );

    $gender =
        trim(
            $_POST['gender']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if (
        $name === ''
        ||
        $email === ''
        ||
        $phone === ''
    ) {

        $error =
            'Please fill in your name, email and phone number.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Store passenger details temporarily in session
        |--------------------------------------------------------------------------
        */

        $_SESSION['booking_passenger'] = [

            'name' =>
                $name,

            'email' =>
                $email,

            'phone' =>
                $phone,

            'passport_no' =>
                $passportNo,

            'date_of_birth' =>
                $dateOfBirth,

            'gender' =>
                $gender
        ];


        /*
        |--------------------------------------------------------------------------
        | Store flight ID
        |--------------------------------------------------------------------------
        */

        $_SESSION['booking_flight_id'] =
            $flightId;


        /*
        |--------------------------------------------------------------------------
        | Go to Seat Selection
        |--------------------------------------------------------------------------
        */

        header(
            'Location: seat_selection.php?flight_id='
            . $flightId
        );

        exit;
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
    VektorSky — Passenger Details
</title>


<style>

* {
    box-sizing:
        border-box;
}


body {

    margin:
        0;

    font-family:
        Inter,
        Arial,
        sans-serif;

    background:
        #f5f8fc;

    color:
        #0f172a;
}


.navbar {

    height:
        74px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        0 6%;

    background:
        white;

    border-bottom:
        1px solid #e2e8f0;
}


.logo {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    text-decoration:
        none;

    color:
        #0f172a;

    font-size:
        21px;

    font-weight:
        900;
}


.logo-icon {

    width:
        42px;

    height:
        42px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        13px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06a9dc
        );

    color:
        white;

    font-size:
        20px;
}


.logo span {
    color:
        #2563eb;
}


.back {

    text-decoration:
        none;

    color:
        #475569;

    font-size:
        12px;

    font-weight:
        800;
}


.page {

    max-width:
        1100px;

    margin:
        45px auto;

    padding:
        0 20px;
}


.page-title {

    margin-bottom:
        25px;
}


.page-title h1 {

    margin:
        0;

    font-size:
        30px;

    font-weight:
        900;
}


.page-title p {

    margin-top:
        7px;

    color:
        #64748b;

    font-size:
        13px;
}


.layout {

    display:
        grid;

    grid-template-columns:
        1fr 360px;

    gap:
        22px;

    align-items:
        start;
}


.card {

    background:
        white;

    border:
        1px solid #e2e8f0;

    border-radius:
        20px;

    padding:
        24px;

    box-shadow:
        0 15px 40px
        rgba(
            15,
            23,
            42,
            .06
        );
}


/* Flight */

.flight-card {

    margin-bottom:
        22px;
}


.flight-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding-bottom:
        18px;

    border-bottom:
        1px solid #e2e8f0;
}


.airline {

    font-size:
        14px;

    font-weight:
        900;
}


.flight-number {

    margin-top:
        5px;

    color:
        #64748b;

    font-size:
        11px;

    font-weight:
        700;
}


.price {

    color:
        #2563eb;

    font-size:
        23px;

    font-weight:
        900;

    text-align:
        right;
}


.price small {

    display:
        block;

    color:
        #94a3b8;

    font-size:
        9px;

    font-weight:
        600;
}


.route {

    display:
        grid;

    grid-template-columns:
        1fr 80px 1fr;

    align-items:
        center;

    margin-top:
        25px;
}


.city {

    font-size:
        20px;

    font-weight:
        900;
}


.code {

    margin-top:
        4px;

    color:
        #2563eb;

    font-size:
        12px;

    font-weight:
        900;
}


.time {

    margin-top:
        9px;

    font-size:
        13px;

    font-weight:
        800;
}


.date {

    margin-top:
        4px;

    color:
        #64748b;

    font-size:
        10px;
}


.route-line {

    position:
        relative;

    height:
        2px;

    background:
        #cbd5e1;
}


.route-line::after {

    content:
        '✈';

    position:
        absolute;

    left:
        50%;

    top:
        50%;

    transform:
        translate(
            -50%,
            -50%
        );

    width:
        30px;

    height:
        30px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        50%;

    background:
        #eff6ff;

    color:
        #2563eb;

    font-size:
        13px;
}


/* Form */

.form-title {

    margin-bottom:
        20px;

    font-size:
        18px;

    font-weight:
        900;
}


.form-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        15px;
}


.form-group {

    display:
        flex;

    flex-direction:
        column;

    gap:
        7px;
}


.form-group.full {

    grid-column:
        1 / -1;
}


.form-group label {

    color:
        #475569;

    font-size:
        10px;

    font-weight:
        900;

    letter-spacing:
        .4px;
}


.form-group input,
.form-group select {

    width:
        100%;

    height:
        48px;

    padding:
        0 13px;

    border:
        1px solid #dbe3ef;

    border-radius:
        11px;

    background:
        white;

    outline:
        none;

    color:
        #0f172a;

    font-size:
        12px;

    font-weight:
        600;
}


.form-group input:focus,
.form-group select:focus {

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(
            37,
            99,
            235,
            .08
        );
}


/* Error */

.error {

    margin-bottom:
        18px;

    padding:
        13px 15px;

    border-radius:
        10px;

    background:
        #fef2f2;

    border:
        1px solid #fecaca;

    color:
        #b91c1c;

    font-size:
        11px;

    font-weight:
        700;
}


/* Continue */

.continue-button {

    width:
        100%;

    height:
        52px;

    margin-top:
        20px;

    border:
        none;

    border-radius:
        12px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color:
        white;

    font-size:
        12px;

    font-weight:
        900;

    cursor:
        pointer;

    transition:
        .2s;
}


.continue-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(
            37,
            99,
            235,
            .25
        );
}


/* Summary */

.summary-title {

    margin-bottom:
        18px;

    font-size:
        16px;

    font-weight:
        900;
}


.summary-row {

    display:
        flex;

    justify-content:
        space-between;

    gap:
        15px;

    padding:
        11px 0;

    border-bottom:
        1px solid #f1f5f9;

    font-size:
        11px;
}


.summary-row span:first-child {

    color:
        #64748b;
}


.summary-row span:last-child {

    font-weight:
        800;

    text-align:
        right;
}


.total {

    display:
        flex;

    justify-content:
        space-between;

    margin-top:
        18px;

    padding-top:
        18px;

    border-top:
        1px solid #e2e8f0;

    font-size:
        16px;

    font-weight:
        900;
}


@media(max-width:800px) {

    .layout {

        grid-template-columns:
            1fr;
    }

}


@media(max-width:600px) {

    .form-grid {

        grid-template-columns:
            1fr;
    }

    .form-group.full {

        grid-column:
            auto;
    }

    .route {

        grid-template-columns:
            1fr 45px 1fr;
    }

    .city {

        font-size:
            17px;
    }

}

</style>

</head>


<body>


<header class="navbar">

    <a
        href="index.php"
        class="logo"
    >

        <span class="logo-icon">
            ✈
        </span>

        Vektor<span>Sky</span>

    </a>


    <a
        href="search_results.php"
        class="back"
    >
        ← Back to flights
    </a>

</header>


<main class="page">


    <div class="page-title">

        <h1>
            Passenger Details
        </h1>

        <p>
            Enter the passenger information for your booking.
        </p>

    </div>


    <?php if (!empty($error)): ?>

        <div class="error">

            <?= h($error) ?>

        </div>

    <?php endif; ?>


    <div class="layout">


        <!-- LEFT -->

        <div>


            <!-- Flight -->

            <div class="card flight-card">


                <div class="flight-header">


                    <div>

                        <div class="airline">

                            <?= h(
                                $flight['airline_name']
                            ) ?>

                        </div>


                        <div class="flight-number">

                            Flight

                            <?= h(
                                $flight['flight_number']
                            ) ?>

                            ·

                            <?= h(
                                $flight['model']
                            ) ?>

                        </div>

                    </div>


                    <div class="price">

                        ₹<?= number_format(
                            (float)$flight['fare']
                        ) ?>

                        <small>
                            per passenger
                        </small>

                    </div>


                </div>


                <div class="route">


                    <div>

                        <div class="city">

                            <?= h(
                                $flight['source_city']
                            ) ?>

                        </div>


                        <div class="code">

                            <?= h(
                                $flight['source_code']
                            ) ?>

                        </div>


                        <div class="time">

                            <?= h(
                                $departureTime
                            ) ?>

                        </div>


                        <div class="date">

                            <?= h(
                                $departureDate
                            ) ?>

                        </div>

                    </div>


                    <div class="route-line"></div>


                    <div
                        style="text-align:right;"
                    >

                        <div class="city">

                            <?= h(
                                $flight['destination_city']
                            ) ?>

                        </div>


                        <div class="code">

                            <?= h(
                                $flight['destination_code']
                            ) ?>

                        </div>


                        <div class="time">

                            <?= h(
                                $arrivalTime
                            ) ?>

                        </div>


                        <div class="date">

                            <?= h(
                                $arrivalDate
                            ) ?>

                        </div>

                    </div>


                </div>

            </div>


            <!-- Passenger Form -->

            <div class="card">


                <div class="form-title">
                    Passenger 1
                </div>


                <form
                    method="POST"
                    action="book.php?flight_id=<?= (int)$flightId ?>"
                    id="passengerForm"
                >


                    <input
                        type="hidden"
                        name="flight_id"
                        value="<?= h(
                            $flight['flight_id']
                        ) ?>"
                    >


                    <input
                        type="hidden"
                        name="passenger_id"
                        value="<?= h(
                            $passengerId
                        ) ?>"
                    >


                    <div class="form-grid">


                        <div class="form-group full">

                            <label>
                                FULL NAME
                            </label>

                            <input
                                type="text"
                                name="name"
                                placeholder="Enter passenger name"
                                value="<?= h(
                                    $passenger['name']
                                    ?? ''
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                EMAIL
                            </label>

                            <input
                                type="email"
                                name="email"
                                placeholder="Enter email address"
                                value="<?= h(
                                    $passenger['email']
                                    ?? ''
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                PHONE
                            </label>

                            <input
                                type="tel"
                                name="phone"
                                placeholder="Enter phone number"
                                value="<?= h(
                                    $passenger['phone']
                                    ?? ''
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                PASSPORT NUMBER
                            </label>

                            <input
                                type="text"
                                name="passport_no"
                                placeholder="Enter passport number"
                                value="<?= h(
                                    $passenger['passport_no']
                                    ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                DATE OF BIRTH
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                                value="<?= h(
                                    $passenger['date_of_birth']
                                    ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                GENDER
                            </label>

                            <select name="gender">

                                <option value="">
                                    Select Gender
                                </option>

                                <option
                                    value="Male"
                                    <?= (
                                        ($passenger['gender'] ?? '')
                                        === 'Male'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= (
                                        ($passenger['gender'] ?? '')
                                        === 'Female'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Female
                                </option>

                                <option
                                    value="Other"
                                    <?= (
                                        ($passenger['gender'] ?? '')
                                        === 'Other'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Other
                                </option>

                            </select>

                        </div>


                    </div>


                    <button
                        type="submit"
                        class="continue-button"
                    >

                        Continue to Seat Selection →

                    </button>


                </form>


            </div>


        </div>


        <!-- RIGHT SUMMARY -->

        <aside class="card">


            <div class="summary-title">
                Booking Summary
            </div>


            <div class="summary-row">

                <span>
                    Flight
                </span>

                <span>

                    <?= h(
                        $flight['flight_number']
                    ) ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Route
                </span>

                <span>

                    <?= h(
                        $flight['source_code']
                    ) ?>

                    →

                    <?= h(
                        $flight['destination_code']
                    ) ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Date
                </span>

                <span>

                    <?= h(
                        $departureDate
                    ) ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Aircraft
                </span>

                <span>

                    <?= h(
                        $flight['model']
                    ) ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Passenger
                </span>

                <span>
                    1
                </span>

            </div>


            <div class="total">

                <span>
                    Flight Fare
                </span>

                <span>

                    ₹<?= number_format(
                        (float)$flight['fare']
                    ) ?>

                </span>

            </div>


        </aside>


    </div>

</main>


</body>

</html>