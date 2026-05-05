<?php
include 'config/db.php';

echo "=== RESET STUDENT VOTES ===\n\n";

// Reset all students' has_voted status so they can vote again
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'is_admin'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $result = mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE is_admin = 0");
} else {
    $hash_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
    if ($hash_check && mysqli_num_rows($hash_check) > 0) {
        $admin_hash = hash('sha256', strtolower('admin.ssg@bisu.edu.ph'));
        $admin_hash_esc = mysqli_real_escape_string($conn, $admin_hash);
        $result = mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE email_hash != '" . $admin_hash_esc . "'");
    } else {
        $admin_email_esc = mysqli_real_escape_string($conn, 'admin.ssg@bisu.edu.ph');
        $result = mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE email != '" . $admin_email_esc . "'");
    }
}

if ($result) {
    $affected = mysqli_affected_rows($conn);
    echo "✓ Reset complete!\n";
    echo "✓ $affected student(s) reset to be able to vote\n\n";
} else {
    echo "✗ Error: " . mysqli_error($conn) . "\n";
}

// Also clear all votes
$result = mysqli_query($conn, "DELETE FROM votes");
if ($result) {
    $deleted = mysqli_affected_rows($conn);
    echo "✓ Deleted $deleted vote records\n";
} else {
    echo "✗ Error deleting votes: " . mysqli_error($conn) . "\n";
}

// Reset candidate vote counts
$result = mysqli_query($conn, "UPDATE candidates SET votes_count = 0");
if ($result) {
    echo "✓ Reset candidate vote counts\n\n";
} else {
    echo "✗ Error: " . mysqli_error($conn) . "\n";
}

echo "=== ALL SET ===\n";
echo "Students can now vote again!\n";
echo "Refresh the student dashboard to see the ballot.\n";

?>
