<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn =
    isset($_SESSION['user_id']) ||
    isset($_SESSION['passenger_id']);

$userName =
    $_SESSION['user_name'] ??
    $_SESSION['name'] ??
    'Traveler';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$departure = $_GET['departure'] ?? '';
$return = $_GET['return'] ?? '';
$tripType = $_GET['trip_type'] ?? 'round';
$travellers = (int)($_GET['travellers'] ?? 1);
$travelClass = $_GET['class'] ?? 'Economy';

if ($travellers < 1) {
    $travellers = 1;
}

if ($travellers > 9) {
    $travellers = 9;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>VektorSky — Smart Flight Booking</title>

<meta
    name="description"
    content="Search flights, compare fares and book your journey with VektorSky."
>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<style>

/* ============================================================
   RESET
============================================================ */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Inter', Arial, sans-serif;
    background: #f5f8fc;
    color: #0f172a;
    min-height: 100vh;
}

button,
input,
select {
    font-family: inherit;
}


/* ============================================================
   NAVBAR
============================================================ */

.vs-navbar {
    position: sticky;
    top: 0;
    z-index: 5000;

    height: 74px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 6%;

    background: rgba(255,255,255,.90);
    backdrop-filter: blur(18px);

    border-bottom: 1px solid #e2e8f0;
}

.vs-logo {
    display: flex;
    align-items: center;
    gap: 10px;

    text-decoration: none;
    color: #0f172a;
}

.vs-logo-icon {
    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 13px;

    background: linear-gradient(
        135deg,
        #2563eb,
        #4f46e5
    );

    color: white;
    font-size: 21px;

    box-shadow:
        0 8px 25px rgba(37,99,235,.25);
}

.vs-logo-text {
    font-size: 21px;
    font-weight: 900;
    letter-spacing: -.6px;
}

.vs-logo-text span {
    color: #2563eb;
}

.vs-nav-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.vs-nav-link {
    text-decoration: none;
    color: #475569;

    font-size: 13px;
    font-weight: 700;

    padding: 10px 13px;
    border-radius: 10px;
}

.vs-nav-link:hover {
    background: #f1f5f9;
    color: #2563eb;
}

.vs-nav-button {
    text-decoration: none;

    padding: 10px 17px;

    border-radius: 11px;

    background: #2563eb;
    color: white;

    font-size: 12px;
    font-weight: 800;

    box-shadow:
        0 7px 20px rgba(37,99,235,.20);
}

.vs-nav-button:hover {
    background: #1d4ed8;
}


/* ============================================================
   HERO
============================================================ */

.vs-hero {
    position: relative;
    overflow: hidden;

    min-height: 500px;

    padding: 78px 6% 120px;

    background:
        linear-gradient(
            135deg,
            #0f172a 0%,
            #172554 48%,
            #1d4ed8 100%
        );
}

.vs-hero::before {
    content: "";

    position: absolute;

    width: 520px;
    height: 520px;

    border-radius: 50%;

    background: rgba(96,165,250,.14);

    top: -250px;
    right: -80px;
}

.vs-hero::after {
    content: "";

    position: absolute;

    width: 380px;
    height: 380px;

    border-radius: 50%;

    background: rgba(129,140,248,.10);

    bottom: -240px;
    left: 10%;
}

.vs-hero-content {
    position: relative;
    z-index: 2;

    max-width: 850px;
    margin: auto;

    text-align: center;
}

.vs-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    padding: 8px 14px;

    border:
        1px solid rgba(255,255,255,.18);

    border-radius: 999px;

    background: rgba(255,255,255,.08);

    color: #bfdbfe;

    font-size: 11px;
    font-weight: 800;

    margin-bottom: 20px;
}

.vs-hero h1 {
    color: white;

    font-size:
        clamp(36px, 5vw, 62px);

    line-height: 1.03;

    letter-spacing: -2.7px;

    font-weight: 900;

    margin-bottom: 18px;
}

.vs-hero h1 span {
    color: #93c5fd;
}

.vs-hero-subtitle {
    max-width: 680px;

    margin: auto;

    color: #cbd5e1;

    font-size: 15px;
    line-height: 1.7;
}


/* ============================================================
   SEARCH CARD
============================================================ */

.vs-search-wrapper {
    position: relative;
    z-index: 20;

    max-width: 1180px;

    margin: -82px auto 0;

    padding: 0 20px;
}

.vs-search-card {
    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 24px;

    padding: 24px;

    box-shadow:
        0 25px 70px rgba(15,23,42,.16);

    animation:
        cardAppear .5s ease;
}

@keyframes cardAppear {

    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}

.vs-search-tabs {
    display: flex;
    gap: 7px;

    margin-bottom: 20px;
}

.vs-search-tab {
    border: none;

    padding: 8px 13px;

    border-radius: 9px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 11px;
    font-weight: 800;
}


/* ============================================================
   SEARCH GRID
============================================================ */

.vs-search-grid {
    display: grid;

    grid-template-columns:
        1.25fr
        1.25fr
        1fr
        .75fr
        .9fr
        auto;

    gap: 10px;

    align-items: end;
}

.vs-field {
    position: relative;
}

.vs-field label {
    display: block;

    margin:
        0 0 7px 3px;

    color: #64748b;

    font-size: 10px;

    font-weight: 900;

    letter-spacing: .7px;
}


/* ============================================================
   AIRPORT INPUT
============================================================ */

.vs-airport-input-wrapper {
    position: relative;
}

.vs-airport-input-wrapper
input[type="text"] {

    width: 100%;

    height: 52px;

    padding: 0 14px;

    border:
        1px solid #dbe3ef;

    border-radius: 13px;

    background: white;

    color: #0f172a;

    font-size: 13px;

    font-weight: 700;

    outline: none;

    transition: .2s ease;
}

.vs-airport-input-wrapper
input[type="text"]:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);
}


/* ============================================================
   AIRPORT DROPDOWN
============================================================ */

.vs-airport-suggestions {

    position: absolute;

    top: calc(100% + 7px);

    left: 0;
    right: 0;

    z-index: 99999;

    display: none;

    overflow: hidden;

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 16px;

    box-shadow:
        0 20px 50px rgba(15,23,42,.18);

    animation:
        dropdownAppear .18s ease;
}

.vs-airport-suggestions.show {
    display: block;
}

@keyframes dropdownAppear {

    from {
        opacity: 0;
        transform: translateY(-5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}

.vs-airport-option {

    width: 100%;

    padding: 13px 15px;

    display: flex;

    align-items: center;

    gap: 12px;

    border: 0;

    border-bottom:
        1px solid #f1f5f9;

    background: white;

    text-align: left;

    cursor: pointer;

    transition: .15s ease;
}

.vs-airport-option:last-child {
    border-bottom: none;
}

.vs-airport-option:hover {
    background: #f8fafc;
}

.vs-airport-icon {

    width: 40px;
    height: 40px;

    flex-shrink: 0;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 12px;

    background: #eff6ff;

    font-size: 18px;
}

.vs-airport-info {
    min-width: 0;
    flex: 1;
}

.vs-airport-name {

    display: block;

    overflow: hidden;

    color: #0f172a;

    font-size: 12px;

    font-weight: 800;

    white-space: nowrap;

    text-overflow: ellipsis;
}

.vs-airport-city {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.vs-airport-code {

    flex-shrink: 0;

    padding: 5px 8px;

    border-radius: 7px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 11px;

    font-weight: 900;
}


/* ============================================================
   SWAP
============================================================ */

.vs-swap {

    position: absolute;

    right: -20px;

    bottom: 8px;

    z-index: 50;

    width: 34px;
    height: 34px;

    border:
        1px solid #dbe3ef;

    border-radius: 50%;

    background: white;

    color: #2563eb;

    font-size: 17px;

    cursor: pointer;

    box-shadow:
        0 5px 15px rgba(15,23,42,.10);

    transition: .2s ease;
}

.vs-swap:hover {
    transform: rotate(180deg);
}


/* ============================================================
   DATE BUTTON
============================================================ */

.vs-date-picker-button {

    width: 100%;

    height: 52px;

    padding: 0 14px;

    display: flex;

    align-items: center;

    gap: 9px;

    border:
        1px solid #dbe3ef;

    border-radius: 13px;

    background: white;

    color: #0f172a;

    cursor: pointer;

    text-align: left;

    transition: .2s ease;
}

.vs-date-picker-button:hover {
    border-color: #2563eb;
}

.vs-date-icon {
    font-size: 17px;
}

.vs-date-text {
    font-size: 12px;
    font-weight: 800;
}

.vs-date-text.placeholder {
    color: #94a3b8;
}


/* ============================================================
   SELECT
============================================================ */

.vs-select {

    width: 100%;

    height: 52px;

    padding: 0 12px;

    border:
        1px solid #dbe3ef;

    border-radius: 13px;

    background: white;

    color: #0f172a;

    font-size: 12px;

    font-weight: 700;

    outline: none;
}


/* ============================================================
   SEARCH BUTTON
============================================================ */

.vs-search-button {

    height: 52px;

    padding: 0 19px;

    border: none;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 12px;

    font-weight: 900;

    cursor: pointer;

    box-shadow:
        0 10px 25px
        rgba(37,99,235,.25);

    transition: .2s ease;
}

.vs-search-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 14px 30px
        rgba(37,99,235,.30);
}


/* ============================================================
   CALENDAR OVERLAY
============================================================ */

.vs-calendar-overlay {

    position: fixed;

    inset: 0;

    z-index: 999999;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(15,23,42,.58);

    backdrop-filter:
        blur(7px);
}

.vs-calendar-overlay.show {
    display: flex;
}


/* ============================================================
   CALENDAR
============================================================ */

.vs-calendar {

    width:
        min(460px, 100%);

    max-height: 90vh;

    overflow-y: auto;

    padding: 24px;

    border-radius: 25px;

    background: white;

    box-shadow:
        0 35px 100px
        rgba(15,23,42,.30);

    animation:
        calendarAppear .25s ease;
}

@keyframes calendarAppear {

    from {
        opacity: 0;
        transform:
            translateY(15px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }

}


/* ============================================================
   CALENDAR HEADER
============================================================ */

.vs-calendar-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 20px;
}

.vs-calendar-title {

    font-size: 20px;

    font-weight: 900;
}

.vs-calendar-subtitle {

    margin-top: 5px;

    color: #64748b;

    font-size: 10px;

    font-weight: 700;
}

.vs-calendar-nav {

    display: flex;

    gap: 7px;
}

.vs-calendar-nav button {

    width: 40px;
    height: 40px;

    border: none;

    border-radius: 11px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 20px;

    cursor: pointer;

    transition: .2s ease;
}

.vs-calendar-nav button:hover {
    background: #dbeafe;
}


/* ============================================================
   WEEKDAYS
============================================================ */

.vs-calendar-weekdays {

    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 5px;

    margin-bottom: 7px;
}

.vs-calendar-weekday {

    text-align: center;

    color: #94a3b8;

    font-size: 9px;

    font-weight: 900;
}


/* ============================================================
   CALENDAR DAYS
============================================================ */

.vs-calendar-days {

    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 5px;
}

.vs-calendar-day {

    min-height: 62px;

    border:
        1px solid transparent;

    border-radius: 12px;

    background: white;

    cursor: pointer;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    transition: .18s ease;
}

.vs-calendar-day:hover {

    background: #eff6ff;

    transform:
        translateY(-2px);
}

.vs-calendar-day.empty {
    cursor: default;
}

.vs-calendar-day.disabled {

    opacity: .28;

    cursor: not-allowed;
}

.vs-calendar-day.today {

    outline:
        2px solid #bfdbfe;
}


/* ============================================================
   FLIGHT AVAILABLE DAY
============================================================ */

.vs-calendar-day.has-flight {

    background: #f0fdf4;

    border:
        1px solid #bbf7d0;
}

.vs-calendar-day.has-flight:hover {

    background: #dcfce7;
}


/* ============================================================
   SELECTED
============================================================ */

.vs-calendar-day.selected {

    background: #2563eb;

    color: white;

    border-color: #2563eb;
}

.vs-calendar-day.selected.has-flight {

    background: #2563eb;

    border-color: #2563eb;
}


/* ============================================================
   DAY NUMBER
============================================================ */

.vs-calendar-day-number {

    font-size: 12px;

    font-weight: 900;
}


/* ============================================================
   PRICE
============================================================ */

.vs-calendar-price {

    margin-top: 5px;

    color: #16a34a;

    font-size: 8px;

    font-weight: 900;

    line-height: 1;
}

.vs-calendar-day.selected
.vs-calendar-price {

    color: white;
}


/* ============================================================
   FLIGHT DOT
============================================================ */

.vs-calendar-flight-dot {

    width: 5px;
    height: 5px;

    margin-top: 4px;

    border-radius: 50%;

    background: #16a34a;
}

.vs-calendar-day.selected
.vs-calendar-flight-dot {

    background: white;
}


/* ============================================================
   CALENDAR LEGEND
============================================================ */

.vs-calendar-legend {

    display: flex;

    align-items: center;

    gap: 18px;

    margin-top: 18px;

    padding-top: 14px;

    border-top:
        1px solid #e2e8f0;

    color: #64748b;

    font-size: 9px;
}

.vs-calendar-legend-item {

    display: flex;

    align-items: center;

    gap: 5px;
}

.vs-calendar-legend-dot {

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #16a34a;
}


/* ============================================================
   CALENDAR CLOSE
============================================================ */

.vs-calendar-close {

    width: 100%;

    height: 44px;

    margin-top: 18px;

    border: none;

    border-radius: 12px;

    background: #f1f5f9;

    color: #475569;

    font-weight: 800;

    cursor: pointer;
}

.vs-calendar-close:hover {
    background: #e2e8f0;
}


/* ============================================================
   FEATURES
============================================================ */

.vs-features {

    max-width: 1180px;

    margin: 75px auto;

    padding: 0 20px;
}

.vs-section-title {

    text-align: center;

    margin-bottom: 30px;
}

.vs-section-title h2 {

    font-size: 29px;

    letter-spacing: -1px;

    font-weight: 900;
}

.vs-section-title p {

    margin-top: 8px;

    color: #64748b;

    font-size: 13px;
}

.vs-feature-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 18px;
}

.vs-feature-card {

    padding: 26px;

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 19px;

    transition: .25s ease;
}

.vs-feature-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 20px 45px
        rgba(15,23,42,.08);
}

.vs-feature-icon {

    width: 45px;
    height: 45px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 13px;

    background: #eff6ff;

    font-size: 20px;

    margin-bottom: 17px;
}

.vs-feature-card h3 {

    font-size: 15px;

    margin-bottom: 7px;
}

.vs-feature-card p {

    color: #64748b;

    font-size: 11px;

    line-height: 1.7;
}


/* ============================================================
   FOOTER
============================================================ */

.vs-footer {

    margin-top: 80px;

    padding: 35px 6%;

    background: #0f172a;

    color: #94a3b8;

    text-align: center;

    font-size: 11px;
}

.vs-footer strong {
    color: white;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1150px) {

    .vs-search-grid {

        grid-template-columns:
            1fr 1fr 1fr;

    }

}

@media (max-width: 750px) {

    .vs-navbar {

        padding: 0 20px;

    }

    .vs-nav-link {
        display: none;
    }

    .vs-hero {

        padding:
            60px 20px 100px;

    }

    .vs-search-wrapper {

        margin-top: -65px;

        padding:
            0 12px;

    }

    .vs-search-card {

        padding: 18px;

    }

    .vs-search-grid {

        grid-template-columns:
            1fr;

    }

    .vs-swap {

        right: 12px;

        bottom: -18px;

    }

    .vs-feature-grid {

        grid-template-columns:
            1fr;

    }

}

</style>


<style>
/* Calendar + trip type fixes */
.vs-search-tab.active {
    background: #2563eb !important;
    color: #fff !important;
}
.vs-two-month-calendar {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    width: 100%;
}
.vs-month-box {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 16px;
}
.vs-month-title {
    text-align: center;
    font-weight: 800;
    margin-bottom: 12px;
}
.vs-calendar-weekdays, .vs-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 5px;
}
.vs-calendar-weekdays { margin-bottom: 6px; }
.vs-calendar-weekdays > div { text-align:center; font-size:11px; font-weight:700; color:#667085; }
.vs-calendar-day { min-height:68px; padding:5px 2px; border:1px solid #e5e7eb; border-radius:9px; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; }
.vs-calendar-day:hover:not(:disabled) { border-color:#2563eb; background:#f8fbff; }
.vs-calendar-day.has-flight { background:#f7fff9; }
.vs-calendar-day.selected { border:2px solid #2563eb; background:#eff6ff; }
.vs-calendar-day.no-flight, .vs-calendar-day.disabled { opacity:.45; cursor:not-allowed; background:#f8fafc; }
.vs-calendar-day.empty { border:none; background:transparent; cursor:default; }
.vs-calendar-day-number { font-weight:800; font-size:13px; }
.vs-calendar-price { margin-top:3px; font-size:10px; font-weight:800; color:#067647; }
.vs-calendar-flight-count { font-size:8px; color:#667085; }
.vs-calendar-no-flight { margin-top:4px; font-size:8px; color:#98a2b3; }
.vs-calendar-loading { padding:50px 20px; text-align:center; color:#667085; }
@media(max-width:850px){ .vs-two-month-calendar{grid-template-columns:1fr;} }
</style>
</head>


<body>


<!-- ============================================================
     NAVBAR
============================================================ -->

<header class="vs-navbar">

    <a
        href="index.php"
        class="vs-logo"
    >

        <span class="vs-logo-icon">
            ✈
        </span>

        <span class="vs-logo-text">
            Vektor<span>Sky</span>
        </span>

    </a>


    <nav class="vs-nav-right">

        <a
            href="index.php"
            class="vs-nav-link"
        >
            Flights
        </a>


        <?php if ($isLoggedIn): ?>

            <a
                href="my_bookings.php"
                class="vs-nav-link"
            >
                My Bookings
            </a>

            <a
                href="logout.php"
                class="vs-nav-button"
            >
                Logout
            </a>

        <?php else: ?>

            <a
                href="login.php"
                class="vs-nav-link"
            >
                Login
            </a>

            <a
                href="register.php"
                class="vs-nav-button"
            >
                Create Account
            </a>

        <?php endif; ?>

    </nav>

</header>


<!-- ============================================================
     HERO
============================================================ -->

<section class="vs-hero">

    <div class="vs-hero-content">

        <div class="vs-badge">
            ✨ Smart flight booking
        </div>

        <h1>
            Your journey starts
            <span>here.</span>
        </h1>

        <p class="vs-hero-subtitle">

            Search thousands of airports,
            discover available fares,
            and book your next journey
            with VektorSky.

        </p>

    </div>

</section>


<!-- ============================================================
     SEARCH
============================================================ -->

<div class="vs-search-wrapper">

<div class="vs-search-card">


    <div class="vs-search-tabs">

        <button
            type="button"
            class="vs-search-tab <?= $tripType !== 'oneway' ? 'active' : '' ?>"
            id="roundTripTab"
            data-trip-type="round"
        >
            ✈ Round Trip
        </button>

        <button
            type="button"
            class="vs-search-tab <?= $tripType === 'oneway' ? 'active' : '' ?>"
            id="oneWayTab"
            data-trip-type="oneway"
        >
            One Way
        </button>

        <input
            type="hidden"
            name="trip_type"
            id="tripType"
            value="<?= $tripType === 'oneway' ? 'oneway' : 'round' ?>"
        >

    </div>


    <form
        action="search_results.php"
        method="GET"
        id="flightSearchForm"
    >

        <div class="vs-search-grid">


            <!-- ==================================================
                 FROM
            =================================================== -->

            <div class="vs-field">

                <label>
                    FROM
                </label>

                <div
                    class="vs-airport-input-wrapper"
                >

                    <input
                        type="text"
                        id="fromAirportSearch"
                        placeholder="City or airport"
                        autocomplete="off"
                        required
                    >

                    <input
                        type="hidden"
                        name="from"
                        id="fromAirport"
                        value="<?= htmlspecialchars(
                            $from,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div
                        class="vs-airport-suggestions"
                        id="fromSuggestions"
                    ></div>

                </div>


                <button
                    type="button"
                    class="vs-swap"
                    id="swapButton"
                    title="Swap airports"
                >
                    ⇄
                </button>

            </div>


            <!-- ==================================================
                 TO
            =================================================== -->

            <div class="vs-field">

                <label>
                    TO
                </label>

                <div
                    class="vs-airport-input-wrapper"
                >

                    <input
                        type="text"
                        id="toAirportSearch"
                        placeholder="City or airport"
                        autocomplete="off"
                        required
                    >

                    <input
                        type="hidden"
                        name="to"
                        id="toAirport"
                        value="<?= htmlspecialchars(
                            $to,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div
                        class="vs-airport-suggestions"
                        id="toSuggestions"
                    ></div>

                </div>

            </div>


            <!-- ==================================================
                 DATE
            =================================================== -->

            <div class="vs-field">

                <label>
                    DEPARTURE
                </label>

                <button
                    type="button"
                    class="vs-date-picker-button"
                    id="departurePicker"
                >

                    <span class="vs-date-icon">
                        📅
                    </span>

                    <span
                        id="departureText"
                        class="vs-date-text placeholder"
                    >
                        Select date
                    </span>

                </button>

                <input
                    type="hidden"
                    name="departure"
                    id="departureDate"
                    value="<?= htmlspecialchars(
                        $departure,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >

                <input
                    type="hidden"
                    name="date"
                    id="searchDate"
                    value="<?= htmlspecialchars(
                        $departure,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

            </div>


            <!-- ==================================================
                 RETURN
            =================================================== -->

            <div
                class="vs-field"
                id="returnField"
            >

                <label>
                    RETURN
                </label>

                <button
                    type="button"
                    class="vs-date-picker-button"
                    id="returnPicker"
                >

                    <span class="vs-date-icon">
                        📅
                    </span>

                    <span
                        id="returnText"
                        class="vs-date-text placeholder"
                    >
                        Select return date
                    </span>

                </button>

                <input
                    type="hidden"
                    name="return"
                    id="returnDate"
                    value="<?= htmlspecialchars(
                        $return,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

            </div>


            <!-- ==================================================
                 TRAVELLERS
            =================================================== -->

            <div class="vs-field">

                <label>
                    TRAVELLERS
                </label>

                <select
                    class="vs-select"
                    name="travellers"
                    id="travellers"
                >

                    <?php for (
                        $i = 1;
                        $i <= 9;
                        $i++
                    ): ?>

                        <option
                            value="<?= $i ?>"
                            <?= $travellers === $i
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= $i ?>

                            <?= $i === 1
                                ? 'Traveller'
                                : 'Travellers'
                            ?>

                        </option>

                    <?php endfor; ?>

                </select>

            </div>


            <!-- ==================================================
                 CLASS
            =================================================== -->

            <div class="vs-field">

                <label>
                    CLASS
                </label>

                <select
                    class="vs-select"
                    name="class"
                    id="travelClass"
                >

                    <option
                        value="Economy"
                        <?= $travelClass === 'Economy'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Economy
                    </option>

                    <option
                        value="Premium Economy"
                        <?= $travelClass === 'Premium Economy'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Premium Economy
                    </option>

                    <option
                        value="Business"
                        <?= $travelClass === 'Business'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Business
                    </option>

                    <option
                        value="First"
                        <?= $travelClass === 'First'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        First
                    </option>

                </select>

            </div>


            <!-- ==================================================
                 SEARCH BUTTON
            =================================================== -->

            <div class="vs-field">

                <label>
                    &nbsp;
                </label>

                <button
                    type="submit"
                    class="vs-search-button"
                >
                    🔍 Search Flights
                </button>

            </div>


        </div>

    </form>

</div>

</div>


<!-- ============================================================
     FEATURES
============================================================ -->

<section class="vs-features">

    <div class="vs-section-title">

        <h2>
            Travel smarter
        </h2>

        <p>
            A simple and modern flight booking experience.
        </p>

    </div>


    <div class="vs-feature-grid">


        <div class="vs-feature-card">

            <div class="vs-feature-icon">
                🔎
            </div>

            <h3>
                Smart Airport Search
            </h3>

            <p>
                Search by airport code,
                city, airport name,
                or alternative name.
            </p>

        </div>


        <div class="vs-feature-card">

            <div class="vs-feature-icon">
                💰
            </div>

            <h3>
                Fare Calendar
            </h3>

            <p>
                See available flight dates
                and the lowest fare directly
                inside the calendar.
            </p>

        </div>


        <div class="vs-feature-card">

            <div class="vs-feature-icon">
                🔐
            </div>

            <h3>
                Secure Booking
            </h3>

            <p>
                Manage your passenger details,
                bookings and payments
                from one account.
            </p>

        </div>


    </div>

</section>


<!-- ============================================================
     CALENDAR
============================================================ -->

<div
    class="vs-calendar-overlay"
    id="calendarOverlay"
>

    <div
        class="vs-calendar"
        role="dialog"
        aria-modal="true"
    >


        <div class="vs-calendar-header">

            <div>

                <div
                    class="vs-calendar-title"
                    id="calendarMonth"
                >
                    September 2026
                </div>

                <div
                    class="vs-calendar-subtitle"
                    id="calendarRoute"
                >
                    Select your route
                </div>

            </div>


            <div class="vs-calendar-nav">

                <button
                    type="button"
                    id="calendarPrev"
                >
                    ‹
                </button>

                <button
                    type="button"
                    id="calendarNext"
                >
                    ›
                </button>

            </div>

        </div>


        <div class="vs-calendar-weekdays">

            <div class="vs-calendar-weekday">
                SUN
            </div>

            <div class="vs-calendar-weekday">
                MON
            </div>

            <div class="vs-calendar-weekday">
                TUE
            </div>

            <div class="vs-calendar-weekday">
                WED
            </div>

            <div class="vs-calendar-weekday">
                THU
            </div>

            <div class="vs-calendar-weekday">
                FRI
            </div>

            <div class="vs-calendar-weekday">
                SAT
            </div>

        </div>


        <div
            class="vs-calendar-days"
            id="calendarDays"
        ></div>


        <div class="vs-calendar-legend">

            <div class="vs-calendar-legend-item">

                <span
                    class="vs-calendar-legend-dot"
                ></span>

                Flight available

            </div>

            <div>
                Lowest fare
            </div>

        </div>


        <button
            type="button"
            class="vs-calendar-close"
            id="calendarClose"
        >
            Close
        </button>

    </div>

</div>


<!-- ============================================================
     FOOTER
============================================================ -->

<footer class="vs-footer">

    <strong>
        VektorSky
    </strong>

    &nbsp;·&nbsp;

    Smart Flight Booking System

    &nbsp;·&nbsp;

    <?= date('Y') ?>

</footer>


<script>

/* ============================================================
   AIRPORT SEARCH
============================================================ */

function setupAirportSearch(searchInput, hiddenInput, suggestionsBox) {
    if (!searchInput || !hiddenInput || !suggestionsBox) return;

    let timer = null;
    let controller = null;

    async function searchAirports(query) {
        if (controller) controller.abort();
        controller = new AbortController();

        const url =
            './api/airport_search.php?q=' +
            encodeURIComponent(query);

        const response = await fetch(url, {
            method: 'GET',
            cache: 'no-store',
            signal: controller.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Airport API HTTP ' + response.status);
        }

        const text = await response.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('airport_search.php returned non-JSON:', text);
            throw new Error('Airport API did not return JSON');
        }

        // Support the normal {success:true, airports:[...]} format
        // and also a plain JSON array.
        let airports = [];

        if (Array.isArray(data)) {
            airports = data;
        } else if (data && Array.isArray(data.airports)) {
            airports = data.airports;
        } else if (data && Array.isArray(data.data)) {
            airports = data.data;
        }

        return airports;
    }

    function closeSuggestions() {
        suggestionsBox.classList.remove('show');
    }

    function showMessage(message) {
        suggestionsBox.innerHTML = '';
        const div = document.createElement('div');
        div.style.cssText =
            'padding:15px;color:#64748b;font-size:12px;';
        div.textContent = message;
        suggestionsBox.appendChild(div);
        suggestionsBox.classList.add('show');
    }

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        // A manually changed airport is no longer a confirmed selection.
        hiddenInput.value = '';

        clearTimeout(timer);

        if (query.length < 2) {
            suggestionsBox.innerHTML = '';
            closeSuggestions();
            return;
        }

        timer = setTimeout(async () => {
            try {
                const airports = await searchAirports(query);

                // Ignore an old response if the user has already typed something else.
                if (searchInput.value.trim() !== query) return;

                suggestionsBox.innerHTML = '';

                if (!airports.length) {
                    showMessage('No airports found');
                    return;
                }

                airports.slice(0, 8).forEach(airport => {
                    const code = String(
                        airport.airport_code ??
                        airport.iata_code ??
                        airport.code ??
                        ''
                    ).trim().toUpperCase();

                    const name = String(
                        airport.airport_name ??
                        airport.name ??
                        ''
                    ).trim();

                    const city = String(
                        airport.city ??
                        airport.city_name ??
                        ''
                    ).trim();

                    const country = String(
                        airport.country ??
                        airport.country_name ??
                        ''
                    ).trim();

                    if (!code) return;

                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'vs-airport-option';

                    option.innerHTML = `
                        <span class="vs-airport-icon">✈️</span>
                        <span class="vs-airport-info">
                            <span class="vs-airport-name"></span>
                            <span class="vs-airport-city"></span>
                        </span>
                        <span class="vs-airport-code"></span>
                    `;

                    option.querySelector('.vs-airport-name').textContent =
                        name || code;

                    option.querySelector('.vs-airport-city').textContent =
                        [city, country].filter(Boolean).join(', ');

                    option.querySelector('.vs-airport-code').textContent =
                        code;

                    option.addEventListener('mousedown', e => {
                        // mousedown prevents the document click handler
                        // from closing the list before selection.
                        e.preventDefault();
                    });

                    option.addEventListener('click', () => {
                        hiddenInput.value = code;

                        searchInput.value =
                            name
                                ? `${code} - ${city || name}`
                                : code;

                        suggestionsBox.innerHTML = '';
                        closeSuggestions();

                        searchInput.dispatchEvent(
                            new Event('change', { bubbles: true })
                        );
                    });

                    suggestionsBox.appendChild(option);
                });

                if (suggestionsBox.children.length) {
                    suggestionsBox.classList.add('show');
                } else {
                    showMessage('No airports found');
                }

            } catch (error) {
                if (error.name === 'AbortError') return;
                console.error('Airport search error:', error);
                showMessage('Unable to load airport suggestions');
            }
        }, 180);
    });

    searchInput.addEventListener('focus', () => {
        if (suggestionsBox.children.length) {
            suggestionsBox.classList.add('show');
        }
    });

    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSuggestions();
        }
    });
}


/* ============================================================
   ESCAPE HTML
============================================================ */

function escapeHTML(value) {

    const div =
        document.createElement(
            'div'
        );

    div.textContent =
        value ?? '';

    return div.innerHTML;
}


/* ============================================================
   AIRPORT ELEMENTS
============================================================ */

const fromSearch =
    document.getElementById(
        'fromAirportSearch'
    );

const fromHidden =
    document.getElementById(
        'fromAirport'
    );

const fromSuggestions =
    document.getElementById(
        'fromSuggestions'
    );


const toSearch =
    document.getElementById(
        'toAirportSearch'
    );

const toHidden =
    document.getElementById(
        'toAirport'
    );

const toSuggestions =
    document.getElementById(
        'toSuggestions'
    );


setupAirportSearch(
    fromSearch,
    fromHidden,
    fromSuggestions
);


setupAirportSearch(
    toSearch,
    toHidden,
    toSuggestions
);

/* Restore selected airports when returning to this page with GET values. */
function restoreAirportDisplay(searchInput, hiddenInput) {
    if (!searchInput || !hiddenInput || !hiddenInput.value) return;

    // If the visible field is empty, show the selected IATA code.
    // The hidden value remains the canonical value sent to search_results.php.
    if (!searchInput.value.trim()) {
        searchInput.value = hiddenInput.value.toUpperCase();
    }
}

restoreAirportDisplay(fromSearch, fromHidden);
restoreAirportDisplay(toSearch, toHidden);



/* ============================================================
   SWAP
============================================================ */

const swapButton = document.getElementById('swapButton');

if (swapButton) {
    swapButton.addEventListener('click', function (event) {
        event.preventDefault();

        const visibleFrom = fromSearch?.value || '';
        const codeFrom = fromHidden?.value || '';

        const visibleTo = toSearch?.value || '';
        const codeTo = toHidden?.value || '';

        if (fromSearch) fromSearch.value = visibleTo;
        if (fromHidden) fromHidden.value = codeTo;

        if (toSearch) toSearch.value = visibleFrom;
        if (toHidden) toHidden.value = codeFrom;

        // Swapping airports changes the route, so reload calendar data.
        if (typeof loadFlightDates === 'function') {
            loadFlightDates();
        }
    });
}


/* ============================================================
   CLOSE AIRPORT DROPDOWNS
============================================================ */

document.addEventListener(
    'click',
    function (event) {

        if (
            !event.target.closest(
                '.vs-airport-input-wrapper'
            )
        ) {

            document
                .querySelectorAll(
                    '.vs-airport-suggestions'
                )
                .forEach(
                    function (box) {

                        box.classList.remove(
                            'show'
                        );

                    }
                );

        }

    }
);


/* ============================================================
   VEKTRORSKY FLIGHT CALENDAR
   Departure + Return / One Way
============================================================ */
(function () {
    'use strict';

    const departurePicker = document.getElementById('departurePicker');
    const departureDate = document.getElementById('departureDate');
    const departureText = document.getElementById('departureText');

    const returnPicker = document.getElementById('returnPicker');
    const returnDate = document.getElementById('returnDate');
    const returnText = document.getElementById('returnText');
    const returnField = document.getElementById('returnField');
    const searchDate = document.getElementById('searchDate');

    const calendarOverlay = document.getElementById('calendarOverlay');
    const calendarDays = document.getElementById('calendarDays');
    const calendarMonth = document.getElementById('calendarMonth');
    const calendarRoute = document.getElementById('calendarRoute');
    const calendarPrev = document.getElementById('calendarPrev');
    const calendarNext = document.getElementById('calendarNext');
    const calendarClose = document.getElementById('calendarClose');

    const roundTripTab = document.getElementById('roundTripTab');
    const oneWayTab = document.getElementById('oneWayTab');
    const tripType = document.getElementById('tripType');
    const form = document.getElementById('flightSearchForm');

    let calendarDate = new Date();
    let flightDates = {};
    let calendarLoading = false;
    let calendarMode = 'departure';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    function formatDateValue(date) {
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0');
    }

    function formatDisplayDate(value) {
        if (!value) return 'Select date';
        return new Date(value + 'T00:00:00').toLocaleDateString('en-IN', {
            weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
        });
    }

    function getMonthName(year, month) {
        return new Date(year, month, 1).toLocaleDateString('en-IN', {
            month: 'long', year: 'numeric'
        });
    }

    function getAirportValue(el) {
        return el ? (el.value || '').trim().toUpperCase() : '';
    }

    function setTripType(type) {
        if (!tripType) return;
        tripType.value = type;

        const round = type === 'round';
        roundTripTab?.classList.toggle('active', round);
        oneWayTab?.classList.toggle('active', !round);

        if (returnField) returnField.style.display = round ? '' : 'none';

        if (!round && returnDate) {
            returnDate.value = '';
            if (returnText) {
                returnText.textContent = 'Select return date';
                returnText.classList.add('placeholder');
            }
        }
    }

    roundTripTab?.addEventListener('click', () => setTripType('round'));
    oneWayTab?.addEventListener('click', () => setTripType('oneway'));

    setTripType(tripType?.value === 'oneway' ? 'oneway' : 'round');

    async function loadFlightDates() {
        const normalFrom = getAirportValue(document.getElementById('fromAirport'));
        const normalTo = getAirportValue(document.getElementById('toAirport'));

        const from = calendarMode === 'return' ? normalTo : normalFrom;
        const to = calendarMode === 'return' ? normalFrom : normalTo;

        flightDates = {};

        if (!from || !to) {
            if (calendarRoute) {
                calendarRoute.textContent = 'Select From and To airports first';
            }
            calendarLoading = false;
            renderCalendar();
            return;
        }

        if (from === to) {
            if (calendarRoute) {
                calendarRoute.textContent =
                    'Departure and destination must be different';
            }
            calendarLoading = false;
            renderCalendar();
            return;
        }

        if (calendarRoute) {
            calendarRoute.textContent =
                (calendarMode === 'return' ? 'Return: ' : 'Departure: ') +
                from + ' → ' + to;
        }

        calendarLoading = true;
        renderCalendar();

        try {
            // Load a 3-month window starting from the currently displayed month.
            // This fixes the problem where prices disappeared after pressing Next.
            const start = new Date(
                calendarDate.getFullYear(),
                calendarDate.getMonth(),
                1
            );

            const end = new Date(
                calendarDate.getFullYear(),
                calendarDate.getMonth() + 3,
                0
            );

            const fromValue = encodeURIComponent(from);
            const toValue = encodeURIComponent(to);

            const url =
                './api/flight_calendar.php' +
                '?from=' + fromValue +
                '&to=' + toValue +
                '&start=' + formatDateValue(start) +
                '&end=' + formatDateValue(end);

            const response = await fetch(url, {
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Flight calendar HTTP ' + response.status);
            }

            const text = await response.text();

            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error('flight_calendar.php returned non-JSON:', text);
                throw new Error('Flight calendar did not return JSON');
            }

            if (data && data.dates && typeof data.dates === 'object') {
                flightDates = data.dates;
            } else if (data && data.data && typeof data.data === 'object') {
                flightDates = data.data;
            } else {
                flightDates = {};
            }

        } catch (error) {
            console.error('Flight calendar error:', error);
            flightDates = {};
        }

        calendarLoading = false;
        renderCalendar();
    }

    function openCalendar(mode) {
        calendarMode = mode;
        if (calendarOverlay) calendarOverlay.classList.add('show');

        const selectedValue = mode === 'return'
            ? returnDate?.value
            : departureDate?.value;

        if (selectedValue) {
            const d = new Date(selectedValue + 'T00:00:00');
            if (!isNaN(d.getTime())) calendarDate = new Date(d.getFullYear(), d.getMonth(), 1);
        } else if (mode === 'return' && departureDate?.value) {
            const d = new Date(departureDate.value + 'T00:00:00');
            calendarDate = new Date(d.getFullYear(), d.getMonth(), 1);
        } else {
            calendarDate = new Date(today.getFullYear(), today.getMonth(), 1);
        }

        loadFlightDates();
    }

    departurePicker?.addEventListener('click', e => {
        e.preventDefault();
        openCalendar('departure');
    });

    returnPicker?.addEventListener('click', e => {
        e.preventDefault();
        if (tripType?.value !== 'round') return;
        openCalendar('return');
    });

    calendarClose?.addEventListener('click', () => calendarOverlay?.classList.remove('show'));

    calendarOverlay?.addEventListener('click', e => {
        if (e.target === calendarOverlay) calendarOverlay.classList.remove('show');
    });

    function renderCalendar() {
        if (!calendarDays) return;
        calendarDays.innerHTML = '';

        if (calendarLoading) {
            const loading = document.createElement('div');
            loading.className = 'vs-calendar-loading';
            loading.textContent = 'Loading flight prices...';
            calendarDays.appendChild(loading);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'vs-two-month-calendar';
        wrapper.appendChild(createMonthCalendar(calendarDate.getFullYear(), calendarDate.getMonth()));

        const next = new Date(calendarDate.getFullYear(), calendarDate.getMonth() + 1, 1);
        wrapper.appendChild(createMonthCalendar(next.getFullYear(), next.getMonth()));
        calendarDays.appendChild(wrapper);

        if (calendarMonth) {
            calendarMonth.textContent = getMonthName(calendarDate.getFullYear(), calendarDate.getMonth()) +
                ' — ' + getMonthName(next.getFullYear(), next.getMonth());
        }
    }

    function createMonthCalendar(year, month) {
        const box = document.createElement('div');
        box.className = 'vs-month-box';

        const title = document.createElement('div');
        title.className = 'vs-month-title';
        title.textContent = getMonthName(year, month);
        box.appendChild(title);

        const weekdays = document.createElement('div');
        weekdays.className = 'vs-calendar-weekdays';
        ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(name => {
            const d = document.createElement('div');
            d.textContent = name;
            weekdays.appendChild(d);
        });
        box.appendChild(weekdays);

        const grid = document.createElement('div');
        grid.className = 'vs-calendar-grid';

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement('div');
            empty.className = 'vs-calendar-day empty';
            grid.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            date.setHours(0,0,0,0);
            const value = formatDateValue(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'vs-calendar-day';

            const number = document.createElement('span');
            number.className = 'vs-calendar-day-number';
            number.textContent = day;
            button.appendChild(number);

            const flightInfo = flightDates[value];
            const hasFlight = flightInfo && Number(flightInfo.flight_count || 0) > 0;

            const isDepartureSelected = departureDate?.value === value;
            const isReturnSelected = returnDate?.value === value;

            if (date < today) button.disabled = true;
            if (value === formatDateValue(today)) button.classList.add('today');
            if (isDepartureSelected || isReturnSelected) button.classList.add('selected');

            if (calendarMode === 'return' && departureDate?.value && value <= departureDate.value) {
                button.disabled = true;
                button.classList.add('disabled');
            }

            if (hasFlight && !button.disabled) {
                button.classList.add('has-flight');

                const price = document.createElement('span');
                price.className = 'vs-calendar-price';
                price.textContent = '₹' + Number(flightInfo.price).toLocaleString('en-IN');
                button.appendChild(price);

                const count = document.createElement('span');
                count.className = 'vs-calendar-flight-count';
                const total = Number(flightInfo.flight_count || 0);
                count.textContent = total + (total === 1 ? ' flight' : ' flights');
                button.appendChild(count);

                button.addEventListener('click', () => selectDate(value));
            } else if (!hasFlight) {
                const noFlight = document.createElement('span');
                noFlight.className = 'vs-calendar-no-flight';
                noFlight.textContent = 'No flights';
                button.appendChild(noFlight);
                button.classList.add('no-flight');
                button.disabled = true;
            }

            grid.appendChild(button);
        }

        box.appendChild(grid);
        return box;
    }

    function selectDate(value) {
        if (calendarMode === 'departure') {
            departureDate.value = value;
            if (searchDate) searchDate.value = value;
            departureText.textContent = formatDisplayDate(value);
            departureText.classList.remove('placeholder');

            if (tripType?.value === 'round') {
                returnDate.value = '';
                returnText.textContent = 'Select return date';
                returnText.classList.add('placeholder');
                calendarMode = 'return';
                if (calendarRoute) calendarRoute.textContent = 'Select your return date';
                loadFlightDates();
                return;
            }
        } else {
            returnDate.value = value;
            returnText.textContent = formatDisplayDate(value);
            returnText.classList.remove('placeholder');
        }

        calendarOverlay?.classList.remove('show');
        renderCalendar();
    }

    calendarPrev?.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        calendarDate.setMonth(calendarDate.getMonth() - 1);
        loadFlightDates();
    });

    calendarNext?.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        calendarDate.setMonth(calendarDate.getMonth() + 1);
        loadFlightDates();
    });

    form?.addEventListener('submit', e => {
        if (!departureDate?.value) {
            e.preventDefault();
            alert('Please select a departure date.');
            openCalendar('departure');
            return;
        }

        if (tripType?.value === 'round') {
            if (!returnDate?.value) {
                e.preventDefault();
                alert('Please select a return date.');
                openCalendar('return');
                return;
            }

            if (returnDate.value <= departureDate.value) {
                e.preventDefault();
                alert('Return date must be after the departure date.');
                openCalendar('return');
                return;
            }
        }
    });

    if (departureDate?.value && departureText) {
        departureText.textContent = formatDisplayDate(departureDate.value);
        departureText.classList.remove('placeholder');
    }
    if (returnDate?.value && returnText) {
        returnText.textContent = formatDisplayDate(returnDate.value);
        returnText.classList.remove('placeholder');
    }

    renderCalendar();
})()
;