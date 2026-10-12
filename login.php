<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| REDIRECT ALREADY LOGGED-IN PASSENGERS
|--------------------------------------------------------------------------
*/

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';
$email = '';


/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim(
        $_POST['email'] ?? ''
    );

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($email === '' || $password === '') {

        $error =
            'Please enter your email and password.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | FIND PASSENGER
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    passenger_id,
                    name,
                    email,
                    password_hash,
                    phone,
                    passport_no,
                    date_of_birth,
                    gender
                FROM passenger
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([
                $email
            ]);

            $passenger =
                $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | CHECK USER
            |--------------------------------------------------------------------------
            */

            if (!$passenger) {

                $error =
                    'Invalid email or password.';

            } elseif (
                empty($passenger['password_hash'])
                ||
                !password_verify(
                    $password,
                    $passenger['password_hash']
                )
            ) {

                $error =
                    'Invalid email or password.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | LOGIN SUCCESS
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['passenger_id'] =
                    (int)$passenger['passenger_id'];

                $_SESSION['passenger_name'] =
                    $passenger['name'];

                $_SESSION['passenger_email'] =
                    $passenger['email'];

                $_SESSION['passenger_phone'] =
                    $passenger['phone'] ?? '';

                $_SESSION['passenger_passport'] =
                    $passenger['passport_no'] ?? '';

                $_SESSION['passenger_dob'] =
                    $passenger['date_of_birth'] ?? '';

                $_SESSION['passenger_gender'] =
                    $passenger['gender'] ?? '';


                /*
                |--------------------------------------------------------------------------
                | REDIRECT
                |--------------------------------------------------------------------------
                */

                header('Location: index.php');
                exit;
            }

        } catch (PDOException $e) {

            error_log(
                'VektorSky LOGIN ERROR: '
                . $e->getMessage()
            );

            $error =
                'Unable to process your login right now.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$page_title = 'Login';
$pageTitle = 'Login';

require_once __DIR__ . '/includes/header.php';

?>


<style>

/* =========================================================
   LOGIN PAGE
========================================================= */

.login-page {

    min-height:
        calc(100vh - 120px);

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        50px 20px;

    position:
        relative;

    overflow:
        hidden;

    background:
        linear-gradient(
            135deg,
            #f5f9ff,
            #eef6ff,
            #f8fbff
        );
}


/* =========================================================
   ANIMATED BACKGROUND
========================================================= */

.login-page::before {

    content: "";

    position: absolute;

    width: 500px;

    height: 500px;

    top: -220px;

    right: -180px;

    border-radius: 50%;

    background:
        rgba(
            37,
            99,
            235,
            .08
        );

    animation:
        floatCircle 7s
        ease-in-out
        infinite;
}


.login-page::after {

    content: "";

    position: absolute;

    width: 400px;

    height: 400px;

    bottom: -200px;

    left: -160px;

    border-radius: 50%;

    background:
        rgba(
            14,
            165,
            233,
            .07
        );

    animation:
        floatCircle 9s
        ease-in-out
        infinite reverse;
}


@keyframes floatCircle {

    0%,
    100% {

        transform:
            translate(
                0,
                0
            );
    }

    50% {

        transform:
            translate(
                20px,
                -20px
            );
    }
}


/* =========================================================
   LOGIN CARD
========================================================= */

.login-card {

    width: 100%;

    max-width: 430px;

    position: relative;

    z-index: 2;

    background:
        rgba(
            255,
            255,
            255,
            .92
        );

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .8
        );

    border-radius: 24px;

    padding: 34px;

    box-shadow:
        0 25px 70px
        rgba(
            11,
            37,
            69,
            .13
        );

    animation:
        cardEnter .5s
        ease both;
}


@keyframes cardEnter {

    from {

        opacity: 0;

        transform:
            translateY(25px)
            scale(.98);
    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}


/* =========================================================
   LOGO
========================================================= */

.login-logo {

    width: 62px;

    height: 62px;

    margin:
        0 auto 18px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #0ea5e9
        );

    color: white;

    font-size: 28px;

    box-shadow:
        0 10px 25px
        rgba(
            37,
            99,
            235,
            .25
        );

    animation:
        logoFloat 3s
        ease-in-out
        infinite;
}


@keyframes logoFloat {

    0%,
    100% {

        transform:
            translateY(0)
            rotate(0deg);
    }

    50% {

        transform:
            translateY(-5px)
            rotate(-2deg);
    }
}


/* =========================================================
   TITLE
========================================================= */

.login-title {

    text-align: center;

    margin-bottom: 28px;
}


.login-title h1 {

    margin: 0;

    color: #0b2545;

    font-size: 27px;

    font-weight: 900;

    letter-spacing: -.5px;
}


.login-title p {

    margin:
        8px 0 0;

    color: #667085;

    font-size: 12px;

    line-height: 1.5;
}


/* =========================================================
   ERROR
========================================================= */

.login-error {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    padding:
        12px 14px;

    margin-bottom: 18px;

    border-radius: 11px;

    background: #fef3f2;

    border:
        1px solid
        #fecdca;

    color: #b42318;

    font-size: 11px;

    font-weight: 700;

    animation:
        errorShake .3s
        ease;
}


@keyframes errorShake {

    0%,
    100% {

        transform:
            translateX(0);
    }

    25% {

        transform:
            translateX(-4px);
    }

    75% {

        transform:
            translateX(4px);
    }
}


/* =========================================================
   FORM
========================================================= */

.login-form {

    display: flex;

    flex-direction: column;

    gap: 18px;
}


/* =========================================================
   FIELD
========================================================= */

.form-group {

    position: relative;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #344054;

    font-size: 11px;

    font-weight: 800;
}


.input-wrapper {

    position: relative;
}


.input-icon {

    position: absolute;

    left: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #98a2b3;

    font-size: 15px;

    pointer-events: none;

    transition: .2s ease;
}


.form-control {

    width: 100%;

    box-sizing: border-box;

    padding:
        13px
        14px
        13px
        42px;

    border:
        1px solid
        #d0d5dd;

    border-radius: 11px;

    background:
        rgba(
            255,
            255,
            255,
            .85
        );

    color: #101828;

    font-family: inherit;

    font-size: 12px;

    outline: none;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}


.form-control:hover {

    border-color: #98a2b3;
}


.form-control:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(
            37,
            99,
            235,
            .10
        );

    transform:
        translateY(-1px);
}


/* =========================================================
   PASSWORD
========================================================= */

.password-wrapper {

    position: relative;
}


.password-wrapper .input-icon {

    z-index: 2;
}


.password-toggle {

    position: absolute;

    right: 12px;

    top: 50%;

    transform:
        translateY(-50%);

    border: none;

    background: transparent;

    color: #667085;

    cursor: pointer;

    font-size: 14px;

    padding: 4px;

    z-index: 3;
}


.password-toggle:hover {

    color: #2563eb;
}


/* =========================================================
   FORGOT PASSWORD
========================================================= */

.login-options {

    display: flex;

    justify-content: flex-end;

    margin-top: -8px;
}


.login-options a {

    color: #2563eb;

    font-size: 10px;

    font-weight: 800;

    text-decoration: none;

    transition: .2s ease;
}


.login-options a:hover {

    color: #1d4ed8;

    text-decoration: underline;
}


/* =========================================================
   SUBMIT
========================================================= */

.login-button {

    width: 100%;

    margin-top: 3px;

    padding: 14px;

    border: none;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #0ea5e9
        );

    color: white;

    font-family: inherit;

    font-size: 12px;

    font-weight: 900;

    cursor: pointer;

    box-shadow:
        0 10px 25px
        rgba(
            37,
            99,
            235,
            .22
        );

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.login-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 14px 30px
        rgba(
            37,
            99,
            235,
            .28
        );
}


.login-button:active {

    transform:
        translateY(0);
}


/* =========================================================
   REGISTER
========================================================= */

.register-link {

    margin-top: 22px;

    padding-top: 20px;

    border-top:
        1px solid
        #eaecf0;

    text-align: center;

    color: #667085;

    font-size: 11px;
}


.register-link a {

    color: #2563eb;

    font-weight: 900;

    text-decoration: none;
}


.register-link a:hover {

    text-decoration: underline;
}


/* =========================================================
   HOME
========================================================= */

.home-link {

    display: block;

    margin-top: 15px;

    text-align: center;

    color: #98a2b3;

    font-size: 10px;

    text-decoration: none;
}


.home-link:hover {

    color: #2563eb;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 500px) {

    .login-page {

        padding:
            30px 14px;
    }


    .login-card {

        padding:
            27px 21px;

        border-radius:
            20px;
    }


    .login-title h1 {

        font-size: 24px;
    }

}

</style>


<main class="login-page">


    <div class="login-card">


        <!-- LOGO -->

        <div class="login-logo">
            ✈
        </div>


        <!-- TITLE -->

        <div class="login-title">

            <h1>
                Welcome to VektorSky
            </h1>

            <p>
                Sign in and continue your journey.
            </p>

        </div>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="login-error">

                <span>
                    ⚠️
                </span>

                <span>
                    <?= h($error) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- LOGIN FORM -->

        <form
            method="POST"
            action=""
            class="login-form"
            autocomplete="on"
        >


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        ✉
                    </span>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter your email"
                        value="<?= h($email) ?>"
                        autocomplete="email"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="password-wrapper">

                    <span class="input-icon">
                        🔒
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        style="padding-right:45px;"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                        aria-label="Show password"
                    >
                        👁
                    </button>

                </div>

            </div>


            <!-- FORGOT PASSWORD -->

            <div class="login-options">

                <a href="forgot_password.php">
                    Forgot Password?
                </a>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="login-button"
            >
                Sign In ✈
            </button>


        </form>


        <!-- REGISTER -->

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Create Account
            </a>

        </div>


        <!-- HOME -->

        <a
            href="index.php"
            class="home-link"
        >
            ← Back to VektorSky
        </a>


    </div>

</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const password =
            document.getElementById(
                'password'
            );

        const toggle =
            document.getElementById(
                'passwordToggle'
            );


        if (
            password &&
            toggle
        ) {

            toggle.addEventListener(
                'click',
                function () {

                    if (
                        password.type ===
                        'password'
                    ) {

                        password.type =
                            'text';

                        toggle.textContent =
                            '🙈';

                        toggle.setAttribute(
                            'aria-label',
                            'Hide password'
                        );

                    } else {

                        password.type =
                            'password';

                        toggle.textContent =
                            '👁';

                        toggle.setAttribute(
                            'aria-label',
                            'Show password'
                        );
                    }

                }
            );

        }

    }
);

</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>