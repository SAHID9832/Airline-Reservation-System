<?php

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/includes/functions.php';


// ============================================================
// REDIRECT IF ALREADY LOGGED IN
// ============================================================

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}


$error = '';
$success = '';

$name = '';
$email = '';
$phone = '';
$passport_no = '';
$date_of_birth = '';
$gender = '';


// ============================================================
// FORM SUBMISSION
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $passport_no = trim($_POST['passport_no'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';


    // ========================================================
    // VALIDATION
    // ========================================================

    if ($name === '') {

        $error = 'Please enter your full name.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif ($password === '') {

        $error = 'Please enter a password.';

    } elseif (strlen($password) < 8) {

        $error = 'Password must contain at least 8 characters.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {

        $error = 'Please enter a valid phone number.';

    } else {

        try {

            // =================================================
            // CHECK EXISTING EMAIL
            // =================================================

            $stmt = $pdo->prepare("
                SELECT passenger_id
                FROM passenger
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            if ($stmt->fetch()) {

                $error =
                    'An account with this email already exists.';

            } else {

                // =============================================
                // PASSWORD HASH
                // =============================================

                $password_hash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                // =============================================
                // CREATE PASSENGER
                // =============================================

                $stmt = $pdo->prepare("
                    INSERT INTO passenger
                    (
                        name,
                        email,
                        password_hash,
                        phone,
                        passport_no,
                        date_of_birth,
                        gender
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?
                    )
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $password_hash,
                    $phone !== '' ? $phone : null,
                    $passport_no !== '' ? $passport_no : null,
                    $date_of_birth !== '' ? $date_of_birth : null,
                    $gender !== '' ? $gender : null
                ]);


                // =============================================
                // SUCCESS
                // =============================================

                $success =
                    'Account created successfully. You can now sign in.';


                // Clear form

                $name = '';
                $email = '';
                $phone = '';
                $passport_no = '';
                $date_of_birth = '';
                $gender = '';

            }

        } catch (PDOException $e) {

            error_log(
                'VektorSky REGISTER ERROR: ' .
                $e->getMessage()
            );

            $error =
                'Unable to create your account right now.';

        }

    }

}


$pageTitle = 'Create Account';

require_once __DIR__ . '/includes/header.php';

?>


<style>

.vs-register-page {

    min-height:
        calc(100vh - 76px);

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    padding:
        45px 20px;

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #f8fafc,
            #ecfeff
        );

}


.vs-register-card {

    width:
        min(100%, 650px);

    background:
        rgba(255,255,255,.97);

    border:
        1px solid #e2e8f0;

    border-radius:
        24px;

    padding:
        38px;

    box-shadow:
        0 25px 70px
        rgba(15,23,42,.12);

    animation:
        registerAppear .5s ease;

}


@keyframes registerAppear {

    from {

        opacity: 0;

        transform:
            translateY(20px);

    }

    to {

        opacity: 1;

        transform:
            translateY(0);

    }

}


.vs-register-brand {

    text-align:
        center;

    margin-bottom:
        28px;

}


.vs-register-logo {

    width:
        62px;

    height:
        62px;

    margin:
        0 auto 15px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        18px;

    color:
        white;

    font-size:
        28px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.25);

}


.vs-register-brand h1 {

    margin:
        0;

    font-size:
        28px;

    font-weight:
        800;

}


.vs-register-brand h1 span {

    color:
        #2563eb;

}


.vs-register-brand p {

    color:
        #64748b;

    margin:
        8px 0 0;

}


.vs-register-error {

    padding:
        13px 15px;

    margin-bottom:
        18px;

    border-radius:
        12px;

    background:
        #fef2f2;

    border:
        1px solid #fecaca;

    color:
        #b91c1c;

    font-size:
        13px;

    font-weight:
        600;

}


.vs-register-success {

    padding:
        13px 15px;

    margin-bottom:
        18px;

    border-radius:
        12px;

    background:
        #f0fdf4;

    border:
        1px solid #bbf7d0;

    color:
        #15803d;

    font-size:
        13px;

    font-weight:
        600;

}


.vs-register-form {

    display:
        flex;

    flex-direction:
        column;

    gap:
        18px;

}


.vs-register-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        18px;

}


.vs-register-group {

    display:
        flex;

    flex-direction:
        column;

    gap:
        7px;

}


.vs-register-group.full {

    grid-column:
        1 / -1;

}


.vs-register-group label {

    font-size:
        13px;

    font-weight:
        700;

    color:
        #334155;

}


.vs-register-group input,
.vs-register-group select {

    width:
        100%;

    height:
        50px;

    padding:
        0 14px;

    border:
        1px solid #cbd5e1;

    border-radius:
        12px;

    outline:
        none;

    background:
        white;

    font-family:
        inherit;

    font-size:
        14px;

}


.vs-register-group input:focus,
.vs-register-group select:focus {

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);

}


.vs-register-submit {

    width:
        100%;

    height:
        52px;

    border:
        none;

    border-radius:
        13px;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    font-size:
        15px;

    font-weight:
        800;

    cursor:
        pointer;

    box-shadow:
        0 10px 25px
        rgba(37,99,235,.22);

    transition:
        .2s;

}


.vs-register-submit:hover {

    transform:
        translateY(-2px);

}


.vs-login-link {

    text-align:
        center;

    margin-top:
        23px;

    padding-top:
        21px;

    border-top:
        1px solid #e2e8f0;

    color:
        #64748b;

}


.vs-login-link a {

    color:
        #2563eb;

    font-weight:
        800;

    text-decoration:
        none;

}


@media (max-width: 600px) {

    .vs-register-card {

        padding:
            27px 20px;

    }

    .vs-register-grid {

        grid-template-columns:
            1fr;

    }

    .vs-register-group.full {

        grid-column:
            auto;

    }

}

</style>


<section class="vs-register-page">

    <div class="vs-register-card">


        <div class="vs-register-brand">

            <div class="vs-register-logo">
                ✈
            </div>

            <h1>
                Join
                <span>VektorSky</span>
            </h1>

            <p>
                Create your account and start exploring.
            </p>

        </div>


        <?php if ($error !== ''): ?>

            <div class="vs-register-error">

                <?= h($error) ?>

            </div>

        <?php endif; ?>


        <?php if ($success !== ''): ?>

            <div class="vs-register-success">

                <?= h($success) ?>

                <br><br>

                <a href="login.php">
                    Go to Login →
                </a>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="register.php"
            class="vs-register-form"
            autocomplete="on"
        >


            <div class="vs-register-grid">


                <!-- NAME -->

                <div class="vs-register-group full">

                    <label for="name">
                        Full Name *
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= h($name) ?>"
                        placeholder="Enter your full name"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="vs-register-group">

                    <label for="email">
                        Email Address *
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= h($email) ?>"
                        placeholder="you@example.com"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="vs-register-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?= h($phone) ?>"
                        placeholder="9876543210"
                    >

                </div>


                <!-- PASSWORD -->

                <div class="vs-register-group">

                    <label for="password">
                        Password *
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 8 characters"
                        minlength="8"
                        required
                    >

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="vs-register-group">

                    <label for="confirm_password">
                        Confirm Password *
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter password"
                        minlength="8"
                        required
                    >

                </div>


                <!-- PASSPORT -->

                <div class="vs-register-group">

                    <label for="passport_no">
                        Passport Number
                    </label>

                    <input
                        type="text"
                        id="passport_no"
                        name="passport_no"
                        value="<?= h($passport_no) ?>"
                        placeholder="Optional"
                    >

                </div>


                <!-- DATE OF BIRTH -->

                <div class="vs-register-group">

                    <label for="date_of_birth">
                        Date of Birth
                    </label>

                    <input
                        type="date"
                        id="date_of_birth"
                        name="date_of_birth"
                        value="<?= h($date_of_birth) ?>"
                    >

                </div>


                <!-- GENDER -->

                <div class="vs-register-group">

                    <label for="gender">
                        Gender
                    </label>

                    <select
                        id="gender"
                        name="gender"
                    >

                        <option value="">
                            Select gender
                        </option>

                        <option
                            value="Male"
                            <?= $gender === 'Male' ? 'selected' : '' ?>
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            <?= $gender === 'Female' ? 'selected' : '' ?>
                        >
                            Female
                        </option>

                        <option
                            value="Other"
                            <?= $gender === 'Other' ? 'selected' : '' ?>
                        >
                            Other
                        </option>

                    </select>

                </div>


            </div>


            <button
                type="submit"
                class="vs-register-submit"
            >
                Create VektorSky Account
            </button>


        </form>


        <div class="vs-login-link">

            Already have an account?

            <a href="login.php">
                Sign In
            </a>

        </div>


    </div>

</section>


<?php

require_once __DIR__ . '/includes/footer.php';

?>