<?php include 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!empty($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Student account required']);
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
        ORDER BY " . candidate_position_order_sql(null, 'c.position');

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Unable to load votes right now.', 'votes' => []]);
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $student_id);
$executed = mysqli_stmt_execute($stmt);

if (!$executed) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Unable to load votes right now.', 'votes' => []]);
    exit();
}

$result = mysqli_stmt_get_result($stmt);
$votes = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row['election_type'] = normalize_election_type($row['election_type'] ?? 'SSG');
    $row['position'] = normalize_position_label($row['position'] ?? '');
    $votes[] = $row;
}

echo json_encode([
    'success' => true,
    'votes' => $votes,
    'count' => count($votes),
]);

mysqli_stmt_close($stmt);
?>
