<?php
/*
|--------------------------------------------------------------------------
| VektorSky - Common Header
|--------------------------------------------------------------------------
|
| This file is included by pages such as:
| index.php
| confirmation.php
| my_bookings.php
| book.php
| payment.php
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Start session safely
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Get login status
|--------------------------------------------------------------------------
*/

$headerLoggedIn = false;

if (function_exists('is_logged_in')) {
    $headerLoggedIn = is_logged_in();
}


/*
|--------------------------------------------------------------------------
| Current passenger
|--------------------------------------------------------------------------
*/

$headerPassenger = null;

if ($headerLoggedIn && function_exists('current_passenger_id')) {

    $headerPassengerId =
        current_passenger_id();

    if (
        $headerPassengerId
        &&
        isset($pdo)
    ) {

        try {

            $headerStmt = $pdo->prepare("
                SELECT
                    passenger_id,
                    name,
                    email
                FROM passenger
                WHERE passenger_id = ?
                LIMIT 1
            ");

            $headerStmt->execute([
                $headerPassengerId
            ]);

            $headerPassenger =
                $headerStmt->fetch(
                    PDO::FETCH_ASSOC
                );

        } catch (Throwable $e) {

            $headerPassenger = null;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Current page
|--------------------------------------------------------------------------
*/

$headerCurrentPage =
    basename(
        $_SERVER['PHP_SELF'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| Helper for active navigation
|--------------------------------------------------------------------------
*/

function header_active($page)
{
    global $headerCurrentPage;

    return
        $headerCurrentPage === $page
        ? 'active'
        : '';
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

<meta
    name="theme-color"
    content="#0b1736"
>

<title>
    VektorSky
</title>


<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

* {
    box-sizing:
        border-box;
}

html {
    scroll-behavior:
        smooth;
}

body {

    margin:
        0;

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;

    background:
        #f5f7fb;

    color:
        #101828;

    -webkit-font-smoothing:
        antialiased;
}

button,
input,
select,
textarea {

    font-family:
        inherit;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.vs-header {

    position:
        sticky;

    top:
        0;

    z-index:
        1000;

    width:
        100%;

    height:
        72px;

    background:
        rgba(
            255,
            255,
            255,
            .96
        );

    backdrop-filter:
        blur(14px);

    -webkit-backdrop-filter:
        blur(14px);

    border-bottom:
        1px solid #e4e7ec;

    box-shadow:
        0 4px 18px
        rgba(
            16,
            24,
            40,
            .04
        );
}


/*
|--------------------------------------------------------------------------
| HEADER CONTAINER
|--------------------------------------------------------------------------
*/

.vs-header-container {

    width:
        min(
            1200px,
            calc(100% - 40px)
        );

    height:
        100%;

    margin:
        auto;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        30px;
}


/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

.vs-logo {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        10px;

    text-decoration:
        none;

    color:
        #0b1736;

    flex-shrink:
        0;
}

.vs-logo-icon {

    width:
        42px;

    height:
        42px;

    border-radius:
        13px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        white;

    font-size:
        19px;

    font-weight:
        900;

    background:
        linear-gradient(
            135deg,
            #1769ff,
            #06a9dc
        );

    box-shadow:
        0 7px 18px
        rgba(
            23,
            105,
            255,
            .25
        );
}

.vs-logo-text {

    font-size:
        20px;

    font-weight:
        950;

    letter-spacing:
        -.7px;
}

.vs-logo-text span {

    color:
        #1769ff;
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

.vs-nav {

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        5px;

    flex: 1;
}

.vs-nav-link {

    position:
        relative;

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    min-height:
        42px;

    padding:
        0 13px;

    border-radius:
        10px;

    text-decoration:
        none;

    color:
        #475467;

    font-size:
        11px;

    font-weight:
        800;

    transition:
        .18s ease;
}

.vs-nav-link:hover {

    color:
        #1769ff;

    background:
        #f1f6ff;
}

.vs-nav-link.active {

    color:
        #1769ff;

    background:
        #eff6ff;
}

.vs-nav-link.active::after {

    content:
        "";

    position:
        absolute;

    bottom:
        -1px;

    left:
        50%;

    width:
        20px;

    height:
        3px;

    transform:
        translateX(-50%);

    border-radius:
        10px;

    background:
        #1769ff;
}


/*
|--------------------------------------------------------------------------
| RIGHT SIDE
|--------------------------------------------------------------------------
*/

.vs-header-right {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    flex-shrink:
        0;
}


/*
|--------------------------------------------------------------------------
| MY TRIPS BUTTON
|--------------------------------------------------------------------------
*/

.vs-trips-btn {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    height:
        40px;

    padding:
        0 13px;

    border:
        1px solid #dbe3ee;

    border-radius:
        10px;

    background:
        white;

    color:
        #344054;

    text-decoration:
        none;

    font-size:
        10px;

    font-weight:
        900;

    transition:
        .18s ease;
}

.vs-trips-btn:hover {

    border-color:
        #a9c7ff;

    color:
        #1769ff;

    background:
        #f8fbff;
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

.vs-user {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    padding-left:
        8px;

    border-left:
        1px solid #e4e7ec;
}

.vs-avatar {

    width:
        36px;

    height:
        36px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        linear-gradient(
            135deg,
            #e0ecff,
            #dbeafe
        );

    color:
        #1769ff;

    font-size:
        14px;

    font-weight:
        900;

    flex-shrink:
        0;
}

.vs-user-info {

    min-width:
        0;
}

.vs-user-name {

    display:
        block;

    max-width:
        130px;

    overflow:
        hidden;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;

    color:
        #101828;

    font-size:
        10px;

    font-weight:
        900;
}

.vs-user-email {

    display:
        block;

    max-width:
        150px;

    overflow:
        hidden;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;

    color:
        #98a2b3;

    font-size:
        8px;

    margin-top:
        2px;
}


/*
|--------------------------------------------------------------------------
| LOGIN BUTTON
|--------------------------------------------------------------------------
*/

.vs-login-btn {

    height:
        40px;

    padding:
        0 16px;

    border:
        1px solid #1769ff;

    border-radius:
        10px;

    background:
        #1769ff;

    color:
        white;

    text-decoration:
        none;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        10px;

    font-weight:
        900;

    transition:
        .18s ease;
}

.vs-login-btn:hover {

    background:
        #1255d6;

    border-color:
        #1255d6;

    transform:
        translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| REGISTER BUTTON
|--------------------------------------------------------------------------
*/

.vs-register-btn {

    height:
        40px;

    padding:
        0 14px;

    border:
        1px solid #d0d5dd;

    border-radius:
        10px;

    background:
        white;

    color:
        #344054;

    text-decoration:
        none;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        10px;

    font-weight:
        900;

    transition:
        .18s ease;
}

.vs-register-btn:hover {

    color:
        #1769ff;

    border-color:
        #a9c7ff;
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

.vs-logout {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    width:
        34px;

    height:
        34px;

    border:
        0;

    border-radius:
        9px;

    background:
        #fef3f2;

    color:
        #b42318;

    text-decoration:
        none;

    font-size:
        13px;

    transition:
        .18s ease;
}

.vs-logout:hover {

    background:
        #fee4e2;

    transform:
        translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| MOBILE MENU BUTTON
|--------------------------------------------------------------------------
*/

.vs-menu-btn {

    display:
        none;

    width:
        40px;

    height:
        40px;

    border:
        1px solid #d0d5dd;

    border-radius:
        10px;

    background:
        white;

    color:
        #344054;

    cursor:
        pointer;

    font-size:
        18px;
}


/*
|--------------------------------------------------------------------------
| MOBILE NAV
|--------------------------------------------------------------------------
*/

.vs-mobile-menu {

    display:
        none;

    position:
        absolute;

    top:
        72px;

    left:
        0;

    width:
        100%;

    background:
        white;

    border-bottom:
        1px solid #e4e7ec;

    box-shadow:
        0 12px 30px
        rgba(
            16,
            24,
            40,
            .10
        );

    padding:
        12px 20px 18px;
}

.vs-mobile-menu.open {

    display:
        block;
}

.vs-mobile-link {

    display:
        flex;

    align-items:
        center;

    min-height:
        44px;

    padding:
        0 12px;

    border-radius:
        9px;

    text-decoration:
        none;

    color:
        #344054;

    font-size:
        11px;

    font-weight:
        800;
}

.vs-mobile-link:hover,
.vs-mobile-link.active {

    background:
        #eff6ff;

    color:
        #1769ff;
}


/*
|--------------------------------------------------------------------------
| MOBILE USER
|--------------------------------------------------------------------------
*/

.vs-mobile-user {

    margin-top:
        10px;

    padding:
        13px;

    border-top:
        1px solid #edf1f5;

    display:
        flex;

    align-items:
        center;

    gap:
        10px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:900px) {

    .vs-nav {

        display:
            none;
    }

    .vs-user-info {

        display:
            none;
    }

    .vs-user {

        border-left:
            0;

        padding-left:
            0;
    }

    .vs-menu-btn {

        display:
            inline-flex;

        align-items:
            center;

        justify-content:
            center;
    }

}


@media(max-width:600px) {

    .vs-header-container {

        width:
            calc(100% - 24px);

        gap:
            10px;
    }

    .vs-logo-text {

        font-size:
            18px;
    }

    .vs-logo-icon {

        width:
            38px;

        height:
            38px;
    }

    .vs-trips-btn {

        display:
            none;
    }

    .vs-login-btn {

        height:
            38px;

        padding:
            0 12px;
    }

    .vs-register-btn {

        display:
            none;
    }

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    .vs-header {

        display:
            none !important;
    }

}

</style>


<!-- ==========================================================
     HEADER
=========================================================== -->

<header class="vs-header">


    <div class="vs-header-container">


        <!-- ==================================================
             LOGO
        =================================================== -->

        <a
            href="index.php"
            class="vs-logo"
            aria-label="VektorSky Home"
        >

            <div class="vs-logo-icon">
                ✈
            </div>

            <div class="vs-logo-text">
                Vektor<span>Sky</span>
            </div>

        </a>


        <!-- ==================================================
             DESKTOP NAVIGATION
        =================================================== -->

        <nav
            class="vs-nav"
            aria-label="Main navigation"
        >

            <a
                href="index.php"
                class="vs-nav-link <?= header_active('index.php') ?>"
            >
                ✈
                Flights
            </a>


            <?php if ($headerLoggedIn): ?>

                <a
                    href="my_bookings.php"
                    class="vs-nav-link <?= header_active('my_bookings.php') ?>"
                >
                    🎫
                    My Trips
                </a>

            <?php endif; ?>


            <a
                href="index.php#why-us"
                class="vs-nav-link"
            >
                Why VektorSky
            </a>

        </nav>


        <!-- ==================================================
             RIGHT SIDE
        =================================================== -->

        <div class="vs-header-right">


            <?php if ($headerLoggedIn): ?>


                <!-- My Trips -->

                <a
                    href="my_bookings.php"
                    class="vs-trips-btn"
                >
                    🎫
                    My Trips
                </a>


                <!-- User -->

                <div class="vs-user">


                    <div class="vs-avatar">

                        <?php

                        $userName =
                            $headerPassenger['name']
                            ?? 'Passenger';

                        $firstLetter =
                            strtoupper(
                                substr(
                                    trim(
                                        $userName
                                    ),
                                    0,
                                    1
                                )
                            );

                        ?>

                        <?= h($firstLetter) ?>

                    </div>


                    <div class="vs-user-info">

                        <span class="vs-user-name">

                            <?= h(
                                $userName
                            ) ?>

                        </span>

                        <span class="vs-user-email">

                            <?= h(
                                $headerPassenger['email']
                                ?? ''
                            ) ?>

                        </span>

                    </div>


                    <!-- Logout -->

                    <a
                        href="logout.php"
                        class="vs-logout"
                        title="Logout"
                        onclick="
                            return confirm(
                                'Are you sure you want to logout?'
                            );
                        "
                    >
                        ↪
                    </a>


                </div>


            <?php else: ?>


                <!-- Register -->

                <a
                    href="register.php"
                    class="vs-register-btn"
                >
                    Register
                </a>


                <!-- Login -->

                <a
                    href="login.php"
                    class="vs-login-btn"
                >
                    Login
                </a>


            <?php endif; ?>


            <!-- Mobile Menu -->

            <button
                type="button"
                class="vs-menu-btn"
                id="vsMenuButton"
                aria-label="Open menu"
                aria-expanded="false"
            >
                ☰
            </button>


        </div>


    </div>


    <!-- ======================================================
         MOBILE MENU
    ======================================================= -->

    <div
        class="vs-mobile-menu"
        id="vsMobileMenu"
    >


        <a
            href="index.php"
            class="vs-mobile-link <?= header_active('index.php') ?>"
        >
            ✈ &nbsp; Flights
        </a>


        <?php if ($headerLoggedIn): ?>

            <a
                href="my_bookings.php"
                class="vs-mobile-link <?= header_active('my_bookings.php') ?>"
            >
                🎫 &nbsp; My Trips
            </a>

        <?php endif; ?>


        <a
            href="index.php#why-us"
            class="vs-mobile-link"
        >
            ⭐ &nbsp; Why VektorSky
        </a>


        <?php if ($headerLoggedIn): ?>


            <div class="vs-mobile-user">


                <div class="vs-avatar">

                    <?= h(
                        $firstLetter
                    ) ?>

                </div>


                <div>

                    <strong
                        style="
                            display:block;
                            font-size:11px;
                        "
                    >

                        <?= h(
                            $userName
                        ) ?>

                    </strong>

                    <span
                        style="
                            display:block;
                            color:#98a2b3;
                            font-size:8px;
                            margin-top:3px;
                        "
                    >

                        <?= h(
                            $headerPassenger['email']
                            ?? ''
                        ) ?>

                    </span>

                </div>


            </div>


            <a
                href="logout.php"
                class="vs-mobile-link"
                style="
                    color:#b42318;
                    margin-top:5px;
                "
            >
                ↪ &nbsp; Logout
            </a>


        <?php else: ?>


            <a
                href="login.php"
                class="vs-mobile-link"
            >
                🔐 &nbsp; Login
            </a>


            <a
                href="register.php"
                class="vs-mobile-link"
            >
                👤 &nbsp; Create Account
            </a>


        <?php endif; ?>


    </div>


</header>


<script>

/*
|--------------------------------------------------------------------------
| Mobile menu
|--------------------------------------------------------------------------
*/

(function () {

    const button =
        document.getElementById(
            'vsMenuButton'
        );

    const menu =
        document.getElementById(
            'vsMobileMenu'
        );


    if (!button || !menu) {
        return;
    }


    button.addEventListener(
        'click',
        function () {

            const isOpen =
                menu.classList.toggle(
                    'open'
                );

            button.setAttribute(
                'aria-expanded',
                isOpen
                    ? 'true'
                    : 'false'
            );

            button.innerHTML =
                isOpen
                    ? '✕'
                    : '☰';

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Close menu when clicking a link
    |--------------------------------------------------------------------------
    */

    menu
        .querySelectorAll('a')
        .forEach(
            function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        menu.classList.remove(
                            'open'
                        );

                        button.setAttribute(
                            'aria-expanded',
                            'false'
                        );

                        button.innerHTML =
                            '☰';

                    }
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Close menu when clicking outside
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        function (event) {

            if (
                !menu.contains(event.target)
                &&
                !button.contains(event.target)
            ) {

                menu.classList.remove(
                    'open'
                );

                button.setAttribute(
                    'aria-expanded',
                    'false'
                );

                button.innerHTML =
                    '☰';

            }

        }
    );

})();

</script>