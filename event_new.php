<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'database.php';

$userId = $_SESSION['user_id'] ?? null;
$role   = $_SESSION['role'] ?? null;

// Same check style as the other handlers in index.php
if ($userId === null || $role !== 'Representative') {
    http_response_code(403);
    exit('You must be logged in as a club representative to create events.');
}

$errors = [];
$success = '';

// Find the approved club that this representative leads
$stmt = $conn->prepare("SELECT club_id, name FROM clubs WHERE leader_id = :uid AND status = 'Approved'");
$stmt->execute(['uid' => $userId]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    $errors[] = "You can only create events for your own approved club.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $club) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = $_POST['event_date'] ?? '';
    $start       = $_POST['start_time'] ?? '';
    $end         = $_POST['end_time'] ?? '';
    $venue       = trim($_POST['venue'] ?? '');
    $status      = ($_POST['action'] ?? '') === 'publish' ? 'Published' : 'Draft';

    if (mb_strlen($title) < 3 || mb_strlen($title) > 100) $errors[] = "Title must be 3 to 100 characters.";
    if ($description === '') $errors[] = "Description is required.";
    if ($venue === '') $errors[] = "Venue is required.";

    if ($date === '' || $start === '' || $end === '') {
        $errors[] = "Date, start time and end time are required.";
    } else {
        $startDT = strtotime("$date $start");
        $endDT   = strtotime("$date $end");
        if ($startDT === false || $endDT === false) $errors[] = "Invalid date or time.";
        elseif ($startDT <= time()) $errors[] = "The event must be in the future.";
        elseif ($endDT <= $startDT) $errors[] = "End time must be after start time.";
    }

    if (!$errors) {
        $ins = $conn->prepare("INSERT INTO events (club_id, title, description, start_time, end_time, venue, status)
                               VALUES (:club_id, :title, :description, :start_time, :end_time, :venue, :status)");
        $ins->execute([
            'club_id'     => $club['club_id'],
            'title'       => $title,
            'description' => $description,
            'start_time'  => date('Y-m-d H:i:s', $startDT),
            'end_time'    => date('Y-m-d H:i:s', $endDT),
            'venue'       => $venue,
            'status'      => $status
        ]);
        $success = "Event saved as $status.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event | FST Club Hub</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main>
<div class="container">
    <h2>Create Event</h2>

    <?php foreach ($errors as $e): ?>
        <div class="message"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if ($success !== ''): ?>
        <div class="message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($club): ?>
    <form method="post">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="100"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="event_date">Date</label>
            <input type="date" id="event_date" name="event_date"
                   value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="start_time">Start time</label>
            <input type="time" id="start_time" name="start_time"
                   value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="end_time">End time</label>
            <input type="time" id="end_time" name="end_time"
                   value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="venue">Venue</label>
            <input type="text" id="venue" name="venue"
                   value="<?= htmlspecialchars($_POST['venue'] ?? '') ?>">
        </div>
        <button type="submit" class="btn" name="action" value="draft">Save as Draft</button>
        <button type="submit" class="btn" name="action" value="publish">Publish</button>
    </form>
    <?php endif; ?>
</div>
</main>
</body>
</html>