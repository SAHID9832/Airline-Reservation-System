# Vector Sky ✈️ — Flight Booking System (PHP + MySQL)

A flight-search-and-booking web app, built around the Airline Reservation System
schema (PASSENGER, AIRPORT, AIRCRAFT, SEAT, FLIGHT, BOOKING, PAYMENT).

## What's included

**Customer-facing site**
- Home page with a flight search form (city, city, date)
- Search results with live seat-availability counts
- Flight details page with an interactive seat map (Business/Economy, taken/available)
- Passenger sign up / login (bcrypt-hashed passwords, PHP sessions)
- Booking flow wrapped in a real MySQL **transaction** (checks seat isn't already
  taken, `SAVEPOINT`, `COMMIT`/`ROLLBACK`) to prevent double-booking a seat
- Payment page (simulated — no real payment gateway) that marks the booking Confirmed
- Booking confirmation page
- "My Bookings" page (view, pay pending bookings, cancel)

**Admin area** (`/admin`)
- Simple separate admin login
- Dashboard with aggregate stats: total bookings, total revenue, average fare,
  bookings-per-flight (JOIN + COUNT), flights priced above the average fare (subquery)
- Add new flights (writes to FLIGHT table against existing AIRPORT/AIRCRAFT rows)

**Database** (`db/schema.sql`)
- All 7 tables from the ER diagram, with PK/FK/UNIQUE/CHECK constraints, up to 3NF
- Indexes on `FLIGHT.source_airport_id`, `FLIGHT.destination_airport_id`,
  `BOOKING.flight_id`, `BOOKING.passenger_id`
- View: `Flight_Booking_Summary`
- Function: `GetPassengerBookingCount(passenger_id)`
- Stored procedure: `AddBooking(passenger_id, flight_id, seat_id, OUT booking_id)`
  (also used conceptually in `book.php`, which implements the same transaction
  logic directly in PHP for tighter error handling)
- Sample data: 6 airports, 3 aircraft, 12 seats per aircraft, 8 upcoming flights,
  and one demo passenger login

> Note: what I added beyond your original brief — passenger accounts/login,
> a payment step, a "My Bookings" page with cancellation, and a small admin
> section to add flights and view revenue/booking reports. All of this reuses
> the exact same 7 tables from your ER diagram; nothing extra was added to the
> schema itself.

## Requirements

- PHP 7.4+ (PHP 8.x recommended) with the **PDO MySQL** extension
- MySQL 5.7+ or MariaDB 10.3+
- Any local server stack works: XAMPP, MAMP, WAMP, Laragon, or PHP's built-in server

## How to run it

### 1. Import the database

Using phpMyAdmin: create nothing manually — just open phpMyAdmin, go to **Import**,
and select `db/schema.sql`. It creates the `vector_sky` database itself.

Or from the command line:

```bash
mysql -u root -p < db/schema.sql
```

This creates the `vector_sky` database, all tables, the view, function, procedure,
and sample data (including a demo login: `demo@vectorsky.com` / `password123`).

### 2. Set your database credentials

Open `config.php` and edit if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'vector_sky');
define('DB_USER', 'root');
define('DB_PASS', '');   // set your MySQL password here
```

### 3. Run the app

**Option A — XAMPP/MAMP/WAMP**
Copy the whole `vector_sky` folder into your server's `htdocs` (XAMPP) or `www`
(WAMP) directory, start Apache + MySQL from the control panel, then visit:

```
http://localhost/vector_sky/
```

**Option B — PHP's built-in server (fastest for testing)**
From inside the `vector_sky` folder:

```bash
php -S localhost:8000
```

Then visit `http://localhost:8000/` in your browser.

### 4. Log in

- **Customer demo login:** `demo@vectorsky.com` / `password123` (or click "Sign Up"
  to create your own account)
- **Admin login:** go to `/admin/login.php` → username `admin`, password `admin123`

## Folder structure

```
vector_sky/
├── db/schema.sql          Database: tables, view, function, procedure, sample data
├── config.php              DB connection + session helpers
├── index.php                Home page / flight search form
├── search_results.php       Search results with seat availability
├── flight_details.php       Flight details + interactive seat map
├── book.php                 Booking transaction handler
├── payment.php               Simulated payment page
├── confirmation.php          Booking confirmation page
├── my_bookings.php           Passenger's bookings (view/pay/cancel)
├── register.php / login.php / logout.php   Passenger auth
├── includes/header.php, footer.php          Shared layout
├── assets/css/style.css      Styling
└── admin/
    ├── login.php / logout.php / admin_guard.php
    ├── dashboard.php          Revenue & booking reports
    └── add_flight.php         Add new flights
```

## Security notes (this is a learning/demo project)

- Passwords are hashed with `password_hash()` / verified with `password_verify()`
- All SQL uses PDO prepared statements (no string-concatenated queries)
- Admin credentials are hard-coded for demo simplicity — change them (and move
  them out of source code) before using this anywhere beyond a class project
- The payment page is a simulation only; no real card/UPI processing happens
