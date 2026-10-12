<?php
require '../config.php';
require 'admin_guard.php';
$page_title = 'Add Flight';
$asset_prefix = '../';

$airports = $pdo->query("SELECT airport_id, city, airport_code FROM AIRPORT ORDER BY city")->fetchAll();
$aircraft = $pdo->query("SELECT aircraft_id, aircraft_no, model FROM AIRCRAFT ORDER BY model")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flight_number = trim($_POST['flight_number'] ?? '');
    $airline_name = trim($_POST['airline_name'] ?? '');
    $source = (int)($_POST['source'] ?? 0);
    $destination = (int)($_POST['destination'] ?? 0);
    $aircraft_id = (int)($_POST['aircraft_id'] ?? 0);
    $departure = $_POST['departure_time'] ?? '';
    $arrival = $_POST['arrival_time'] ?? '';
    $fare = $_POST['fare'] ?? '';

    if ($flight_number === '') $errors[] = 'Flight number is required.';
    if ($source === $destination) $errors[] = 'Source and destination must differ.';
    if (!is_numeric($fare) || $fare < 0) $errors[] = 'Fare must be a valid non-negative number.';
    if (!$departure || !$arrival) $errors[] = 'Departure and arrival times are required.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO FLIGHT (flight_number, airline_name, source_airport_id, destination_airport_id, aircraft_id, departure_time, arrival_time, fare)
                VALUES (:fn, :al, :src, :dst, :ac, :dep, :arr, :fare)
            ");
            $stmt->execute([
                'fn' => $flight_number, 'al' => $airline_name, 'src' => $source,
                'dst' => $destination, 'ac' => $aircraft_id, 'dep' => $departure,
                'arr' => $arrival, 'fare' => $fare,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Flight added successfully.'];
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Could not add flight (flight number may already exist).';
        }
    }
}

include '../includes/header.php';
?>
<div class="card" style="max-width:600px;">
    <h1>Add New Flight</h1>
    <?php foreach ($errors as $err): ?><div class="flash error"><?= h($err) ?></div><?php endforeach; ?>
    <form method="post">
        <div class="form-grid">
            <div class="form-row"><label>Flight Number</label><input type="text" name="flight_number" required></div>
            <div class="form-row"><label>Airline Name</label><input type="text" name="airline_name" value="Vector Sky" required></div>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <label>Source Airport</label>
                <select name="source" required>
                    <?php foreach ($airports as $a): ?>
                        <option value="<?= $a['airport_id'] ?>"><?= h($a['city']) ?> (<?= h($a['airport_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Destination Airport</label>
                <select name="destination" required>
                    <?php foreach ($airports as $a): ?>
                        <option value="<?= $a['airport_id'] ?>"><?= h($a['city']) ?> (<?= h($a['airport_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label>Aircraft</label>
            <select name="aircraft_id" required>
                <?php foreach ($aircraft as $ac): ?>
                    <option value="<?= $ac['aircraft_id'] ?>"><?= h($ac['model']) ?> (<?= h($ac['aircraft_no']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid">
            <div class="form-row"><label>Departure Time</label><input type="datetime-local" name="departure_time" required></div>
            <div class="form-row"><label>Arrival Time</label><input type="datetime-local" name="arrival_time" required></div>
        </div>
        <div class="form-row"><label>Fare (&#8377;)</label><input type="text" name="fare" required></div>
        <button type="submit" class="btn btn-block">Add Flight</button>
    </form>
</div>
<?php include '../includes/footer.php'; ?>
