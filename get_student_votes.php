<?php include 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student's votes with candidate details
$candidate_type_check = mysqli_query($conn, "SHOW COLUMNS FROM candidates LIKE 'election_type'");
$candidate_has_election_type = $candidate_type_check && mysqli_num_rows($candidate_type_check) > 0;

$query = $candidate_has_election_type
    ? "SELECT c.id, c.name AS candidate_name, c.position, c.election_type 
        FROM votes v 
        JOIN candidates c ON v.candidate_id = c.id 
        WHERE v.student_id = ? 
        ORDER BY " . candidate_position_order_sql('c.election_type', 'c.position')
    : "SELECT c.id, c.name AS candidate_name, c.position, 'SSG' AS election_type 
        FROM votes v 
        JOIN candidates c ON v.candidate_id = c.id 
        WHERE v.student_id = ? 
        ORDER BY " . candidate_position_order_sql(null, 'c.position') . ";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$votes = [];

while ($row = mysqli_fetch_assoc($result)) {
    $votes[] = $row;
}

if (count($votes) > 0) {
    echo json_encode([
        'success' => true,
        'votes' => $votes,
        'count' => count($votes)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No votes found',
        'votes' => []
    ]);
}
mysqli_stmt_close($stmt);
?>
