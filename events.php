<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$clubId = $_GET['club_id'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$sql = "SELECT e.title, e.start_time, e.end_time, e.venue, c.name AS club_name
        FROM events e
        JOIN clubs c ON c.club_id = e.club_id
        WHERE e.status = 'Published'
        AND c.status = 'Approved'
        AND e.start_time > NOW()";
$params = [];

if ($clubId !== '') { $sql .= " AND e.club_id = ?"; $params[] = $clubId; }
if ($from !== '')   { $sql .= " AND DATE(e.start_time) >= ?"; $params[] = $from; }
if ($to !== '')     { $sql .= " AND DATE(e.start_time) <= ?"; $params[] = $to; }
$sql .= " ORDER BY e.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered = ($clubId !== '' || $from !== '' || $to !== '');
$clubs = $conn->query("SELECT club_id, name FROM clubs WHERE status = 'Approved' ORDER BY name")
              ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Events | FST Club Hub</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main>
<div class="container">
    <h2>Upcoming Events</h2>

    <form method="get" class="search-bar">
        <select name="club_id">
            <option value="">All clubs</option>
            <?php foreach ($clubs as $c): ?>
                <option value="<?= $c['club_id'] ?>" <?= $clubId == $c['club_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        <button type="submit" class="btn">Filter</button>
    </form>

    <div class="cards">
    <?php if (!$events): ?>
        <p><?= $filtered ? "No events match your filters" : "No upcoming events" ?></p>
    <?php else: ?>
        <?php foreach ($events as $e): ?>
            <div class="card">
                <h3><?= htmlspecialchars($e['title']) ?></h3>
                <p>Club: <?= htmlspecialchars($e['club_name']) ?></p>
                <p>When: <?= date('D j M Y, g:i A', strtotime($e['start_time'])) ?></p>
                <p>Venue: <?= htmlspecialchars($e['venue']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>
</main>
</body>
</html>