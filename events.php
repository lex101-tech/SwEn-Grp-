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
$eventToEdit = null;

// Find the approved club that this representative leads
$stmt = $conn->prepare("SELECT club_id, name FROM clubs WHERE leader_id = :uid AND status = 'Approved'");
$stmt->execute(['uid' => $userId]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    $errors[] = "You can only manage events for your own approved club.";
}

// Handle editing an existing event
if (isset($_GET['edit']) && $club) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND club_id = ?");
    $stmt->execute([(int)$_GET['edit'], $club['club_id']]);
    $eventToEdit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eventToEdit) {
        $errors[] = "Event not found.";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $club) {

    $action = $_POST['action'] ?? '';
    $eventId = (int)($_POST['event_id'] ?? 0);

    // Delete an event
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ? AND club_id = ?");
        $stmt->execute([$eventId, $club['club_id']]);

        $success = $stmt->rowCount() > 0
            ? "Event deleted successfully."
            : "Event could not be found.";
    }

    // Publish or unpublish an event
    elseif ($action === 'toggle_status') {
        $newStatus = ($_POST['status'] ?? '') === 'Published' ? 'Published' : 'Draft';

        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ? AND club_id = ?");
        $stmt->execute([$newStatus, $eventId, $club['club_id']]);

        $success = "Event status updated successfully.";
    }

    // Create or update an event
    elseif (in_array($action, ['draft', 'publish', 'update'], true)) {

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date        = $_POST['event_date'] ?? '';
        $start       = $_POST['start_time'] ?? '';
        $end         = $_POST['end_time'] ?? '';
        $venue       = trim($_POST['venue'] ?? '');

        $status = $action === 'publish'
            ? 'Published'
            : ($action === 'draft' ? 'Draft' : ($_POST['status'] ?? 'Draft'));

        if (!in_array($status, ['Draft', 'Published'], true)) {
            $status = 'Draft';
        }

        if (mb_strlen($title) < 3 || mb_strlen($title) > 100) {
            $errors[] = "Title must be 3 to 100 characters.";
        }

        if ($description === '') $errors[] = "Description is required.";
        if ($venue === '') $errors[] = "Venue is required.";

        if ($date === '' || $start === '' || $end === '') {
            $errors[] = "Date, start time and end time are required.";
        } else {
            $startDT = strtotime("$date $start");
            $endDT = strtotime("$date $end");

            if ($startDT === false || $endDT === false) {
                $errors[] = "Invalid date or time.";
            } elseif ($startDT <= time()) {
                $errors[] = "The event must be in the future.";
            } elseif ($endDT <= $startDT) {
                $errors[] = "End time must be after start time.";
            }
        }

        if (!$errors) {
            $startValue = date('Y-m-d H:i:s', $startDT);
            $endValue = date('Y-m-d H:i:s', $endDT);

            if ($action === 'update') {
                $stmt = $conn->prepare(
                    "UPDATE events
                     SET title = ?, description = ?, start_time = ?,
                         end_time = ?, venue = ?, status = ?
                     WHERE event_id = ? AND club_id = ?"
                );

                $stmt->execute([
                    $title, $description, $startValue,
                    $endValue, $venue, $status,
                    $eventId, $club['club_id']
                ]);

                $success = "Event updated successfully.";
                $eventToEdit = null;

            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO events
                     (club_id, title, description, start_time, end_time, venue, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $club['club_id'], $title, $description,
                    $startValue, $endValue, $venue, $status
                ]);

                $success = "Event saved as $status.";
            }
        }
    }
}

// Retrieve all events belonging to this representative's club
$clubEvents = [];

if ($club) {
    $stmt = $conn->prepare(
        "SELECT * FROM events WHERE club_id = ? ORDER BY start_time DESC"
    );
    $stmt->execute([$club['club_id']]);
    $clubEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="page-header">
    <h2>Create Event</h2>
    <a href="index.php" class="btn">Back to Dashboard</a>
</div>

    <?php foreach ($errors as $e): ?>
        <div class="message"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if ($success !== ''): ?>
        <div class="message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    
<?php if ($club): ?>

    <h2><?= $eventToEdit ? 'Edit Event' : 'Create Event' ?></h2>

    <form method="post" action="events.php">
        <?php if ($eventToEdit): ?>
            <input type="hidden" name="event_id" value="<?= $eventToEdit['event_id'] ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($eventToEdit['status']) ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="100"
                   value="<?= htmlspecialchars($eventToEdit['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($eventToEdit['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="event_date">Date</label>
            <input type="date" id="event_date" name="event_date"
                   value="<?= $eventToEdit ? date('Y-m-d', strtotime($eventToEdit['start_time'])) : '' ?>" required>
        </div>

        <div class="form-group">
            <label for="start_time">Start time</label>
            <input type="time" id="start_time" name="start_time"
                   value="<?= $eventToEdit ? date('H:i', strtotime($eventToEdit['start_time'])) : '' ?>" required>
        </div>

        <div class="form-group">
            <label for="end_time">End time</label>
            <input type="time" id="end_time" name="end_time"
                   value="<?= $eventToEdit ? date('H:i', strtotime($eventToEdit['end_time'])) : '' ?>" required>
        </div>

        <div class="form-group">
            <label for="venue">Venue</label>
            <input type="text" id="venue" name="venue"
                   value="<?= htmlspecialchars($eventToEdit['venue'] ?? '') ?>" required>
        </div>

        <?php if ($eventToEdit): ?>
            <button type="submit" class="btn" name="action" value="update">Save Changes</button>
            <a href="events.php" class="btn">Cancel Edit</a>
        <?php else: ?>
            <button type="submit" class="btn" name="action" value="draft">Save as Draft</button>
            <button type="submit" class="btn" name="action" value="publish">Publish</button>
        <?php endif; ?>
    </form>

    <hr>

    <h2>Manage Your Events</h2>

    <?php if (empty($clubEvents)): ?>
        <p>You haven't created any events yet.</p>
    <?php else: ?>

        <div class="cards">
            <?php foreach ($clubEvents as $event): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($event['title']) ?></h3>
                    <p>Date: <?= htmlspecialchars($event['start_time']) ?></p>
                    <p>Venue: <?= htmlspecialchars($event['venue']) ?></p>
                    <p>Status: <?= htmlspecialchars($event['status']) ?></p>

                    <a href="events.php?edit=<?= $event['event_id'] ?>" class="btn">Edit</a>

                    <form method="post" action="events.php" onsubmit="return confirm('Are you sure you want to delete this event?');">
                        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                        <button type="submit" name="action" value="delete" class="btn">Delete</button>
                    </form>

                    <form method="post" action="events.php">
                        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                        <input type="hidden" name="status" value="<?= $event['status'] === 'Published' ? 'Draft' : 'Published' ?>">
                        <button type="submit" name="action" value="toggle_status" class="btn">
                            <?= $event['status'] === 'Published' ? 'Unpublish' : 'Publish' ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

<?php endif; ?>

</div>
</main>
</body>
</html>