
<?php
// Initialize the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Connect to the database (see database.php)
require_once 'database.php';

/* ===== ACCOUNT REGISTRATION ===== */
if (($_GET['action'] ?? '') === 'register_account'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'Student';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 8) {
        echo "Enter valid details. Password must be at least 8 characters.";
        exit;
    }

    if (!in_array($role, ['Student', 'Representative'], true)) {
        echo "Invalid account role.";
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        echo "An account with this email already exists.";
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, password_hash, role)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([$name, $email, $hash, $role]);

    echo "Account created successfully. You can now log in.";
    exit;
}

/* ===== USER LOGIN ===== */
if (($_GET['action'] ?? '') === 'login'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT user_id, name, email, password_hash, role
         FROM users WHERE email = ?"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo "Incorrect email or password.";
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    echo "Login successful.";
    exit;
}

/* ===== USER LOGOUT ===== */
if (($_GET['action'] ?? '') === 'logout') {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header("Location: index.php");
    exit;
}


/* ===== REGISTER A CLUB ===== */
if (($_GET['action'] ?? '') === 'register_club'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Representative') {
        echo "Only club representatives can register clubs.";
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');

    $allowed_categories = [
        'Academic', 'Cultural', 'Service', 'Sports', 'Technology'
    ];

    if ($name === '' || $description === '' ||
        !filter_var($contact_email, FILTER_VALIDATE_EMAIL) ||
        !in_array($category, $allowed_categories, true)) {
        echo "Please enter valid club details.";
        exit;
    }

    if (strlen($description) > 500) {
        echo "Description must not exceed 500 characters.";
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO clubs
        (name, category, description, contact_email, status, leader_id)
        VALUES (?, ?, ?, ?, 'Pending', ?)"
    );

    $stmt->execute([
        $name,
        $category,
        $description,
        $contact_email,
        $_SESSION['user_id']
    ]);

    echo "Club registration submitted successfully. Awaiting administrator approval.";
    exit;
}


/* ===== ADMINISTRATOR: APPROVE OR REJECT CLUB ===== */
if (($_GET['action'] ?? '') === 'update_status'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
        echo "Only administrators can approve or reject clubs.";
        exit;
    }

    $club_id = (int) ($_POST['club_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($club_id <= 0 || !in_array($status, ['Approved', 'Rejected'], true)) {
        echo "Invalid club or status.";
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE clubs SET status = ? WHERE club_id = ? AND status = 'Pending'"
    );

    $stmt->execute([$status, $club_id]);

    if ($stmt->rowCount() > 0) {
        echo "Club " . strtolower($status) . " successfully.";
    } else {
        echo "Club not found or has already been processed.";
    }

    exit;
}


/* ===== STUDENT: REQUEST TO JOIN A CLUB ===== */
if (($_GET['action'] ?? '') === 'request_join'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
        echo "Only students can request to join clubs.";
        exit;
    }

    $club_id = (int) ($_POST['club_id'] ?? 0);

    if ($club_id <= 0) {
        echo "Invalid club.";
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT club_id FROM clubs
         WHERE club_id = ? AND status = 'Approved'
         AND accepting_members = 1"
    );
    $stmt->execute([$club_id]);

    if (!$stmt->fetch()) {
        echo "This club is unavailable or is not accepting members.";
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT request_id FROM memberships
         WHERE student_id = ? AND club_id = ?"
    );
    $stmt->execute([$_SESSION['user_id'], $club_id]);

    if ($stmt->fetch()) {
        echo "You have already requested to join this club.";
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO memberships (student_id, club_id, status)
         VALUES (?, ?, 'Pending')"
    );

    $stmt->execute([$_SESSION['user_id'], $club_id]);

    echo "Your request to join has been submitted successfully.";
    exit;
}


/* ===== REPRESENTATIVE: RESPOND TO MEMBERSHIP REQUEST ===== */
if (($_GET['action'] ?? '') === 'update_membership'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Representative') {
        echo "Only club representatives can manage membership requests.";
        exit;
    }

    $request_id = (int) ($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($request_id <= 0 || !in_array($status, ['Accepted', 'Declined'], true)) {
        echo "Invalid request.";
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE memberships
         JOIN clubs ON memberships.club_id = clubs.club_id
         SET memberships.status = ?
         WHERE memberships.request_id = ?
         AND memberships.status = 'Pending'
         AND clubs.leader_id = ?"
    );

    $stmt->execute([$status, $request_id, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo "Membership request " . strtolower($status) . " successfully.";
    } else {
        echo "Request not found or you do not manage this club.";
    }

    exit;
}


/* ---------------------------------------------------------------
   PART A: Club search (called by scripts.js using fetch)
   --------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'search') {

    $keyword = trim($_GET['keyword'] ?? '');
    $category = trim($_GET['category'] ?? '');

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

    if (count($results) === 0) {
        echo "<p>No clubs found.</p>";
    } else {
        foreach ($results as $club) {
            echo "<div class='club-card'>";
            echo "<h3>" . htmlspecialchars($club['name']) . "</h3>";
            echo "<p>" . htmlspecialchars($club['category']) . "</p>";
            echo "<p>" . htmlspecialchars($club['description']) . "</p>";

            if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Student') {
                echo "<button type='button' class='join-button' data-club-id='" .
                     (int)$club['club_id'] . "'>Request to Join</button>";
            }

            echo "</div>";
        }
    }

    exit;
}


/* ---------------------------------------------------------------
   ANNOUNCEMENTS: Post an announcement (Club Representative)
   Called by scripts.js: index.php?action=post_announcement (POST)
   Supports US-05. Corresponds to ClubFunc.postAnnouncement() in the SDS.
   --------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'post_announcement') {
    
    $current_user_id = $_SESSION['user_id'] ?? null;
    $current_role    = $_SESSION['role'] ?? null;

    if ($current_user_id === null || $current_role !== 'Representative') {
        echo "You must be logged in as a club representative to post announcements.";
        exit;
    } 

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
// ===== US-07: Update club profile =====
if (($_GET['action'] ?? '') === 'update_profile'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // US-07: Verify logged-in representative
       $current_user_id = $_SESSION['user_id'] ?? null;
       $current_role = $_SESSION['role'] ?? null;

       if (!$current_user_id || $current_role !== 'Representative') {
       http_response_code(403);
       echo "Unauthorized access.";
       exit;
}

    // Retrieve the submitted information
    $club_id = $_POST['club_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $email = trim($_POST['contact_email'] ?? '');
    $phone = trim($_POST['contact_phone'] ?? '');
    $meeting = trim($_POST['meeting_info'] ?? '');

    // Validate description
    if ($description === '' || mb_strlen($description) > 500) {
        echo "Description must be between 1 and 500 characters.";
        exit;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || mb_strlen($email) > 100) {
        echo "Invalid email address.";
        exit;
    }

    // Validate phone number
    if ($phone !== '' && !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        echo "Invalid phone number.";
        exit;
    }

    // Validate meeting information
    if (mb_strlen($meeting) > 200) {
        echo "Meeting information is too long.";
        exit;
    }

    // Check club ownership and approval status
    $stmt = $conn->prepare("SELECT club_id FROM clubs
                            WHERE club_id = :club_id
                            AND leader_id = :uid
                            AND status = 'Approved'");

    $stmt->execute([
        'club_id' => $club_id,
        'uid' => $current_user_id
    ]);

    if (!$stmt->fetch()) {
        echo "You cannot edit this club.";
        exit;
    }

    // Update the club profile
    $stmt = $conn->prepare("UPDATE clubs
                            SET description = :description,
                                contact_email = :email,
                                contact_phone = :phone,
                                meeting_info = :meeting
                            WHERE club_id = :club_id
                            AND leader_id = :uid
                            AND status = 'Approved'");

    $stmt->execute([
        'description' => $description,
        'email' => $email,
        'phone' => $phone,
        'meeting' => $meeting,
        'club_id' => $club_id,
        'uid' => $current_user_id
    ]);

    echo "Club profile updated successfully.";
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

/* ---------------------------------------------------------------
   ANNOUNCEMENTS: data for the page
   --------------------------------------------------------------- */

$current_user_id = $_SESSION['user_id'] ?? null;
$current_role    = $_SESSION['role'] ?? null;

// Start empty so the page still works for other roles or logged-out users
$rep_clubs = [];
$rep_announcements = [];
$rep_membership_requests = [];
$announcements = [];
$club_profiles = [];
if ($current_role === 'Representative') {
       // US-07: Retrieve approved club profiles
    $stmt = $conn->prepare("SELECT club_id, name, description,
                           contact_email, contact_phone, meeting_info
                           FROM clubs
                           WHERE leader_id = :uid AND status = 'Approved'");

    $stmt->execute(['uid' => $current_user_id]);
    $club_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Clubs this representative leads and that are Approved (form dropdown)
    $stmt = $conn->prepare("SELECT club_id, name FROM clubs
                            WHERE leader_id = :uid AND status = 'Approved'");
    $stmt->execute(['uid' => $current_user_id]);
    $rep_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Announcements posted by this representative's clubs
    $stmt = $conn->prepare("SELECT announcements.title, announcements.message,
                                   announcements.posted_at, clubs.name AS club_name
                            FROM announcements
                            JOIN clubs ON announcements.club_id = clubs.club_id
                            WHERE clubs.leader_id = :uid
                            ORDER BY announcements.posted_at DESC");
    $stmt->execute(['uid' => $current_user_id]);
    $rep_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);


   
    // Membership requests for clubs managed by this representative
    $stmt = $conn->prepare(
        "SELECT memberships.request_id,
                memberships.status,
                memberships.requested_at,
                users.name AS student_name,
                users.email AS student_email,
                clubs.name AS club_name
         FROM memberships
         JOIN users ON memberships.student_id = users.user_id
         JOIN clubs ON memberships.club_id = clubs.club_id
         WHERE clubs.leader_id = :uid
         AND memberships.status = 'Pending'
         ORDER BY memberships.requested_at DESC"
    );

    $stmt->execute(['uid' => $current_user_id]);

    $rep_membership_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


if ($current_role === 'Student') {

    // Announcements from Approved clubs where this student is an Accepted member
    $stmt = $conn->prepare("SELECT announcements.title, announcements.message,
                                   announcements.posted_at, clubs.name AS club_name
                            FROM announcements
                            JOIN clubs ON announcements.club_id = clubs.club_id
                            JOIN memberships ON memberships.club_id = clubs.club_id
                            WHERE memberships.student_id = :uid
                            AND memberships.status = 'Accepted'
                            AND clubs.status = 'Approved'
                            ORDER BY announcements.posted_at DESC");
    $stmt->execute(['uid' => $current_user_id]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
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
        <?php if (!$current_user_id): ?>
            <li><a href="#" class="nav-link" data-section="auth-section">Login / Register</a></li>

        <?php elseif ($current_role === 'Student'): ?>
            <li><a href="#" class="nav-link" data-section="student-section">Students</a></li>

        <?php elseif ($current_role === 'Representative'): ?>
            <li><a href="#" class="nav-link" data-section="club-section">Club Representatives</a></li>

        <?php elseif ($current_role === 'Administrator'): ?>
            <li><a href="#" class="nav-link" data-section="admin-section">Administrators</a></li>
        <?php endif; ?>

        <?php if ($current_user_id): ?>
            <li><a href="index.php?action=logout">Log Out</a></li>
        <?php endif; ?>
    </ul>
</nav>
        </div>
    </header>

    <main>
        <div class="container">
	

	
<!-- ========== ACCOUNT ACCESS ========== -->
<section id="auth-section" class="page-section <?= !$current_user_id ? 'active' : ''; ?>">

    <h2>Welcome to FST Club Hub</h2>
    <p>Create an account or log in to access the platform.</p>

    <h3>Create an Account</h3>

    <form id="account-register-form">
        <label for="account-name">Full Name</label>
        <input type="text" id="account-name" name="name" required>

        <label for="account-email">Email Address</label>
        <input type="email" id="account-email" name="email" required>

        <label for="account-password">Password</label>
        <input type="password" id="account-password"
               name="password" minlength="8" required>

        <label for="account-role">Account Type</label>
        <select id="account-role" name="role" required>
            <option value="Student">Student</option>
            <option value="Representative">Club Representative</option>
        </select>

        <button type="submit" class="btn">Create Account</button>
        <div id="account-register-message" class="message"></div>
    </form>

    <h3>Already have an account? Log In</h3>

    <form id="login-form" method="POST" action="index.php?action=login">
        <label for="login-email">Email Address</label>
        <input type="email" id="login-email" name="email" required>

        <label for="login-password">Password</label>
        <input type="password" id="login-password"
               name="password" required>

        <button type="submit" class="btn">Log In</button>
        <div id="login-message" class="message"></div>
    </form>

</section>


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
                        <!-- Column name that holds the event's start date and time. -->
                        <p>When: <?= htmlspecialchars($event['start_time']); ?></p>
                        <!-- Column name that holds the event's venue. -->
                        <p>Venue: <?= htmlspecialchars($event['venue']); ?></p>
                    </div>
<?php endforeach; ?>
                </div>

                <!-- ANNOUNCEMENTS (student view): only from clubs the student has joined -->
                <?php if ($current_role === 'Student'): ?>
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
                <?php endif; ?>

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
                        <!-- Maximum description length from the SRS. -->
                        <textarea id="club-description" name="description"
                                  rows="4" maxlength="500"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="contact-email">Contact Email</label>
                        <!-- TODO 9: Enter the correct input type for an email address. -->
                        <input type="email" id="contact-email"
                               name="contact_email" placeholder="club@example.com">
                    </div>

                    <button type="submit" class="btn">Submit Registration</button>
                    <div id="register-message" class="message"></div>
                </form>

                
<h2>Manage Your Club</h2>

<div class="cards">
    <div class="card">
        <h3>Club Profile</h3>
        <p>Update your club description, contact details and meeting information.</p>
        <button type="button" class="btn" id="edit-profile-button">Edit Profile</button>
    </div>

    <div class="card">
        <h3>Membership Requests</h3>
        <p>Review and respond to students who want to join your clubs.</p>
    </div>

    <div class="card">
        <h3>Events</h3>
        <p>Manage your club events.</p>
    </div>
</div>

<h2>Pending Membership Requests</h2>

<div class="cards">
    <?php if (count($rep_membership_requests) === 0): ?>
        <p>No pending membership requests.</p>
    <?php else: ?>
        <?php foreach ($rep_membership_requests as $request): ?>
            <div class="card">
                <h3><?= htmlspecialchars($request['student_name']); ?></h3>
                <p>Email: <?= htmlspecialchars($request['student_email']); ?></p>
                <p>Club: <?= htmlspecialchars($request['club_name']); ?></p>
                <p>Requested: <?= htmlspecialchars($request['requested_at']); ?></p>

                <button type="button"
                        class="btn membership-action-button"
                        data-request-id="<?= $request['request_id']; ?>"
                        data-status="Accepted">
                    Accept
                </button>

                <button type="button"
                        class="btn btn-reject membership-action-button"
                        data-request-id="<?= $request['request_id']; ?>"
                        data-status="Declined">
                    Decline
                </button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>



                
                <!-- ANNOUNCEMENTS (representative view): list of posted announcements +  post form -->

                <?php if ($current_role === 'Representative'): ?>
                <!-- Announcements already posted by this representative's clubs -->

                <!-- US-07: Edit Club Profile -->
                <div id="edit-profile-section" hidden>

                <h2>Edit Club Profile</h2>

                <?php foreach ($club_profiles as $club): ?>

                  <form class="edit-profile-form">

                    <input type="hidden" name="club_id"
                   value="<?= $club['club_id']; ?>">

            <label>Club Name</label>
            <input type="text"
                   value="<?= htmlspecialchars($club['name']); ?>"
                   readonly>

            <label>Description</label>
            <textarea name="description" rows="4"
                      maxlength="500" required><?= htmlspecialchars($club['description']); ?></textarea>

            <label>Contact Email</label>
            <input type="email" name="contact_email"
                   maxlength="100"
                   value="<?= htmlspecialchars($club['contact_email']); ?>"
                   required>

            <label>Contact Phone</label>
            <input type="tel" name="contact_phone"
                   maxlength="20"
                   value="<?= htmlspecialchars($club['contact_phone'] ?? ''); ?>">

            <label>Meeting Information</label>
            <textarea name="meeting_info" rows="3"
                      maxlength="200"><?= htmlspecialchars($club['meeting_info'] ?? ''); ?></textarea>

            <button type="submit" class="btn">
                Save Changes
            </button>

            <button type="button" class="btn cancel-profile-button">
                Cancel
            </button>

            <div class="message profile-message"></div>

        </form>

    <?php endforeach; ?>

</div>
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
                                <!-- Column name that holds the registration date. -->
                                <td><?= htmlspecialchars($club['registration_date']); ?></td>
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
