<?php
include 'config/db.php';

echo "=== COMPLETE SYSTEM RESET ===\n\n";

// 1. Clear all sessions
echo "1. Clearing all sessions...\n";
@session_destroy();

// 2. Reset all students to not voted
echo "2. Resetting student votes...\n";
// Avoid referencing is_admin if the column doesn't exist
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'is_admin'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
	$result = mysqli_query($conn, "UPDATE students SET has_voted = 0 WHERE is_admin = 0");
} else {
	$hash_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
	if ($hash_check && mysqli_num_rows($hash_check) > 0) {
		$admin_hash = hash('sha256', strtolower('admin.ssg@bisu.edu.ph'));
		$admin_hash_esc = mysqli_real_escape_string($conn, $admin_hash);
		$result = mysqli_query($conn, "UPDATE students SET has_voted = 0 WHERE email_hash != '" . $admin_hash_esc . "'");
	} else {
		$admin_email_esc = mysqli_real_escape_string($conn, 'admin.ssg@bisu.edu.ph');
		$result = mysqli_query($conn, "UPDATE students SET has_voted = 0 WHERE email != '" . $admin_email_esc . "'");
	}
}
$affected = mysqli_affected_rows($conn);
echo "   ✓ $affected students reset\n";

// 3. Delete all votes
echo "3. Clearing vote records...\n";
$result = mysqli_query($conn, "DELETE FROM votes");
$deleted = mysqli_affected_rows($conn);
echo "   ✓ Deleted $deleted votes\n";

// 4. Reset candidate vote counts
echo "4. Resetting candidate counts...\n";
$result = mysqli_query($conn, "UPDATE candidates SET votes_count = 0");
echo "   ✓ Candidate counts reset\n";

// 5. Verify candidates exist
echo "5. Verifying candidates...\n";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM candidates");
$row = mysqli_fetch_assoc($result);
echo "   ✓ " . $row['cnt'] . " candidates in database\n";

// 6. Verify students exist
echo "6. Verifying students...\n";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM students WHERE is_admin = 0");
$row = mysqli_fetch_assoc($result);
echo "   ✓ " . $row['cnt'] . " student accounts ready\n";

echo "\n";
echo "=== RESET COMPLETE ===\n";
echo "✓ System is ready!\n";
echo "✓ Students must logout and log back in\n";
echo "✓ Redirect to: http://localhost/bisu_voting/login.php\n";

?>
