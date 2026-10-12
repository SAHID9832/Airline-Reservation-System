<?php

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['is_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Demo Admin Login
    |--------------------------------------------------------------------------
    */

    if ($username === 'admin' && $password === 'admin123') {

        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = $username;

        header('Location: dashboard.php');
        exit;

    } else {

        $error = 'Invalid username or password.';
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

<title>VektorSky | Admin Portal</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100%;
}

body {
    font-family:
        Inter,
        "Segoe UI",
        Arial,
        sans-serif;

    color: #0b2545;

    overflow-x: hidden;
}


/* =========================================================
   MAIN BACKGROUND
========================================================= */

.login-page {

    min-height: 100vh;

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #f7fbff 0%,
            #eaf3ff 48%,
            #f8fcff 100%
        );

}


/* =========================================================
   ANIMATED BLOBS
========================================================= */

.blob {

    position: absolute;

    border-radius: 50%;

    filter: blur(2px);

    pointer-events: none;

}


.blob-1 {

    width: 500px;

    height: 500px;

    left: -200px;

    top: 100px;

    background:
        radial-gradient(
            circle,
            rgba(37,99,235,.20),
            transparent 68%
        );

    animation:
        blobMove1 12s ease-in-out infinite;
}


.blob-2 {

    width: 600px;

    height: 600px;

    right: -250px;

    bottom: -250px;

    background:
        radial-gradient(
            circle,
            rgba(6,182,212,.18),
            transparent 68%
        );

    animation:
        blobMove2 14s ease-in-out infinite;
}


.blob-3 {

    width: 300px;

    height: 300px;

    right: 20%;

    top: -180px;

    background:
        radial-gradient(
            circle,
            rgba(99,102,241,.12),
            transparent 70%
        );

    animation:
        blobMove3 10s ease-in-out infinite;
}


@keyframes blobMove1 {

    0%,
    100% {
        transform:
            translate(0,0)
            scale(1);
    }

    50% {
        transform:
            translate(80px,-50px)
            scale(1.12);
    }
}


@keyframes blobMove2 {

    0%,
    100% {
        transform:
            translate(0,0)
            scale(1);
    }

    50% {
        transform:
            translate(-70px,-60px)
            scale(1.1);
    }
}


@keyframes blobMove3 {

    0%,
    100% {
        transform:
            translate(0,0);
    }

    50% {
        transform:
            translate(-80px,80px);
    }
}


/* =========================================================
   GRID
========================================================= */

.background-grid {

    position: absolute;

    inset: 0;

    opacity: .35;

    background-image:

        linear-gradient(
            rgba(37,99,235,.06) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(37,99,235,.06) 1px,
            transparent 1px
        );

    background-size: 45px 45px;

    mask-image:
        linear-gradient(
            to bottom,
            black,
            transparent 80%
        );

    pointer-events: none;
}


/* =========================================================
   HEADER
========================================================= */

.topbar {

    height: 72px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 5%;

    background:
        rgba(255,255,255,.78);

    border-bottom:
        1px solid
        rgba(15,23,42,.07);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    position: relative;

    z-index: 10;
}


.logo {

    display: flex;

    align-items: center;

    gap: 11px;

    text-decoration: none;

    color: #0b2545;
}


.logo-icon {

    width: 42px;

    height: 42px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    color: white;

    font-size: 20px;

    box-shadow:
        0 9px 24px
        rgba(37,99,235,.25);

    animation:
        logoFloat 3s ease-in-out infinite;
}


@keyframes logoFloat {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-3px);
    }
}


.logo-text {

    font-size: 21px;

    font-weight: 900;

    letter-spacing: -.7px;
}


.logo-text span {
    color: #2563eb;
}


.topbar-right {

    display: flex;

    align-items: center;

    gap: 12px;
}


.portal-badge {

    padding:
        8px 13px;

    border-radius: 50px;

    background: #eff6ff;

    border:
        1px solid #dbeafe;

    color: #2563eb;

    font-size: 10px;

    font-weight: 900;

    letter-spacing: .6px;
}


.home-link {

    color: #475467;

    text-decoration: none;

    font-size: 11px;

    font-weight: 800;

    padding:
        9px 13px;

    border-radius: 8px;

    transition: .2s ease;
}


.home-link:hover {

    background: #eff6ff;

    color: #2563eb;
}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    min-height:
        calc(100vh - 72px);

    max-width: 1200px;

    margin: auto;

    padding:
        50px 30px;

    display: grid;

    grid-template-columns:
        1fr 440px;

    align-items: center;

    gap: 90px;

    position: relative;

    z-index: 2;
}


/* =========================================================
   LEFT SIDE
========================================================= */

.hero {

    animation:
        heroEnter .8s ease both;
}


@keyframes heroEnter {

    from {
        opacity: 0;

        transform:
            translateX(-35px);
    }

    to {
        opacity: 1;

        transform:
            translateX(0);
    }
}


.status-pill {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        8px 13px;

    border-radius: 50px;

    background:
        rgba(37,99,235,.08);

    border:
        1px solid
        rgba(37,99,235,.12);

    color: #2563eb;

    font-size: 10px;

    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}


.status-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: #22c55e;

    box-shadow:
        0 0 0 5px
        rgba(34,197,94,.10);

    animation:
        statusPulse 2s infinite;
}


@keyframes statusPulse {

    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: .55;
        transform: scale(1.3);
    }
}


.hero h1 {

    margin:
        20px 0 0;

    font-size:
        clamp(
            45px,
            5vw,
            68px
        );

    line-height: .98;

    letter-spacing:
        -3px;

    color: #0b2545;
}


.hero h1 span {

    color: #2563eb;
}


.hero-description {

    max-width: 560px;

    margin:
        22px 0 0;

    color: #667085;

    font-size: 15px;

    line-height: 1.8;
}


/* =========================================================
   FEATURES
========================================================= */

.features {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 12px;

    margin-top: 30px;
}


.feature {

    padding: 15px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.66);

    border:
        1px solid
        rgba(15,23,42,.07);

    backdrop-filter:
        blur(10px);

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.feature:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 15px 30px
        rgba(11,37,69,.09);
}


.feature-icon {

    font-size: 20px;

    margin-bottom: 8px;
}


.feature-title {

    font-size: 11px;

    font-weight: 900;

    color: #0b2545;
}


.feature-text {

    margin-top: 4px;

    color: #667085;

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   PLANE TRACK
========================================================= */

.plane-track {

    position: relative;

    height: 75px;

    margin-top: 25px;

    overflow: hidden;
}


.plane-line {

    position: absolute;

    left: 0;

    right: 0;

    top: 39px;

    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(37,99,235,.3),
            transparent
        );
}


.plane {

    position: absolute;

    left: -50px;

    top: 23px;

    color: #2563eb;

    font-size: 24px;

    filter:
        drop-shadow(
            0 6px 8px
            rgba(37,99,235,.25)
        );

    animation:
        planeFly 7s linear infinite;
}


@keyframes planeFly {

    0% {
        left: -50px;
        transform:
            translateY(8px)
            rotate(-4deg);
    }

    45% {
        transform:
            translateY(-7px)
            rotate(2deg);
    }

    100% {
        left: calc(100% + 50px);
        transform:
            translateY(8px)
            rotate(-2deg);
    }
}


/* =========================================================
   LOGIN CARD
========================================================= */

.login-card {

    position: relative;

    background:
        rgba(255,255,255,.86);

    border:
        1px solid
        rgba(255,255,255,.9);

    border-radius: 25px;

    padding: 34px;

    box-shadow:

        0 30px 70px
        rgba(11,37,69,.14),

        0 8px 25px
        rgba(37,99,235,.06);

    backdrop-filter:
        blur(22px);

    -webkit-backdrop-filter:
        blur(22px);

    animation:
        cardEnter .85s
        cubic-bezier(.2,.8,.2,1)
        both;

    overflow: hidden;
}


@keyframes cardEnter {

    from {

        opacity: 0;

        transform:
            translateY(35px)
            scale(.96);
    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}


.login-card::before {

    content: "";

    position: absolute;

    left: 0;

    top: 0;

    right: 0;

    height: 4px;

    background:
        linear-gradient(
            90deg,
            #2563eb,
            #06b6d4,
            #6366f1,
            #2563eb
        );

    background-size:
        300% 100%;

    animation:
        topGradient 4s linear infinite;
}


@keyframes topGradient {

    from {
        background-position: 0 0;
    }

    to {
        background-position: 300% 0;
    }
}


/* =========================================================
   CARD ICON
========================================================= */

.card-icon {

    width: 58px;

    height: 58px;

    border-radius: 17px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    font-size: 24px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    box-shadow:
        0 12px 28px
        rgba(37,99,235,.25);

    margin-bottom: 20px;
}


.login-card h2 {

    margin: 0;

    color: #0b2545;

    font-size: 28px;

    letter-spacing:
        -.8px;
}


.login-subtitle {

    margin:
        8px 0 25px;

    color: #667085;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   ERROR
========================================================= */

.error {

    padding:
        11px 13px;

    border-radius: 10px;

    margin-bottom: 17px;

    background: #fff1f1;

    border:
        1px solid #f3b3b3;

    color: #b42318;

    font-size: 10px;

    font-weight: 700;

    animation:
        shake .35s ease;
}


@keyframes shake {

    0%,
    100% {
        transform: translateX(0);
    }

    25% {
        transform: translateX(-5px);
    }

    75% {
        transform: translateX(5px);
    }
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 17px;
}


.form-label {

    display: block;

    margin-bottom: 7px;

    color: #344054;

    font-size: 10px;

    font-weight: 900;
}


.input-box {

    position: relative;
}


.input-icon {

    position: absolute;

    left: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #98a2b3;

    pointer-events: none;

    transition:
        color .2s ease;
}


.input {

    width: 100%;

    height: 50px;

    border:
        1px solid #d0d5dd;

    border-radius: 11px;

    background:
        rgba(255,255,255,.9);

    color: #101828;

    outline: none;

    padding:
        0 43px;

    font-size: 12px;

    transition:
        border .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}


.input::placeholder {

    color: #98a2b3;
}


.input:hover {

    border-color:
        #98a2b3;
}


.input:focus {

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);

    transform:
        translateY(-1px);
}


.input-box:focus-within
.input-icon {

    color: #2563eb;
}


/* =========================================================
   PASSWORD TOGGLE
========================================================= */

.password-toggle {

    position: absolute;

    right: 11px;

    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    cursor: pointer;

    color: #667085;

    font-size: 13px;

    padding: 6px;
}


.password-toggle:hover {

    color: #2563eb;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-button {

    width: 100%;

    height: 51px;

    margin-top: 4px;

    border: 0;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    color: white;

    font-size: 12px;

    font-weight: 900;

    letter-spacing:
        .3px;

    cursor: pointer;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.25);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.login-button::after {

    content: "";

    position: absolute;

    width: 70px;

    height: 180px;

    top: -70px;

    left: -120px;

    background:
        rgba(255,255,255,.22);

    transform:
        rotate(25deg);

    animation:
        shine 3s infinite;
}


@keyframes shine {

    0% {
        left: -120px;
    }

    45%,
    100% {
        left: 120%;
    }
}


.login-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 32px
        rgba(37,99,235,.32);
}


.login-button:active {

    transform:
        translateY(0);
}


/* =========================================================
   DEMO
========================================================= */

.demo {

    margin-top: 18px;

    padding:
        12px 14px;

    border:
        1px dashed #d0d5dd;

    border-radius: 10px;

    background:
        #f8fafc;

    color: #667085;

    font-size: 9px;

    line-height: 1.7;
}


.demo strong {

    color: #344054;
}


.demo span {

    color: #2563eb;

    font-weight: 900;
}


/* =========================================================
   SECURITY
========================================================= */

.security {

    text-align: center;

    margin-top: 17px;

    color: #98a2b3;

    font-size: 9px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    position: relative;

    z-index: 5;

    text-align: center;

    padding:
        15px;

    color: #98a2b3;

    font-size: 9px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 950px) {

    .main-content {

        grid-template-columns: 1fr;

        max-width: 540px;

        gap: 30px;

        padding:
            45px 20px;
    }


    .hero {

        text-align: center;
    }


    .hero-description {

        margin-left: auto;

        margin-right: auto;
    }


    .plane-track {

        display: none;
    }

}


@media (max-width: 600px) {

    .topbar {

        height: 65px;

        padding:
            0 18px;
    }


    .portal-badge {

        display: none;
    }


    .home-link {

        display: none;
    }


    .main-content {

        padding:
            35px 15px;
    }


    .hero h1 {

        font-size: 43px;

        letter-spacing:
            -2px;
    }


    .features {

        grid-template-columns: 1fr;
    }


    .login-card {

        padding:
            27px 20px;

        border-radius: 20px;
    }

}

</style>

</head>


<body>


<div class="login-page">


    <!-- BACKGROUND -->

    <div class="background-grid"></div>

    <div class="blob blob-1"></div>

    <div class="blob blob-2"></div>

    <div class="blob blob-3"></div>


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="topbar">


        <a
            href="../index.php"
            class="logo"
        >

            <div class="logo-icon">
                ✈
            </div>

            <div class="logo-text">
                Vektor<span>Sky</span>
            </div>

        </a>


        <div class="topbar-right">

            <div class="portal-badge">
                ADMIN PORTAL
            </div>

            <a
                href="../index.php"
                class="home-link"
            >
                ← Back to Website
            </a>

        </div>


    </header>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main-content">


        <!-- LEFT -->

        <section class="hero">


            <div class="status-pill">

                <span class="status-dot"></span>

                VektorSky Control Center

            </div>


            <h1>

                Fly smarter.<br>

                Manage <span>better.</span>

            </h1>


            <p class="hero-description">

                Welcome to the VektorSky administration
                portal. Manage flights, aircraft, seats,
                passengers, bookings and payments from
                one powerful control center.

            </p>


            <div class="features">


                <div class="feature">

                    <div class="feature-icon">
                        ✈️
                    </div>

                    <div class="feature-title">
                        Flight Control
                    </div>

                    <div class="feature-text">
                        Manage schedules and routes.
                    </div>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        🎫
                    </div>

                    <div class="feature-title">
                        Bookings
                    </div>

                    <div class="feature-text">
                        Track reservations and PNRs.
                    </div>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        📊
                    </div>

                    <div class="feature-title">
                        Analytics
                    </div>

                    <div class="feature-text">
                        Monitor revenue and performance.
                    </div>

                </div>


            </div>


            <div class="plane-track">

                <div class="plane-line"></div>

                <div class="plane">
                    ✈
                </div>

            </div>


        </section>


        <!-- LOGIN CARD -->

        <section class="login-card">


            <div class="card-icon">
                🔐
            </div>


            <h2>
                Admin Login
            </h2>


            <p class="login-subtitle">

                Sign in to access your VektorSky
                administration dashboard.

            </p>


            <?php if ($error): ?>

                <div class="error">

                    ⚠️

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                autocomplete="off"
            >


                <!-- USERNAME -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="username"
                    >
                        ADMIN USERNAME
                    </label>


                    <div class="input-box">

                        <span class="input-icon">
                            👤
                        </span>


                        <input
                            class="input"
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter admin username"
                            value="<?= htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                            autocomplete="username"
                        >

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="password"
                    >
                        PASSWORD
                    </label>


                    <div class="input-box">

                        <span class="input-icon">
                            🔒
                        </span>


                        <input
                            class="input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter admin password"
                            required
                            autocomplete="current-password"
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                        >
                            👁
                        </button>

                    </div>

                </div>


                <button
                    type="submit"
                    class="login-button"
                    id="loginButton"
                >

                    Sign In to Dashboard
                    &nbsp; →

                </button>


            </form>


            <div class="demo">

                <strong>
                    Demo administrator
                </strong>

                <br>

                Username:
                <span>admin</span>

                &nbsp; • &nbsp;

                Password:
                <span>admin123</span>

            </div>


            <div class="security">

                🔒 Secure VektorSky Admin Portal

            </div>


        </section>


    </main>


    <footer class="footer">

        © <?= date('Y') ?>

        VektorSky

        — Airline Management System

    </footer>


</div>


<script>

/* =========================================================
   PASSWORD SHOW / HIDE
========================================================= */

function togglePassword() {

    const input =
        document.getElementById(
            'password'
        );

    const button =
        document.querySelector(
            '.password-toggle'
        );


    if (
        input.type === 'password'
    ) {

        input.type = 'text';

        button.textContent = '🙈';

    } else {

        input.type = 'password';

        button.textContent = '👁';
    }
}


/* =========================================================
   LOGIN LOADING
========================================================= */

document
    .querySelector('form')
    .addEventListener(
        'submit',
        function () {

            const button =
                document.getElementById(
                    'loginButton'
                );

            button.innerHTML =
                'Authenticating...';

            button.style.opacity =
                '.85';

            button.style.pointerEvents =
                'none';
        }
    );

</script>


</body>

</html>