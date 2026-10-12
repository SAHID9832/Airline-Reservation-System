-- =====================================================
-- VECTOR SKY - Airline Reservation System
-- Database Schema (MySQL)
-- Based on the ADBMS Airline Reservation System design
-- =====================================================

DROP DATABASE IF EXISTS vector_sky;
CREATE DATABASE vector_sky;
USE vector_sky;

-- =====================================================
-- TABLE: PASSENGER  (also acts as the login account)
-- =====================================================
CREATE TABLE PASSENGER (
    passenger_id   INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(100) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    phone          VARCHAR(15),
    passport_no    VARCHAR(20) UNIQUE,
    date_of_birth  DATE,
    gender         VARCHAR(10),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- TABLE: AIRPORT
-- =====================================================
CREATE TABLE AIRPORT (
    airport_id     INT AUTO_INCREMENT PRIMARY KEY,
    airport_code   VARCHAR(10) NOT NULL UNIQUE,
    airport_name   VARCHAR(100) NOT NULL,
    city           VARCHAR(50) NOT NULL,
    country        VARCHAR(50) NOT NULL
);

-- =====================================================
-- TABLE: AIRCRAFT
-- =====================================================
CREATE TABLE AIRCRAFT (
    aircraft_id    INT AUTO_INCREMENT PRIMARY KEY,
    aircraft_no    VARCHAR(20) NOT NULL UNIQUE,
    model          VARCHAR(50) NOT NULL,
    total_seats    INT NOT NULL CHECK (total_seats > 0)
);

-- =====================================================
-- TABLE: FLIGHT
-- =====================================================
CREATE TABLE FLIGHT (
    flight_id             INT AUTO_INCREMENT PRIMARY KEY,
    flight_number         VARCHAR(20) NOT NULL UNIQUE,
    airline_name          VARCHAR(50) NOT NULL,
    source_airport_id     INT NOT NULL,
    destination_airport_id INT NOT NULL,
    aircraft_id           INT NOT NULL,
    departure_time        DATETIME NOT NULL,
    arrival_time          DATETIME NOT NULL,
    fare                  DECIMAL(10,2) NOT NULL CHECK (fare >= 0),
    FOREIGN KEY (source_airport_id) REFERENCES AIRPORT(airport_id),
    FOREIGN KEY (destination_airport_id) REFERENCES AIRPORT(airport_id),
    FOREIGN KEY (aircraft_id) REFERENCES AIRCRAFT(aircraft_id),
    CHECK (source_airport_id <> destination_airport_id)
);

-- =====================================================
-- TABLE: SEAT
-- =====================================================
CREATE TABLE SEAT (
    seat_id       INT AUTO_INCREMENT PRIMARY KEY,
    aircraft_id   INT NOT NULL,
    seat_number   VARCHAR(10) NOT NULL,
    seat_class    VARCHAR(20) NOT NULL DEFAULT 'Economy',
    status        VARCHAR(20) NOT NULL DEFAULT 'Available',
    FOREIGN KEY (aircraft_id) REFERENCES AIRCRAFT(aircraft_id),
    UNIQUE KEY uniq_seat_per_aircraft (aircraft_id, seat_number)
);

-- =====================================================
-- TABLE: BOOKING
-- Note: seat availability is tracked PER FLIGHT via this
-- table (status), while SEAT.status reflects the seat map
-- defined on the aircraft itself.
-- =====================================================
CREATE TABLE BOOKING (
    booking_id     INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id   INT NOT NULL,
    flight_id      INT NOT NULL,
    seat_id        INT NOT NULL,
    booking_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status         VARCHAR(20) NOT NULL DEFAULT 'Pending', -- Pending, Confirmed, Cancelled
    total_amount   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (passenger_id) REFERENCES PASSENGER(passenger_id),
    FOREIGN KEY (flight_id) REFERENCES FLIGHT(flight_id),
    FOREIGN KEY (seat_id) REFERENCES SEAT(seat_id),
    UNIQUE KEY uniq_seat_per_flight (flight_id, seat_id, status)
);

-- =====================================================
-- TABLE: PAYMENT  (1:1 with BOOKING)
-- =====================================================
CREATE TABLE PAYMENT (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    booking_id      INT NOT NULL UNIQUE,
    payment_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  VARCHAR(20) NOT NULL,
    payment_status  VARCHAR(20) NOT NULL DEFAULT 'Success',
    FOREIGN KEY (booking_id) REFERENCES BOOKING(booking_id)
);

-- =====================================================
-- INDEXES (Phase 3 - query optimization)
-- =====================================================
CREATE INDEX idx_flight_source ON FLIGHT(source_airport_id);
CREATE INDEX idx_flight_dest ON FLIGHT(destination_airport_id);
CREATE INDEX idx_booking_flight ON BOOKING(flight_id);
CREATE INDEX idx_booking_passenger ON BOOKING(passenger_id);

-- =====================================================
-- VIEW: Flight_Booking_Summary
-- =====================================================
CREATE VIEW Flight_Booking_Summary AS
SELECT
    f.flight_number,
    f.airline_name,
    src.city  AS source_city,
    dst.city  AS destination_city,
    p.name    AS passenger_name,
    s.seat_number,
    b.status  AS booking_status,
    f.fare
FROM BOOKING b
JOIN FLIGHT f    ON b.flight_id = f.flight_id
JOIN AIRPORT src ON f.source_airport_id = src.airport_id
JOIN AIRPORT dst ON f.destination_airport_id = dst.airport_id
JOIN PASSENGER p ON b.passenger_id = p.passenger_id
JOIN SEAT s      ON b.seat_id = s.seat_id;

-- =====================================================
-- FUNCTION: GetPassengerBookingCount
-- =====================================================
DELIMITER //
CREATE FUNCTION GetPassengerBookingCount(p_passenger_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE cnt INT;
    SELECT COUNT(*) INTO cnt FROM BOOKING WHERE passenger_id = p_passenger_id;
    RETURN cnt;
END //
DELIMITER ;

-- =====================================================
-- PROCEDURE: AddBooking
-- Handles the transaction: insert booking + mark seat
-- taken for that flight, with rollback on failure.
-- =====================================================
DELIMITER //
CREATE PROCEDURE AddBooking(
    IN p_passenger_id INT,
    IN p_flight_id INT,
    IN p_seat_id INT,
    OUT p_booking_id INT
)
BEGIN
    DECLARE v_fare DECIMAL(10,2);
    DECLARE v_taken INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_taken
    FROM BOOKING
    WHERE flight_id = p_flight_id AND seat_id = p_seat_id AND status IN ('Pending','Confirmed')
    FOR UPDATE;

    IF v_taken > 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seat already booked for this flight';
    ELSE
        SELECT fare INTO v_fare FROM FLIGHT WHERE flight_id = p_flight_id;

        SAVEPOINT before_insert;

        INSERT INTO BOOKING (passenger_id, flight_id, seat_id, status, total_amount)
        VALUES (p_passenger_id, p_flight_id, p_seat_id, 'Pending', v_fare);

        SET p_booking_id = LAST_INSERT_ID();

        COMMIT;
    END IF;
END //
DELIMITER ;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

INSERT INTO AIRPORT (airport_code, airport_name, city, country) VALUES
('DEL', 'Indira Gandhi International', 'Delhi', 'India'),
('BLR', 'Kempegowda International', 'Bengaluru', 'India'),
('BOM', 'Chhatrapati Shivaji Maharaj Intl', 'Mumbai', 'India'),
('MAA', 'Chennai International', 'Chennai', 'India'),
('CCU', 'Netaji Subhas Chandra Bose Intl', 'Kolkata', 'India'),
('HYD', 'Rajiv Gandhi International', 'Hyderabad', 'India');

INSERT INTO AIRCRAFT (aircraft_no, model, total_seats) VALUES
('VS-A320-01', 'Airbus A320', 12),
('VS-B737-01', 'Boeing 737', 12),
('VS-A321-01', 'Airbus A321', 12);

-- Seats for each aircraft: rows 1-3, seats A-D (2 classes)
INSERT INTO SEAT (aircraft_id, seat_number, seat_class, status)
SELECT a.aircraft_id, CONCAT(n, l), IF(n=1,'Business','Economy'), 'Available'
FROM AIRCRAFT a
JOIN (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3) rows_
JOIN (SELECT 'A' AS l UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D') cols_;

INSERT INTO FLIGHT (flight_number, airline_name, source_airport_id, destination_airport_id, aircraft_id, departure_time, arrival_time, fare) VALUES
('VS101', 'Vector Sky', 1, 2, 1, '2026-09-05 06:00:00', '2026-09-05 08:45:00', 4999.00),
('VS102', 'Vector Sky', 2, 1, 2, '2026-09-05 19:00:00', '2026-09-05 21:45:00', 5299.00),
('VS201', 'Vector Sky', 3, 4, 3, '2026-09-06 09:15:00', '2026-09-06 11:00:00', 3899.00),
('VS202', 'Vector Sky', 4, 3, 1, '2026-09-06 17:30:00', '2026-09-06 19:15:00', 3999.00),
('VS301', 'Vector Sky', 1, 3, 2, '2026-09-07 07:00:00', '2026-09-07 09:15:00', 4599.00),
('VS302', 'Vector Sky', 5, 6, 3, '2026-09-07 13:00:00', '2026-09-07 14:45:00', 3599.00),
('VS401', 'Vector Sky', 2, 4, 1, '2026-09-08 10:30:00', '2026-09-08 12:00:00', 3299.00),
('VS402', 'Vector Sky', 6, 2, 2, '2026-09-08 20:00:00', '2026-09-08 22:00:00', 4199.00);

-- Demo passenger login: email demo@vectorsky.com / password "password123"
INSERT INTO PASSENGER (name, email, password_hash, phone, passport_no, date_of_birth, gender)
VALUES ('Demo User', 'demo@vectorsky.com', '$2b$10$Cei10QhR1qR486BoghRHxeIpkGN7v4QxrTkX4ssjtFVnUHWXrU7/.', '9999999999', 'P1234567', '1995-01-01', 'Other');
