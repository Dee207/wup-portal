<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — DATABASE INSTALLER
//  Visit this file ONCE in your browser to create
//  all tables and seed default data.
//  DELETE or RENAME this file after running it!
// ══════════════════════════════════════════════════
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

$steps = [];

try {
    $db = getDB();

    // ── 1. USERS TABLE ──
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(120)  NOT NULL,
            email         VARCHAR(180)  NOT NULL UNIQUE,
            password_hash VARCHAR(255)  NOT NULL,
            role          ENUM('admin','teacher','student','parent') NOT NULL DEFAULT 'student',
            department    VARCHAR(120)  DEFAULT NULL,
            status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
            session_token VARCHAR(64)   DEFAULT NULL,
            token_expires DATETIME      DEFAULT NULL,
            created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✅ Table <strong>users</strong> created.';

    // ── 2. ANNOUNCEMENTS TABLE ──
    $db->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            title      VARCHAR(255) NOT NULL,
            content    TEXT         NOT NULL,
            category   ENUM('event','exam','notice','activity','holiday') NOT NULL DEFAULT 'notice',
            audience   ENUM('all','student','teacher','parent','staff')   NOT NULL DEFAULT 'all',
            author_id  INT          NOT NULL,
            pinned     TINYINT(1)   NOT NULL DEFAULT 0,
            archived   TINYINT(1)   NOT NULL DEFAULT 0,
            scheduled_at DATETIME   DEFAULT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✅ Table <strong>announcements</strong> created.';

    // ── 3. READ RECEIPTS TABLE ──
    $db->exec("
        CREATE TABLE IF NOT EXISTS read_receipts (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT      NOT NULL,
            announcement_id INT      NOT NULL,
            read_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_read (user_id, announcement_id),
            FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE,
            FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✅ Table <strong>read_receipts</strong> created.';

    // ── 4. EVENTS TABLE ──
    $db->exec("
        CREATE TABLE IF NOT EXISTS events (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            event_date  DATE         NOT NULL,
            event_time  VARCHAR(30)  DEFAULT 'All Day',
            location    VARCHAR(120) DEFAULT NULL,
            description TEXT         DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✅ Table <strong>events</strong> created.';

    // ── SEED: Users (use prepared statements so bcrypt $ signs are never interpolated) ──
    $seedUsers = [
        ['Administrator', 'admin@wup.edu.ph',   password_hash('admin123', PASSWORD_BCRYPT), 'admin',   'University Administration'],
        ['Prof. Santos',  'teacher@wup.edu.ph', password_hash('teach123', PASSWORD_BCRYPT), 'teacher', 'College of Education'],
        ['Juan dela Cruz','student@wup.edu.ph', password_hash('stud123',  PASSWORD_BCRYPT), 'student', 'BS Information Technology'],
        ['Mrs. Reyes',    'parent@wup.edu.ph',  password_hash('par123',   PASSWORD_BCRYPT), 'parent',  'Parent / Guardian'],
    ];
    $userStmt = $db->prepare('INSERT IGNORE INTO users (name, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?)');
    foreach ($seedUsers as $u) {
        $userStmt->execute($u);
    }
    $steps[] = '✅ Default <strong>users</strong> seeded (admin / teacher / student / parent).';

    // ── SEED: Get admin ID ──
    $adminId = $db->query("SELECT id FROM users WHERE email = 'admin@wup.edu.ph' LIMIT 1")->fetchColumn();

    // ── SEED: Announcements ──
    $anns = [
        ['National Science Fair Participation', 'event', 'All Grade 10 and 11 students are invited to participate in this year\'s National Science Fair. Project proposal submissions are due on March 28. Students who wish to join must coordinate with their Science teachers for guidance and topic approval.', 'student', 1, 0, '2025-03-20'],
        ['2nd Quarter Examination Schedule Released', 'exam', 'The schedule for 2nd Quarter Examinations has been officially released. Exams will be conducted from April 1–5, 2025. All students are advised to review their subjects and be present during their scheduled days.', 'all', 1, 0, '2025-03-19'],
        ['WUP Sportsfest 2025 — Registration Open', 'activity', 'The annual Sportsfest will be held on April 10–12, 2025 at the university gymnasium. All students are encouraged to represent their respective departments. Registration is open until March 30 at the Office of Student Affairs.', 'all', 0, 0, '2025-03-18'],
        ['Holy Week — No Classes April 17–20', 'holiday', 'Classes will be suspended from April 17 to 20, 2025 in observance of Holy Week. Regular classes resume on Monday, April 21. The university wishes everyone a reflective Holy Week.', 'all', 0, 0, '2025-03-17'],
        ['Faculty Professional Development Seminar', 'notice', 'All teaching personnel are required to attend the Professional Development Seminar on March 25 at the AVR, 8:00 AM. Topics include updated CHED curriculum guidelines and student mental health awareness.', 'teacher', 0, 0, '2025-03-15'],
        ['PTA General Assembly — March 29', 'event', 'All parents and guardians are invited to the PTA General Assembly on March 29 at 9:00 AM at the Gymnasium. Agenda includes school updates, financial reports, and election of new PTA officers.', 'parent', 0, 0, '2025-03-14'],
        ['Library — New Book Arrivals (March 2025)', 'notice', 'The University Library has received new collections including updated Science references, Filipino Literature anthologies, and research methodology textbooks. All titles are available for borrowing.', 'all', 0, 0, '2025-03-10'],
        ['Regional Math Olympiad — WUP Wins 3rd Place', 'event', 'Congratulations to our College of Education students who competed in the Regional Mathematics Olympiad! WUP placed 3rd overall. A recognition ceremony will be held at the covered court on March 22.', 'all', 0, 0, '2025-03-05'],
        ['Old Uniform Donation Drive', 'activity', 'The WUP Student Council is collecting gently used school uniforms to be donated to incoming freshmen. Donation boxes are placed at the main entrance and college lobbies. Drive runs until April 1.', 'all', 0, 1, '2025-02-28'],
        ['1st Quarter Report Cards — Now Available', 'exam', 'Report cards for the 1st Quarter are ready for claiming at the Registrar\'s Office. Parents/guardians must personally claim with a valid ID. Cards unclaimed after April 15 go to class advisers.', 'all', 0, 1, '2025-02-15'],
    ];

    $stmt = $db->prepare("
        INSERT IGNORE INTO announcements (title, category, content, audience, pinned, archived, author_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($anns as $a) {
        $stmt->execute([$a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $adminId, $a[6] . ' 08:00:00']);
    }
    $steps[] = '✅ Sample <strong>announcements</strong> seeded (' . count($anns) . ' records).';

    // ── SEED: Events ──
    $db->exec("
        INSERT IGNORE INTO events (title, event_date, event_time, location) VALUES
        ('National Science Fair',        '2025-03-28', '8:00 AM',  'AVR'),
        ('PTA General Assembly',         '2025-03-29', '9:00 AM',  'Gymnasium'),
        ('2nd Quarter Examinations',     '2025-04-01', 'All Day',  'Classrooms'),
        ('WUP Sportsfest 2025',          '2025-04-10', '7:30 AM',  'Gym & Grounds'),
        ('Holy Week Break',              '2025-04-17', 'All Day',  'University Closed')
    ");
    $steps[] = '✅ Sample <strong>events</strong> seeded.';

    $steps[] = '<br><strong style="color:green;font-size:18px">🎉 Installation complete! Your database is ready.</strong>';
    $steps[] = '<strong style="color:red">⚠️ IMPORTANT: Delete or rename this file (install.php) now for security!</strong>';

} catch (PDOException $e) {
    $steps[] = '<strong style="color:red">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</strong>';
    $steps[] = '<br>Please check your database credentials in <code>api/config.php</code>.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WUP Portal — Database Installer</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 700px; margin: 60px auto; padding: 0 20px; background: #f0f4f0; }
    h1 { color: #155c2e; }
    .step { background: white; border-left: 4px solid #155c2e; margin: 10px 0; padding: 12px 16px; border-radius: 4px; }
    code { background: #eee; padding: 2px 6px; border-radius: 3px; }
  </style>
</head>
<body>
  <h1>🎓 WUP Portal — Database Installer</h1>
  <?php foreach ($steps as $s): ?>
    <div class="step"><?= $s ?></div>
  <?php endforeach; ?>
</body>
</html>
