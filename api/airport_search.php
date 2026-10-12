<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {

    $q = trim($_GET['q'] ?? '');

    if ($q === '') {

        echo json_encode([
            'success' => true,
            'airports' => []
        ]);

        exit;
    }

    $search = '%' . $q . '%';

    $sql = "
        SELECT
            airport_id,
            airport_code,
            airport_name,
            city,
            country
        FROM airport
        WHERE
            airport_code LIKE :code
            OR airport_name LIKE :name
            OR city LIKE :city
            OR country LIKE :country
        ORDER BY
            CASE
                WHEN LOWER(airport_code) = LOWER(:exact_code)
                    THEN 0

                WHEN LOWER(city) = LOWER(:exact_city)
                    THEN 1

                WHEN LOWER(city) LIKE LOWER(:city_prefix)
                    THEN 2

                WHEN LOWER(airport_name) LIKE LOWER(:name_prefix)
                    THEN 3

                ELSE 4
            END,
            city,
            airport_name
        LIMIT 15
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':code' =>
            $search,

        ':name' =>
            $search,

        ':city' =>
            $search,

        ':country' =>
            $search,

        ':exact_code' =>
            $q,

        ':exact_city' =>
            $q,

        ':city_prefix' =>
            $q . '%',

        ':name_prefix' =>
            $q . '%'

    ]);

    $airports =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    echo json_encode([

        'success' => true,

        'airports' => $airports

    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'airports' => [],

        'message' => $e->getMessage()

    ]);

}