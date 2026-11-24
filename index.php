
<?php
// Connect to the database (see database.php)
require_once 'database.php';

/* ---------------------------------------------------------------
   PART A: Club search (called by scripts.js using fetch)
   --------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $keyword = "";
    if (isset($_GET['keyword'])) {
        $keyword = $_GET['keyword'];
    }

    $category = "";
    if (isset($_GET['category'])) {
        $category = $_GET['category'];
    }

    // TODO 1: Enter the name of the table that stores clubs.
    // TODO 2: Enter the status a club must have before students can see it.
    $sql = "SELECT club_id, name, category, description
            FROM __________
            WHERE status = '__________'
            AND (name LIKE :keyword OR description LIKE :keyword_desc)
            AND category LIKE :category";

    $stmt = $conn->prepare($sql);

    // TODO 3: Execute the query (the values below are already provided).
    $stmt->__________([
        'keyword' => '%' . $keyword . '%',
        'keyword_desc' => '%' . $keyword . '%',
        'category' => '%' . $category . '%'
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TODO 4: Complete the condition so the message shows when 0 clubs were found.
    if (count($results) === __) {
        echo "<p>No clubs found</p>";
    }
?>
<?php foreach ($results as $row): ?>
    <div class="card">
        <h3><?= htmlspecialchars($row['name']); ?></h3>
        <p class="category"><?= htmlspecialchars($row['category']); ?></p>
        <!-- TODO 5: Enter the column name that holds the club's description. -->
        <p><?= htmlspecialchars($row['__________']); ?></p>
        <button type="button" class="btn join-button"
                data-club-id="<?= $row['club_id']; ?>">
            Request to Join
        </button>
    </div>
<?php endforeach; ?>
<?php
    exit;
}

/* ---------------------------------------------------------------
   PART B: Data for the rest of the page
   Study how the JOIN works.
   --------------------------------------------------------------- */

// Upcoming published events from approved clubs, soonest first
$sql = "SELECT events.title, events.start_time, events.venue,
               clubs.name AS club_name
        FROM events
        JOIN clubs ON events.club_id = clubs.club_id
        WHERE events.status = 'Published'
        AND clubs.status = 'Approved'
        AND events.start_time > NOW()
        ORDER BY events.start_time ASC";

$stmt = $conn->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clubs waiting for administrator review
$sql = "SELECT club_id, name, category, registration_date
        FROM clubs
        WHERE status = 'Pending'
        ORDER BY registration_date ASC";

$stmt = $conn->query($sql);
$pending_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FST Club Hub</title>
    <link rel="stylesheet" href="styles.css">
    <script src="scripts.js" defer></script>
</head>

<body>
    <header>
        <div class="container">
            <h1>FST Club Hub</h1>

            <nav>
                <ul>
                    <li><a href="#" class="nav-link" data-section="student-section">Students</a></li>
                    <li><a href="#" class="nav-link" data-section="club-section">Club Representatives</a></li>
                    <li><a href="#" class="nav-link" data-section="admin-section">Administrators</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">

            <!-- ========== STUDENT INTERFACE (StudentUI) ========== -->
            <section id="student-section" class="page-section active">
                <h2>Discover Clubs</h2>

                <div class="search-bar">
                    <input type="text" id="keyword"
                           placeholder="Search by club name or description">

                    <select id="category">
                        <option value="">All Categories</option>
                        <option value="Academic">Academic</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Service">Service</option>
                        <option value="Sports">Sports</option>
                        <option value="Technology">Technology</option>
                    </select>

                    <button type="button" class="btn" id="search-button">Search</button>
                </div>

                <div id="club-results" class="cards"></div>
                <div id="join-message" class="message"></div>

                <h2>Upcoming Events</h2>

                <h2>Club Announcements</h2>
                <div class="cards">
                    <?php if (count($announcements) === 0): ?>
                        <p>No announcements</p>
                    <?php endif; ?>
                    <?php foreach ($announcements as $a): ?>
                        <div class="card">
                            <h3><?= htmlspecialchars($a['title']); ?></h3>
                            <p class="small"><?= htmlspecialchars($a['club_name']); ?> · <?= htmlspecialchars($a['posted_at']); ?></p>
                            <p><?= htmlspecialchars($a['message']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cards">
<?php if (count($events) === 0): ?>
                    <p>No upcoming events</p>
<?php endif; ?>

<?php foreach ($events as $event): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($event['title']); ?></h3>
                        <p>Club: <?= htmlspecialchars($event['club_name']); ?></p>
                        <!-- TODO 6: Enter the column name that holds the event's start date and time. -->
                        <p>When: <?= htmlspecialchars($event['__________']); ?></p>
                        <!-- TODO 7: Enter the column name that holds the event's venue. -->
                        <p>Venue: <?= htmlspecialchars($event['__________']); ?></p>
                    </div>
<?php endforeach; ?>
                </div>
            </section>

            <!-- ========== CLUB REPRESENTATIVE INTERFACE (ClubUI) ========== -->
            <section id="club-section" class="page-section">
                <h2>Register a Club</h2>

                <form id="register-form" action="#" method="post">
                    <div class="form-group">
                        <label for="club-name">Club Name</label>
                        <input type="text" id="club-name" name="name"
                               placeholder="e.g. Chess Club">
                    </div>

                    <div class="form-group">
                        <label for="club-category">Category</label>
                        <select id="club-category" name="category">
                            <option value="Academic">Academic</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Service">Service</option>
                            <option value="Sports">Sports</option>
                            <option value="Technology">Technology</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="club-description">Description</label>
                        <!-- TODO 10: Enter the maximum description length from the SRS. -->
                        <textarea id="club-description" name="description"
                                  rows="4" maxlength="__________"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="contact-email">Contact Email</label>
                        <!-- TODO 9: Enter the correct input type for an email address. -->
                        <input type="__________" id="contact-email"
                               name="contact_email" placeholder="club@example.com">
                    </div>

                    <button type="submit" class="btn">Submit Registration</button>
                    <div id="register-message" class="message"></div>
                </form>

                <h2>Manage Your Club</h2>

                <div class="cards">
                    <div class="card"><h3>Club Profile</h3><p>Coming soon</p></div>
                    <div class="card"><h3>Membership Requests</h3><p>Coming soon</p></div>
                    <div class="card"><h3>Events</h3><p>Coming soon</p></div>
                    <div class="card"><h3>Announcements</h3><p>Coming soon</p></div>
                </div>
                
                <h2>Post an Announcement</h2>
                
                <form id="announcement-form" method="post">
                    <div class="form-group">
                        <label for="announcement-club">Club</label>
                        <select id="announcement-club" name="club_id">
                <?php foreach ($rep_clubs as $c): ?>
                            <option value="<?= $c['club_id']; ?>"><?= htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="announcement-title">Title</label>
                        <input type="text" id="announcement-title" name="title" maxlength="80">
                    </div>
                    <div class="form-group">
                        <label for="announcement-text">Message</label>
                        <textarea id="announcement-text" name="message" rows="4" maxlength="1000"></textarea>
                    </div>
                    <button type="submit" class="btn">Post Announcement</button>
                    <div id="announcement-message" class="message"></div>
                </form>

            </section>

            <!-- ========== ADMINISTRATOR INTERFACE (AdminUI) ========== -->
            <section id="admin-section" class="page-section">
                <h2>Pending Club Registrations</h2>

<?php if (count($pending_clubs) === 0): ?>
                <p>No pending clubs</p>
<?php else: ?>
                <div class="results">
                    <table id="pending-clubs">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
<?php foreach ($pending_clubs as $club): ?>
                            <tr>
                                <td><?= htmlspecialchars($club['name']); ?></td>
                                <td><?= htmlspecialchars($club['category']); ?></td>
                                <!-- TODO 8: Enter the column name that holds the registration date. -->
                                <td><?= htmlspecialchars($club['__________']); ?></td>
                                <td>
                                    <button type="button" class="btn approve-button"
                                            data-club-id="<?= $club['club_id']; ?>">
                                        Approve
                                    </button>

                                    <button type="button" class="btn btn-reject reject-button"
                                            data-club-id="<?= $club['club_id']; ?>">
                                        Reject
                                    </button>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
<?php endif; ?>
            </section>

        </div>
    </main>

    <footer>
        <div class="container">
            <p>FST Club Hub | Error404 Collective | COMP2140</p>
        </div>
    </footer>
</body>
</html>
