<?php

require_once __DIR__ . '/../config.php';


// ============================================================
// FILES
// ============================================================

$airportFile =
    'D:\all project\vector_sky\css\airports.csv';

$coordinateFile =
    'D:\all project\vector_sky\css\airport_coordinates.csv';

$aircraftFile =
    'D:\all project\vector_sky\css\global_aircraft.csv';


// ============================================================
// CHECK FILES
// ============================================================

foreach ([
    $airportFile,
    $coordinateFile,
    $aircraftFile
] as $file) {

    if (!file_exists($file)) {

        die(
            "File not found: " .
            $file .
            PHP_EOL
        );

    }

}


echo PHP_EOL;
echo "=========================================" . PHP_EOL;
echo "       VektorSky CSV Importer" . PHP_EOL;
echo "=========================================" . PHP_EOL;


// ============================================================
// LOAD COORDINATES AS FALLBACK
// ============================================================

echo PHP_EOL;
echo "Loading airport coordinates..." . PHP_EOL;


$coordinates = [];


$handle =
    fopen(
        $coordinateFile,
        'r'
    );


$coordinateHeader =
    fgetcsv($handle);


$coordinateColumns =
    array_flip(
        $coordinateHeader
    );


while (
    ($row = fgetcsv($handle)) !== false
) {

    $code =
        strtoupper(
            trim(
                $row[
                    $coordinateColumns['airport_code']
                ] ?? ''
            )
        );


    if ($code === '') {
        continue;
    }


    $coordinates[$code] = [

        'latitude' =>
            trim(
                $row[
                    $coordinateColumns['latitude']
                ] ?? ''
            ),

        'longitude' =>
            trim(
                $row[
                    $coordinateColumns['longitude']
                ] ?? ''
            )

    ];

}


fclose($handle);


echo
    "Loaded " .
    count($coordinates) .
    " coordinate records."
    . PHP_EOL;


// ============================================================
// IMPORT AIRPORTS
// ============================================================

echo PHP_EOL;
echo "Importing airports..." . PHP_EOL;


$handle =
    fopen(
        $airportFile,
        'r'
    );


$header =
    fgetcsv($handle);


$columns =
    array_flip($header);


// Make sure required columns exist

$requiredColumns = [

    'name',
    'iso_country',
    'municipality',
    'icao_code',
    'iata_code',
    'keywords',
    'latitude_deg',
    'longitude_deg'

];


foreach ($requiredColumns as $column) {

    if (!isset($columns[$column])) {

        fclose($handle);

        die(
            "Missing CSV column: " .
            $column .
            PHP_EOL
        );

    }

}


// ============================================================
// PREPARE INSERT
// ============================================================

$insertAirport =
    $pdo->prepare("

        INSERT INTO airport

        (
            airport_code,
            airport_name,
            city,
            country,
            icao_code,
            latitude,
            longitude,
            keywords
        )

        VALUES

        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )

        ON DUPLICATE KEY UPDATE

            airport_name =
                VALUES(airport_name),

            city =
                VALUES(city),

            country =
                VALUES(country),

            icao_code =
                VALUES(icao_code),

            latitude =
                VALUES(latitude),

            longitude =
                VALUES(longitude),

            keywords =
                VALUES(keywords)

    ");


// ============================================================
// IMPORT
// ============================================================

$airportCount = 0;

$pdo->beginTransaction();


try {

    while (
        ($row = fgetcsv($handle)) !== false
    ) {

        // ----------------------------------------------------
        // IATA
        // ----------------------------------------------------

        $iata =
            strtoupper(
                trim(
                    $row[
                        $columns['iata_code']
                    ] ?? ''
                )
            );


        /*
        We only need airports with an IATA code
        for the passenger flight-booking search.
        */

        if ($iata === '') {

            continue;

        }


        // ----------------------------------------------------
        // NAME
        // ----------------------------------------------------

        $name =
            trim(
                $row[
                    $columns['name']
                ] ?? ''
            );


        if ($name === '') {

            continue;

        }


        // ----------------------------------------------------
        // CITY
        // ----------------------------------------------------

        $city =
            trim(
                $row[
                    $columns['municipality']
                ] ?? ''
            );


        // ----------------------------------------------------
        // COUNTRY
        // ----------------------------------------------------

        $country =
            trim(
                $row[
                    $columns['iso_country']
                ] ?? ''
            );


        // ----------------------------------------------------
        // ICAO
        // ----------------------------------------------------

        $icao =
            strtoupper(
                trim(
                    $row[
                        $columns['icao_code']
                    ] ?? ''
                )
            );


        // ----------------------------------------------------
        // KEYWORDS
        // ----------------------------------------------------

        $keywords =
            trim(
                $row[
                    $columns['keywords']
                ] ?? ''
            );


        /*
        Add city to searchable keywords.

        Example:

        Bengaluru
        Bangalore

        can both help find BLR.
        */

        $searchKeywords =
            trim(
                $city .
                ' ' .
                $keywords
            );


        // ----------------------------------------------------
        // COORDINATES
        // ----------------------------------------------------

        $latitude =
            trim(
                $row[
                    $columns['latitude_deg']
                ] ?? ''
            );


        $longitude =
            trim(
                $row[
                    $columns['longitude_deg']
                ] ?? ''
            );


        /*
        If airports.csv doesn't contain valid coordinates,
        use airport_coordinates.csv as fallback.
        */

        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude)
        ) {

            if (
                isset(
                    $coordinates[$iata]
                )
            ) {

                $latitude =
                    $coordinates[$iata]['latitude'];

                $longitude =
                    $coordinates[$iata]['longitude'];

            } else {

                $latitude =
                    null;

                $longitude =
                    null;

            }

        }


        // ----------------------------------------------------
        // INSERT
        // ----------------------------------------------------

        $insertAirport->execute([

            $iata,

            $name,

            $city,

            $country,

            $icao !== ''
                ? $icao
                : null,

            is_numeric($latitude)
                ? $latitude
                : null,

            is_numeric($longitude)
                ? $longitude
                : null,

            $searchKeywords

        ]);


        $airportCount++;


        if (
            $airportCount % 1000 === 0
        ) {

            echo
                "Imported " .
                $airportCount .
                " airports..."
                . PHP_EOL;

        }

    }


    $pdo->commit();


} catch (Throwable $e) {

    $pdo->rollBack();

    fclose($handle);

    die(
        "Airport import failed: " .
        $e->getMessage() .
        PHP_EOL
    );

}


fclose($handle);


echo
    "Airports imported: " .
    $airportCount .
    PHP_EOL;


// ============================================================
// AIRCRAFT
// ============================================================

echo PHP_EOL;
echo "Importing aircraft..." . PHP_EOL;


$handle =
    fopen(
        $aircraftFile,
        'r'
    );


$header =
    fgetcsv($handle);


$columns =
    array_flip($header);


$requiredAircraftColumns = [

    'aircraft_no',
    'model',
    'total_seats'

];


foreach (
    $requiredAircraftColumns
    as $column
) {

    if (!isset($columns[$column])) {

        fclose($handle);

        die(
            "Missing aircraft CSV column: " .
            $column .
            PHP_EOL
        );

    }

}


$insertAircraft =
    $pdo->prepare("

        INSERT INTO aircraft

        (
            aircraft_no,
            model,
            total_seats
        )

        VALUES

        (
            ?,
            ?,
            ?
        )

        ON DUPLICATE KEY UPDATE

            model =
                VALUES(model),

            total_seats =
                VALUES(total_seats)

    ");


$aircraftCount = 0;


$pdo->beginTransaction();


try {

    while (
        ($row = fgetcsv($handle)) !== false
    ) {

        $aircraftNo =
            trim(
                $row[
                    $columns['aircraft_no']
                ] ?? ''
            );


        $model =
            trim(
                $row[
                    $columns['model']
                ] ?? ''
            );


        $seats =
            (int)
            (
                $row[
                    $columns['total_seats']
                ] ?? 0
            );


        if (
            $aircraftNo === '' ||
            $model === '' ||
            $seats <= 0
        ) {

            continue;

        }


        $insertAircraft->execute([

            $aircraftNo,

            $model,

            $seats

        ]);


        $aircraftCount++;

    }


    $pdo->commit();


} catch (Throwable $e) {

    $pdo->rollBack();

    fclose($handle);

    die(
        "Aircraft import failed: " .
        $e->getMessage() .
        PHP_EOL
    );

}


fclose($handle);


echo
    "Aircraft imported: " .
    $aircraftCount .
    PHP_EOL;


// ============================================================
// COMPLETE
// ============================================================

echo PHP_EOL;

echo "=========================================" . PHP_EOL;

echo "       CSV IMPORT COMPLETED" . PHP_EOL;

echo "=========================================" . PHP_EOL;

echo PHP_EOL;