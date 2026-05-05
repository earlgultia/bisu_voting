<?php
include 'config/db.php';

echo "=== INITIALIZING DATABASE TABLES ===\n\n";

// Create students table if it doesn't exist
$create_students = "CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    complete_address VARCHAR(255),
    email_hash CHAR(64) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT 0,
    has_voted BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

if (mysqli_query($conn, $create_students)) {
    echo "✓ students table OK\n";
} else {
    echo "✗ Error with students table: " . mysqli_error($conn) . "\n";
}

// Migration: drop removed columns if they exist in older schemas
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'students'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $old_cols = ['age', 'contact_number', 'ismis_id'];
    foreach ($old_cols as $col) {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE '" . $col . "'");
        if ($res && mysqli_num_rows($res) > 0) {
            if (mysqli_query($conn, "ALTER TABLE students DROP COLUMN `" . $col . "`")) {
                echo "✓ Dropped column $col\n";
            } else {
                echo "✗ Failed to drop column $col: " . mysqli_error($conn) . "\n";
            }
        }
    }
}

// Migration: convert plaintext `email` to `email_hash` (SHA-256) if present
$res_old_email = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email'");
$res_new_email = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
if ($res_old_email && mysqli_num_rows($res_old_email) > 0 && (!($res_new_email && mysqli_num_rows($res_new_email) > 0))) {
    // Normalize and hash existing emails in-place, then rename column
    if (mysqli_query($conn, "UPDATE students SET email = LOWER(TRIM(email))")) {
        echo "✓ Normalized existing emails\n";
    }

    if (mysqli_query($conn, "UPDATE students SET email = SHA2(email, 256)")) {
        echo "✓ Converted existing emails to SHA-256 hash\n";
    }

    if (mysqli_query($conn, "ALTER TABLE students CHANGE email email_hash CHAR(64) NOT NULL")) {
        echo "✓ Renamed email to email_hash\n";
    } else {
        echo "✗ Failed to rename email to email_hash: " . mysqli_error($conn) . "\n";
    }
} elseif ($res_new_email && mysqli_num_rows($res_new_email) > 0) {
    echo "✓ email_hash column exists\n";
}

// Ensure `is_admin` and `has_voted` columns exist for older schemas
$ensure_cols = [
    'is_admin' => 'BOOLEAN DEFAULT 0',
    'has_voted' => 'BOOLEAN DEFAULT 0',
];
foreach ($ensure_cols as $col => $def) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE '" . $col . "'");
    if (!($res && mysqli_num_rows($res) > 0)) {
        if (mysqli_query($conn, "ALTER TABLE students ADD COLUMN `" . $col . "` " . $def)) {
            echo "✓ Added column $col\n";
        } else {
            echo "✗ Failed to add column $col: " . mysqli_error($conn) . "\n";
        }
    }
}

// Migration: drop `college` and `course` columns if present
$drop_cols = ['college', 'course'];
foreach ($drop_cols as $col) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE '" . $col . "'");
    if ($res && mysqli_num_rows($res) > 0) {
        if (mysqli_query($conn, "ALTER TABLE students DROP COLUMN `" . $col . "`")) {
            echo "✓ Dropped column $col\n";
        } else {
            echo "✗ Failed to drop column $col: " . mysqli_error($conn) . "\n";
        }
    }
}

// Create candidates table if it doesn't exist
$create_candidates = "CREATE TABLE IF NOT EXISTS candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_type VARCHAR(20) NOT NULL DEFAULT 'SSG',
    name VARCHAR(150) NOT NULL,
    position VARCHAR(100) NOT NULL,
    details TEXT,
    picture VARCHAR(255),
    votes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

if (mysqli_query($conn, $create_candidates)) {
    echo "✓ candidates table OK\n";
} else {
    echo "✗ Error with candidates table: " . mysqli_error($conn) . "\n";
}

// Ensure election_type exists for older schemas
$election_type_col = mysqli_query($conn, "SHOW COLUMNS FROM candidates LIKE 'election_type'");
if (!($election_type_col && mysqli_num_rows($election_type_col) > 0)) {
    if (mysqli_query($conn, "ALTER TABLE candidates ADD COLUMN election_type VARCHAR(20) NOT NULL DEFAULT 'SSG' AFTER id")) {
        echo "✓ Added election_type column\n";
    } else {
        echo "✗ Failed to add election_type column: " . mysqli_error($conn) . "\n";
    }
}

mysqli_query($conn, "UPDATE candidates SET election_type = 'SSG' WHERE election_type IS NULL OR election_type = ''");

// Create votes table if it doesn't exist
$create_votes = "CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    candidate_id INT NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (student_id, candidate_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

if (mysqli_query($conn, $create_votes)) {
    echo "✓ votes table OK\n";
} else {
    echo "✗ Error with votes table: " . mysqli_error($conn) . "\n";
}

// Create admin user if it doesn't exist

$admin_email = 'admin.ssg@bisu.edu.ph';
$admin_hash = hash('sha256', strtolower($admin_email));
$check_admin = mysqli_prepare($conn, 'SELECT 1 FROM students WHERE email_hash = ? LIMIT 1');
mysqli_stmt_bind_param($check_admin, 's', $admin_hash);
mysqli_stmt_execute($check_admin);
$admin_result = mysqli_stmt_get_result($check_admin);

if (mysqli_num_rows($admin_result) === 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $create_admin = mysqli_prepare($conn, 
        'INSERT INTO students (first_name, last_name, email_hash, password, is_admin, has_voted) VALUES (?, ?, ?, ?, 1, 1)');
    mysqli_stmt_bind_param($create_admin, 'ssss', $fn, $ln, $admin_hash, $admin_password);
    $fn = 'Admin';
    $ln = 'SSG';
    if (mysqli_stmt_execute($create_admin)) {
        echo "✓ Admin account created (admin.ssg@bisu.edu.ph / admin123)\n";
    } else {
        echo "✗ Error creating admin: " . mysqli_error($conn) . "\n";
    }
    mysqli_stmt_close($create_admin);
} else {
    echo "✓ Admin account exists\n";
}

mysqli_stmt_close($check_admin);

echo "\n=== VERIFICATION ===\n";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM candidates");
$row = mysqli_fetch_assoc($result);
echo "Candidates in database: " . $row['cnt'] . "\n";

$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM students");
$row = mysqli_fetch_assoc($result);
echo "Students in database: " . $row['cnt'] . "\n";

$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM votes");
$row = mysqli_fetch_assoc($result);
echo "Votes in database: " . $row['cnt'] . "\n";

echo "\n=== DATABASE SETUP COMPLETE ===\n";
echo "✓ Next steps:\n";
echo "  1. Login as admin: admin.ssg@bisu.edu.ph / admin123\n";
echo "  2. Add candidates from the admin panel\n";
echo "  3. Students can then vote\n";
echo "\n✓ Check status anytime: http://localhost/bisu_voting/debug.php\n";
?>
