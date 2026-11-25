
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

    $sql = "SELECT club_id, name, category, description
            FROM clubs
            WHERE status = 'Approved'
            AND (name LIKE :keyword OR description LIKE :keyword_desc)
            AND category LIKE :category";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        'keyword' => '%' . $keyword . '%',
        'keyword_desc' => '%' . $keyword . '%',
        'category' => '%' . $category . '%'
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Shows a message if no clubs are found and exits the function without crashing
    if (count($results) === 0) {
        echo "<p>No clubs found</p>";
    }
    else {
        foreach ($results as $club) {
            echo "<div class='club-card'>";
            echo "<h3>" . htmlspecialchars($club['name']) . "</h3>";
            echo "<p>" . htmlspecialchars($club['category']) . "</p>";
            echo "<p>" . htmlspecialchars($club['description']) . "</p>";
            echo "<button type='button' class='btn join-button' data-club-id='" . (int)$club['club_id'] . "'>Request to Join</button>";
            echo "</div>";
        }
    }
    exit;
    
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
   MEMBERSHIP: Submit a membership request (Student)
   Called by scripts.js: index.php?action=request_join (POST)
   --------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'request_join') {
    // TEMP: fixed ID of the sample student (matches the sample data).
    // Replace with the logged-in user's ID from $_SESSION once login exists.
    $current_user_id = 3;

    // Validation: a club must be selected and the ID must be a whole number.
    $club_id = filter_var($_POST['club_id'] ?? '', FILTER_VALIDATE_INT);
    if ($club_id === false) {
        echo "Please select a valid club.";
        exit;
    }

    // The club must exist, be Approved, and be accepting members.
    $stmt = $conn->prepare("SELECT accepting_members FROM clubs
                            WHERE club_id = :club_id AND status = 'Approved'");
    $stmt->execute(['club_id' => $club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club) {
        echo "That club is not available.";
        exit;
    }
    if (!$club['accepting_members']) {
        echo "This club is not accepting new members.";
        exit;
    }

    // Prevent duplicate requests: check for an existing request by this student.
    $stmt = $conn->prepare("SELECT status FROM memberships
                            WHERE student_id = :student_id AND club_id = :club_id");
    $stmt->execute(['student_id' => $current_user_id, 'club_id' => $club_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['status'] === 'Pending') {
            echo "You already have a pending request for this club.";
        } elseif ($existing['status'] === 'Accepted') {
            echo "You are already a member of this club.";
        } else {
            echo "Your previous request to this club was declined.";
        }
        exit;
    }

    // All checks passed: save the request with status Pending.
    // request_id, status and requested_at are filled in by the database defaults.
    try {
        $stmt = $conn->prepare("INSERT INTO memberships (student_id, club_id)
                                VALUES (:student_id, :club_id)");
        $stmt->execute(['student_id' => $current_user_id, 'club_id' => $club_id]);
        echo "Membership request submitted. The club will review it.";
    } catch (PDOException $e) {
        // Safety net: the UNIQUE (student_id, club_id) key rejected a duplicate.
        echo "Your request could not be submitted. Please try again.";
    }
    exit;
}

/* ---------------------------------------------------------------
   ANNOUNCEMENTS: Post an announcement (Club Representative)
   Called by scripts.js: index.php?action=post_announcement (POST)
   Supports US-05. Corresponds to ClubFunc.postAnnouncement() in the SDS.
   --------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'post_announcement') {
    // TEMP: fixed ID of the sample representative (matches the sample data).
    // Replace with the logged-in user's ID from $_SESSION once login exists.
    $current_user_id = 2; 

    // Read the form values. "?? ''" gives an empty string if a field is missing.
    // trim() removes spaces at the start and end so "   " counts as blank.
    $club_id = $_POST['club_id'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation: all fields are mandatory.
    if ($club_id === '' || $title === '' || $message === '') {
        echo "Please fill in all fields."; 
        exit;
    }

    // Validation: enforce the SRS limits (title 80, message 1000) on the server,
    // because the form's maxlength can be bypassed. mb_strlen counts characters correctly.
    if (mb_strlen($title) > 80 || mb_strlen($message) > 1000) {
        echo "Title must be 80 characters or fewer and message 1000 or fewer."; 
        exit;
    }

    
    // Authorization: the user must be a leader of this club AND the club
    // must be Approved (SDS assumption: only approved clubs can post).
    // Prepared statement (:placeholders) protects against SQL injection.
    $stmt = $conn->prepare("SELECT club_id FROM clubs
                            WHERE club_id = :club_id AND leader_id = :user_id
                            AND status = 'Approved'");
    $stmt->execute(['club_id' => $club_id, 'user_id' => $current_user_id]);

    // fetch() returns false when no row matched, so the user is not allowed to post.
    if (!$stmt->fetch()) {
        echo "You can only post announcements for your own approved clubs.";
        exit;
    }

    // All checks passed: save the announcement.
    // announcement_id and posted_at are filled in automatically by the database.
    $stmt = $conn->prepare("INSERT INTO announcements (club_id, title, message)
                            VALUES (:club_id, :title, :message)");
    $stmt->execute(['club_id' => $club_id, 'title' => $title, 'message' => $message]);

    // Plain-text reply: scripts.js shows it and checks for this exact text.
    echo "Announcement posted.";
    exit; // Stop here so the page HTML is not sent back with the message.
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

/* ---------------------------------------------------------------
   ANNOUNCEMENTS: data for the page
   --------------------------------------------------------------- */

// TEMP: fixed IDs of the sample users. Replace with $_SESSION values after login is built.
$current_rep_id = 2;
$current_student_id = 3;

// Clubs this representative leads and that are Approved.
// Used to fill the club dropdown in the post form.
$stmt = $conn->prepare("SELECT club_id, name FROM clubs
                        WHERE leader_id = :uid AND status = 'Approved'");
$stmt->execute(['uid' => $current_rep_id]);
$rep_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Announcements a student may see: only from Approved clubs where the
// student's membership has been Accepted (SRS: visible to active members only).
// JOINs link announcements -> clubs -> memberships. Newest first.
$stmt = $conn->prepare("SELECT announcements.title, announcements.message,
                               announcements.posted_at, clubs.name AS club_name
                        FROM announcements
                        JOIN clubs ON announcements.club_id = clubs.club_id
                        WHERE clubs.leader_id = :uid
                        ORDER BY announcements.posted_at DESC");
$stmt->execute(['uid' => $current_rep_id]);
$rep_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Announcements posted by the clubs this representative leads.
// Lets the representative confirm what has been posted. Newest first.
$stmt = $conn->prepare("SELECT announcements.title, announcements.message,
                               announcements.posted_at, clubs.name AS club_name
                        FROM announcements
                        JOIN clubs ON announcements.club_id = clubs.club_id
                        JOIN memberships ON memberships.club_id = clubs.club_id
                        WHERE memberships.student_id = :uid
                        AND memberships.status = 'Accepted'
                        AND clubs.status = 'Approved'
                        ORDER BY announcements.posted_at DESC");
$stmt->execute(['uid' => $current_student_id]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

                <!-- ANNOUNCEMENTS (student view): only from clubs the student has joined -->
                <h2>Club Announcements</h2>
                <div class="cards">
                    <?php if (count($announcements) === 0): ?>
                        <p>No announcements</p>
                    <?php endif; ?>
                    <?php foreach ($announcements as $a): ?>
                        <div class="card">
                            <h3><?= htmlspecialchars($a['title']); ?></h3>
                            <p class="small"><?= htmlspecialchars($a['club_name']); ?> · <?= htmlspecialchars($a['posted_at']); ?></p>
                            <p><?= nl2br(htmlspecialchars($a['message'])); ?></p>
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
                </div>
                
                <!-- ANNOUNCEMENTS (representative view): list of posted announcements +  post form -->

                <!-- Announcements already posted by this representative's clubs -->
                <h2>Your Posted Announcements</h2>
                <div class="cards">
                <?php if (count($rep_announcements) === 0): ?>
                    <p>You haven't posted any announcements yet.</p>
                <?php endif; ?>
                <?php foreach ($rep_announcements as $a): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($a['title']); ?></h3>
                        <p class="small"><?= htmlspecialchars($a['club_name']); ?> · <?= htmlspecialchars($a['posted_at']); ?></p>
                        <p><?= nl2br(htmlspecialchars($a['message'])); ?></p>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Form to Post Announcements -->
                <h2>Post an Announcement</h2>
                
                <?php if (count($rep_clubs) === 0): ?>
                    <!-- No approved club, so there is nothing to post to (e.g. club still Pending) -->
                    <p>You need an approved club before you can post announcements.</p>
                <?php else: ?>

                <!-- Submitted by scripts.js with fetch (see "post an announcement" there) -->
                <form id="announcement-form" method="post">
                    <div class="form-group">
                        <label for="announcement-club">Club</label>
                        <!-- Only the clubs this representative leads and that are approved -->
                        <select id="announcement-club" name="club_id">
                <?php foreach ($rep_clubs as $c): ?>
                            <option value="<?= $c['club_id']; ?>"><?= htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="announcement-title">Title</label>
                        <!-- maxlength 80 matches the SRS and the database column -->
                        <input type="text" id="announcement-title" name="title" maxlength="80">
                    </div>
                    <div class="form-group">
                        <label for="announcement-text">Message</label>
                        <!-- maxlength 1000 matches the SRS and the database column -->
                        <textarea id="announcement-text" name="message" rows="4" maxlength="1000"></textarea>
                    </div>
                    <button type="submit" class="btn">Post Announcement</button>
                    <!-- scripts.js writes the server's reply here -->
                    <div id="announcement-message" class="message"></div>
                </form>

                <?php endif; ?>



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
