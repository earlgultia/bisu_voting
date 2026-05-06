<?php include 'config/db.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$candidate_type_check = mysqli_query($conn, "SHOW COLUMNS FROM candidates LIKE 'election_type'");
$candidate_has_election_type = $candidate_type_check && mysqli_num_rows($candidate_type_check) > 0;

if (!$candidate_has_election_type) {
    if (mysqli_query($conn, "ALTER TABLE candidates ADD COLUMN election_type VARCHAR(20) NOT NULL DEFAULT 'SSG' AFTER id")) {
        mysqli_query($conn, "UPDATE candidates SET election_type = 'SSG' WHERE election_type IS NULL OR election_type = ''");
        $candidate_has_election_type = true;
    }
}

$active_edit_candidate_id = null;

// Add candidate
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_candidate'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $election_type = strtoupper(trim($_POST['election_type'] ?? 'SSG'));
        if (!in_array($election_type, ['SSG', 'FTP'], true)) {
            $election_type = 'SSG';
        }
        $name = trim($_POST['name']);
        $position = trim($_POST['position']);
        $details = trim($_POST['details']);

        $picture_path = '';
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['picture']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed, true)) {
                $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }
                $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($filename));
                $picture_path = 'uploads/' . $safe_name;
                $target_path = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
                if (!move_uploaded_file($_FILES['picture']['tmp_name'], $target_path)) {
                    $picture_path = '';
                }
            }
        }

        if ($candidate_has_election_type) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO candidates (election_type, name, position, details, picture) VALUES (?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssss', $election_type, $name, $position, $details, $picture_path);
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO candidates (name, position, details, picture) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssss', $name, $position, $details, $picture_path);
        }
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Candidate added successfully!';
        } else {
            $error = 'Unable to add candidate.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Update candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_candidate'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $candidate_id = (int)($_POST['candidate_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $election_type = strtoupper(trim($_POST['election_type'] ?? 'SSG'));

        if (!in_array($election_type, ['SSG', 'FTP'], true)) {
            $election_type = 'SSG';
        }

        if ($candidate_id <= 0 || $name === '' || $position === '') {
            $error = 'Please complete the candidate details before saving.';
            $active_edit_candidate_id = $candidate_id > 0 ? $candidate_id : null;
        } else {
            $current_picture = '';
            $fetch_candidate = mysqli_prepare($conn, 'SELECT picture FROM candidates WHERE id = ?');
            mysqli_stmt_bind_param($fetch_candidate, 'i', $candidate_id);
            mysqli_stmt_execute($fetch_candidate);
            mysqli_stmt_bind_result($fetch_candidate, $current_picture);
            $candidate_exists = mysqli_stmt_fetch($fetch_candidate);
            mysqli_stmt_close($fetch_candidate);

            if (!$candidate_exists) {
                $error = 'Candidate not found.';
                $active_edit_candidate_id = $candidate_id;
            } else {
                $picture_path = $current_picture ?: '';
                $new_picture_uploaded = false;
                $new_picture_temp = '';

                if (isset($_FILES['picture']) && $_FILES['picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['picture']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                        $filename = $_FILES['picture']['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowed, true)) {
                            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0775, true);
                            }

                            $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($filename));
                            $target_path = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
                            if (move_uploaded_file($_FILES['picture']['tmp_name'], $target_path)) {
                                $picture_path = 'uploads/' . $safe_name;
                                $new_picture_uploaded = true;
                                $new_picture_temp = $target_path;
                            } else {
                                $error = 'Unable to upload the candidate photo.';
                            }
                        } else {
                            $error = 'Invalid picture type. Please upload a JPG, JPEG, PNG, or GIF file.';
                        }
                    } else {
                        $error = 'Unable to upload the candidate photo.';
                    }
                }

                if (!isset($error)) {
                    mysqli_begin_transaction($conn);

                    if ($candidate_has_election_type) {
                        $stmt = mysqli_prepare($conn, 'UPDATE candidates SET election_type = ?, name = ?, position = ?, details = ?, picture = ? WHERE id = ?');
                        mysqli_stmt_bind_param($stmt, 'sssssi', $election_type, $name, $position, $details, $picture_path, $candidate_id);
                    } else {
                        $stmt = mysqli_prepare($conn, 'UPDATE candidates SET name = ?, position = ?, details = ?, picture = ? WHERE id = ?');
                        mysqli_stmt_bind_param($stmt, 'ssssi', $name, $position, $details, $picture_path, $candidate_id);
                    }

                    $update_ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if ($update_ok) {
                        mysqli_commit($conn);

                        if ($new_picture_uploaded && $current_picture && $current_picture !== $picture_path) {
                            $old_picture_path = __DIR__ . DIRECTORY_SEPARATOR . ltrim($current_picture, '/\\');
                            if (is_file($old_picture_path)) {
                                @unlink($old_picture_path);
                            }
                        }

                        $success = 'Candidate updated successfully!';
                    } else {
                        mysqli_rollback($conn);
                        if ($new_picture_uploaded && is_file($new_picture_temp)) {
                            @unlink($new_picture_temp);
                        }
                        $error = 'Unable to update candidate. Please try again.';
                        $active_edit_candidate_id = $candidate_id;
                    }
                } elseif ($new_picture_uploaded && is_file($new_picture_temp)) {
                    @unlink($new_picture_temp);
                    $active_edit_candidate_id = $candidate_id;
                }
            }
        }
    }
}

// Remove candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_candidate'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $id = (int)$_POST['remove_candidate'];
    mysqli_begin_transaction($conn);
        $deleted_votes = mysqli_prepare($conn, 'DELETE FROM votes WHERE candidate_id = ?');
        mysqli_stmt_bind_param($deleted_votes, 'i', $id);
        $votes_ok = mysqli_stmt_execute($deleted_votes);
        mysqli_stmt_close($deleted_votes);

        $deleted_candidate = mysqli_prepare($conn, 'DELETE FROM candidates WHERE id = ?');
        mysqli_stmt_bind_param($deleted_candidate, 'i', $id);
        $candidate_ok = mysqli_stmt_execute($deleted_candidate);
        mysqli_stmt_close($deleted_candidate);

        if ($votes_ok && $candidate_ok) {
            mysqli_commit($conn);
            $success = 'Candidate removed successfully!';
        } else {
            mysqli_rollback($conn);
            $error = 'Unable to remove candidate. Please try again.';
        }
    }
}

// Reset election (clear votes and reset voting status)
if (isset($_POST['reset_election'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        mysqli_begin_transaction($conn);

        $votes_cleared = mysqli_query($conn, "DELETE FROM votes");
        $counts_reset = mysqli_query($conn, "UPDATE candidates SET votes_count = 0");

        // Reset students' voting status but try to avoid referencing missing columns
        $students_reset = true;
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'is_admin'");
        if ($col_check && mysqli_num_rows($col_check) > 0) {
            $students_reset = (bool) mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE is_admin = 0");
        } else {
            // fallback: prefer email_hash if present, otherwise plaintext email
            $hash_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
            if ($hash_check && mysqli_num_rows($hash_check) > 0) {
                $admin_hash = hash('sha256', strtolower('admin.ssg@bisu.edu.ph'));
                $admin_hash_esc = mysqli_real_escape_string($conn, $admin_hash);
                $students_reset = (bool) mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE email_hash != '" . $admin_hash_esc . "'");
            } else {
                $admin_email_esc = mysqli_real_escape_string($conn, 'admin.ssg@bisu.edu.ph');
                $students_reset = (bool) mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE email != '" . $admin_email_esc . "'");
            }
        }

        if ($votes_cleared && $counts_reset && $students_reset) {
            mysqli_commit($conn);
            $success = 'Election has been reset for the next election!';
        } else {
            mysqli_rollback($conn);
            $error = 'Unable to reset the election. Please try again.';
        }
    }
}

// Get all candidates
// Normalize legacy position labels such as "SSG President" and "FTP Vice - President".
$position_order_sql = candidate_position_order_sql($candidate_has_election_type ? 'election_type' : null, 'position');
$candidates = $candidate_has_election_type
    ? mysqli_query($conn, "SELECT * FROM candidates ORDER BY {$position_order_sql}, votes_count DESC, name ASC")
    : mysqli_query($conn, "SELECT *, 'SSG' AS election_type FROM candidates ORDER BY {$position_order_sql}, votes_count DESC, name ASC");
$total_votes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM votes"))['total'];
$candidate_count = mysqli_num_rows($candidates);
$position_counts = [];
$normalized_position_sql = candidate_position_sql_normalized('position');
$position_count_result = $candidate_has_election_type
    ? mysqli_query($conn, "SELECT UPPER(TRIM(election_type)) AS election_type, {$normalized_position_sql} AS position_label, COUNT(*) as total FROM candidates GROUP BY election_type, position_label")
    : mysqli_query($conn, "SELECT 'SSG' AS election_type, {$normalized_position_sql} AS position_label, COUNT(*) as total FROM candidates GROUP BY position_label");
while ($row = mysqli_fetch_assoc($position_count_result)) {
    $type_key = normalize_position_label($row['election_type'] ?? 'SSG');
    $position_key = normalize_position_label($row['position_label'] ?? '');
    $position_counts[$type_key][$position_key] = (int)$row['total'];
}

$analytics_ready = false;
$college_vote_stats = [];
$course_vote_stats = [];
$total_profile_students = 0;
$total_profile_voted = 0;
$college_col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'college'");
$course_col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'course'");
$admin_col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'is_admin'");
$analytics_ready = (
    $college_col_check && mysqli_num_rows($college_col_check) > 0
    && $course_col_check && mysqli_num_rows($course_col_check) > 0
);

if ($analytics_ready) {
    $student_scope = ($admin_col_check && mysqli_num_rows($admin_col_check) > 0)
        ? 'WHERE COALESCE(is_admin, 0) = 0'
        : '';

    $college_vote_query = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(college), ''), 'Unassigned') AS label, COUNT(*) AS total_students, SUM(CASE WHEN has_voted = 1 THEN 1 ELSE 0 END) AS voted_students FROM students {$student_scope} GROUP BY label ORDER BY label"
    );
    if ($college_vote_query) {
        while ($row = mysqli_fetch_assoc($college_vote_query)) {
            $college_vote_stats[] = [
                'label' => (string) $row['label'],
                'total' => (int) $row['total_students'],
                'voted' => (int) $row['voted_students'],
            ];
            $total_profile_students += (int) $row['total_students'];
            $total_profile_voted += (int) $row['voted_students'];
        }
        mysqli_free_result($college_vote_query);
    }

    $course_vote_query = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(college), ''), 'Unassigned') AS college_label, COALESCE(NULLIF(TRIM(course), ''), 'Unassigned') AS course_label, COUNT(*) AS total_students, SUM(CASE WHEN has_voted = 1 THEN 1 ELSE 0 END) AS voted_students FROM students {$student_scope} GROUP BY college_label, course_label ORDER BY college_label, course_label"
    );
    if ($course_vote_query) {
        while ($row = mysqli_fetch_assoc($course_vote_query)) {
            $course_vote_stats[] = [
                'college' => (string) $row['college_label'],
                'course' => (string) $row['course_label'],
                'total' => (int) $row['total_students'],
                'voted' => (int) $row['voted_students'],
            ];
        }
        mysqli_free_result($course_vote_query);
    }
}

if (isset($_GET['stats'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'total_votes' => (int)$total_votes,
        'candidate_count' => (int)$candidate_count
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - BISU Voting System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/theme.css">
    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .user-meta {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            flex-wrap: wrap;
        }
        .user-name {
            font-weight: 700;
        }
        .logout-btn {
            padding: 0.58rem 1.15rem;
        }
        .brand-logo {
            width: 2.35rem;
            height: 2.35rem;
            object-fit: contain;
            display: block;
            flex: 0 0 auto;
        }
        .stats-card {
            padding: 1.2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            animation: rise 0.6s ease both;
            background:
                linear-gradient(135deg, rgba(23, 59, 114, 0.05), rgba(17, 124, 107, 0.06)),
                #fff;
        }
        .stats-card > div {
            padding: 1.15rem;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
        }
        .analytics-section {
            margin-bottom: 2rem;
        }
        .analytics-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .analytics-summary-card,
        .analytics-panel {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff, #f9fbff);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
        }
        .analytics-summary-card {
            padding: 1rem 1.1rem;
        }
        .analytics-label {
            display: block;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            font-size: 0.68rem;
            margin-bottom: 0.35rem;
        }
        .analytics-value {
            display: block;
            font-size: clamp(1.65rem, 3vw, 2.05rem);
            font-weight: 700;
            color: var(--accent-strong);
        }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .analytics-panel {
            padding: 1rem;
        }
        .analytics-panel h4 {
            margin-bottom: 0.35rem;
        }
        .analytics-panel .note {
            margin-top: 0.35rem;
        }
        .progress-list {
            display: grid;
            gap: 0.9rem;
            margin-top: 1rem;
        }
        .progress-item {
            display: grid;
            gap: 0.45rem;
        }
        .progress-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
        }
        .progress-title {
            font-weight: 700;
            color: var(--accent-strong);
        }
        .progress-subtitle {
            display: block;
            color: var(--muted);
            font-size: 0.84rem;
            margin-top: 0.2rem;
        }
        .progress-meta {
            color: var(--muted);
            font-size: 0.84rem;
            white-space: nowrap;
        }
        .progress-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #e7edf4;
        }
        .progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
        }
        .analytics-empty {
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .stat-label {
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            font-size: 0.7rem;
            margin-bottom: 0.4rem;
        }
        .stat-number {
            font-size: clamp(2rem, 4vw, 2.4rem);
            font-weight: 700;
            color: var(--accent-strong);
        }
        .section {
            padding: 1.6rem;
            margin-bottom: 2rem;
            animation: rise 0.6s ease both;
        }
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.4rem;
            font-size: 1.1rem;
        }
        .section-title span {
            color: var(--muted);
            font-size: 0.85rem;
            background: rgba(23, 59, 114, 0.06);
            padding: 0.34rem 0.7rem;
            border-radius: 999px;
        }
        .form-grid {
            display: grid;
            gap: 1rem;
        }
        .table-wrap {
            overflow-x: auto;
            border-radius: var(--radius-sm);
            border: 1px solid var(--line);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.04);
        }
        .results-accordion {
            display: grid;
            gap: 1rem;
        }
        .results-panel {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .results-panel[open] {
            border-color: rgba(23, 59, 114, 0.16);
        }
        .results-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            cursor: pointer;
            list-style: none;
            font-weight: 800;
            color: var(--accent-strong);
            background: linear-gradient(135deg, rgba(23, 59, 114, 0.05), rgba(17, 124, 107, 0.06));
        }
        .results-title {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }
        .results-toggle::-webkit-details-marker {
            display: none;
        }
        .results-toggle::after {
            content: '+';
            flex: 0 0 auto;
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(23, 59, 114, 0.08);
            color: var(--accent-strong);
            font-size: 1.1rem;
        }
        .results-panel[open] .results-toggle::after {
            content: '–';
            background: rgba(17, 124, 107, 0.12);
        }
        .results-logo {
            width: 1.6rem;
            height: 1.6rem;
            object-fit: contain;
            display: block;
            flex: 0 0 auto;
        }
        .results-body {
            padding: 0.85rem 0 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 780px;
        }
        th, td {
            padding: 0.9rem;
            text-align: left;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }
        th {
            background: #f5f7f6;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.1em;
            color: var(--muted);
        }
        .badge {
            background: rgba(17, 124, 107, 0.12);
            color: var(--accent-2-strong);
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }
        .candidate-img {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            object-fit: cover;
        }
        .position-row td {
            background: linear-gradient(135deg, rgba(23, 59, 114, 0.08), rgba(17, 124, 107, 0.06));
            color: var(--accent-strong);
            font-weight: 700;
            border-top: 1px solid var(--line);
        }
        .position-meta {
            font-weight: 500;
            color: var(--muted);
            margin-left: 0.6rem;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.7);
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
        }
        .btn-sm {
            padding: 0.5rem 0.9rem;
            font-size: 0.85rem;
        }
        .candidate-actions {
            display: flex;
            gap: 0.55rem;
            flex-wrap: wrap;
        }
        .candidate-actions form {
            margin: 0;
        }
        .candidate-edit-row td {
            padding: 0;
            background: linear-gradient(180deg, #fbfdff, #f4f8fc);
        }
        .candidate-edit-shell {
            padding: 1rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
        }
        .candidate-edit-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }
        .candidate-edit-head strong {
            font-size: 1rem;
        }
        .candidate-edit-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .candidate-edit-grid .form-field:last-child {
            grid-column: 1 / -1;
        }
        .candidate-edit-preview {
            width: 88px;
            height: 88px;
            border-radius: 18px;
            object-fit: cover;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
        }
        .candidate-edit-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.65rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 2rem;
        }
        .note {
            margin-top: 1rem;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .winners {
            margin-top: 2rem;
            padding: 1.4rem;
            background: linear-gradient(135deg, #eef8f3, #f6fbff);
            border: 1px solid rgba(17, 124, 107, 0.18);
            border-radius: var(--radius);
        }
        .winners-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            cursor: pointer;
            list-style: none;
        }
        .winners-summary::-webkit-details-marker {
            display: none;
        }
        .winners-summary::after {
            content: '+';
            flex: 0 0 auto;
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(23, 59, 114, 0.08);
            color: var(--accent-strong);
            font-size: 1.1rem;
        }
        .winners[open] .winners-summary::after {
            content: '–';
            background: rgba(17, 124, 107, 0.12);
        }
        .winners-body {
            margin-top: 1rem;
        }
        @media (max-width: 900px) {
            .dashboard-header,
            .user-meta,
            .grid-2,
            .section-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-header,
            .user-meta {
                width: 100%;
            }

            .logout-btn,
            .btn {
                width: 100%;
            }

            .stats-card {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .analytics-summary,
            .analytics-grid {
                grid-template-columns: 1fr;
            }

            .grid-2 {
                gap: 1.25rem;
            }

            .section,
            .stats-card {
                padding: 1.2rem;
            }

            .section-title {
                gap: 0.45rem;
            }

            .section-title span {
                font-size: 0.8rem;
            }
        }
        @media (max-width: 1000px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            table {
                min-width: 100%;
            }

            .table-wrap {
                border-radius: 16px;
            }
        }
        @media (max-width: 640px) {
            .stats-card {
                margin-bottom: 1.5rem;
            }

            th, td {
                padding: 0.75rem 0.7rem;
            }

            .candidate-img {
                width: 42px;
                height: 42px;
                border-radius: 10px;
            }

            .position-row td {
                font-size: 0.92rem;
            }

            .btn-sm {
                width: 100%;
            }

            .winners {
                padding: 1rem;
            }
        }
        @media (max-width: 420px) {
            .container {
                width: min(1120px, 96vw);
                padding-top: 1.25rem;
            }

            .card,
            .section,
            .stats-card,
            .winners {
                border-radius: 16px;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .position-meta {
                display: block;
                margin-left: 0;
                margin-top: 0.25rem;
            }
        }
    </style>
</head>
<body data-total-votes="<?php echo (int)$total_votes; ?>">
    <header class="topbar dashboard-header">
        <div class="brand">
            <img src="uploads/admin-logo.png" class="brand-logo" alt="Commission on Student Elections logo">
            <div>
                <div class="brand-title">BISU Voting System</div>
                <div class="brand-sub">Admin Panel</div>
            </div>
        </div>
        <div class="user-meta">
            <span class="user-name">Welcome, <?php echo h($_SESSION['is_admin'] ? 'Comselec' : ($_SESSION['user_name'] ?? 'Admin')); ?></span>
            <a href="logout.php" class="btn btn-ghost logout-btn">Logout</a>
        </div>
    </header>
    
    <main class="container">
        <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        
        <div class="stats-card card">
            <div>
                <div class="stat-label">Total Votes Cast</div>
                        <div class="stat-number"><?php echo (int)$total_votes; ?></div>
            </div>
            <div>
                <div class="stat-label">Active Candidates</div>
                        <div class="stat-number"><?php echo (int)$candidate_count; ?></div>
            </div>
        </div>

        <div class="section card analytics-section">
            <h3 class="section-title">
                Voting Analytics
                <span>College and course completion</span>
            </h3>
            <?php if ($analytics_ready && (!empty($college_vote_stats) || !empty($course_vote_stats))): ?>
                <div class="analytics-summary">
                    <div class="analytics-summary-card">
                        <span class="analytics-label">Registered students</span>
                        <span class="analytics-value"><?php echo (int)$total_profile_students; ?></span>
                    </div>
                    <div class="analytics-summary-card">
                        <span class="analytics-label">Students who voted</span>
                        <span class="analytics-value"><?php echo (int)$total_profile_voted; ?></span>
                    </div>
                    <div class="analytics-summary-card">
                        <span class="analytics-label">Completion rate</span>
                        <span class="analytics-value"><?php echo $total_profile_students > 0 ? round(($total_profile_voted / $total_profile_students) * 100) : 0; ?>%</span>
                    </div>
                </div>

                <div class="analytics-grid">
                    <div class="analytics-panel">
                        <h4>By College</h4>
                        <p class="note">See which colleges have finished voting and which still have pending ballots.</p>
                        <div class="progress-list">
                            <?php foreach ($college_vote_stats as $college_stat):
                                $college_percent = $college_stat['total'] > 0 ? round(($college_stat['voted'] / $college_stat['total']) * 100) : 0;
                            ?>
                                <div class="progress-item">
                                    <div class="progress-head">
                                        <div>
                                            <div class="progress-title"><?php echo h($college_stat['label']); ?></div>
                                            <span class="progress-subtitle"><?php echo (int)$college_stat['voted']; ?> of <?php echo (int)$college_stat['total']; ?> voted</span>
                                        </div>
                                        <div class="progress-meta"><?php echo (int)$college_percent; ?>%</div>
                                    </div>
                                    <div class="progress-track" aria-hidden="true">
                                        <div class="progress-fill" style="width: <?php echo (int)$college_percent; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="analytics-panel">
                        <h4>By Course</h4>
                        <p class="note">Track vote completion for each course under its college.</p>
                        <div class="progress-list">
                            <?php foreach ($course_vote_stats as $course_stat):
                                $course_percent = $course_stat['total'] > 0 ? round(($course_stat['voted'] / $course_stat['total']) * 100) : 0;
                            ?>
                                <div class="progress-item">
                                    <div class="progress-head">
                                        <div>
                                            <div class="progress-title"><?php echo h($course_stat['course']); ?></div>
                                            <span class="progress-subtitle"><?php echo h($course_stat['college']); ?></span>
                                        </div>
                                        <div class="progress-meta"><?php echo (int)$course_percent; ?>%</div>
                                    </div>
                                    <div class="progress-track" aria-hidden="true">
                                        <div class="progress-fill" style="width: <?php echo (int)$course_percent; ?>%;"></div>
                                    </div>
                                    <span class="progress-subtitle"><?php echo (int)$course_stat['voted']; ?> of <?php echo (int)$course_stat['total']; ?> voted</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice card analytics-empty">
                    <h3>Analytics not available yet.</h3>
                    <p>Add or update student profiles with college and course information to see voting progress here.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="grid-2">
            <!-- Add Candidate Form -->
            <div class="section card">
                <h3 class="section-title">
                    Add New Candidate
                    <span>Candidate details</span>
                </h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Election Type</label>
                            <select name="election_type" required>
                                <option value="SSG">SSG Election</option>
                                <option value="FTP">FTP Election</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Full Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-field">
                            <label>Position</label>
                            <input type="text" name="position" required placeholder="e.g., President, Vice President, Secretary">
                        </div>
                        <div class="form-field">
                            <label>Details/Bio</label>
                            <textarea name="details" rows="3" placeholder="Platform, achievements, etc."></textarea>
                        </div>
                        <div class="form-field">
                            <label>Picture (Optional)</label>
                            <input type="file" name="picture" accept="image/*">
                        </div>
                        <div>
                            <button type="submit" name="add_candidate" class="btn btn-primary">Add Candidate</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Reset Election -->
            <div class="section card">
                <h3 class="section-title">
                    Manage Election
                    <span>Admin actions</span>
                </h3>
                <form method="POST" onsubmit="return confirm('Are you sure? This will reset ALL votes and allow students to vote again for the next election.')">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <button type="submit" name="reset_election" class="btn btn-warning">Reset for Next Election</button>
                </form>
                <p class="note">This will clear all votes and allow students to vote again.</p>
            </div>
        </div>
        
        <!-- Candidates List & Results -->
        <div class="section card">
            <h3 class="section-title">
                Candidates and Live Results
                <span>Sorted by position</span>
            </h3>
            <?php
            $candidates_by_election = [];
            $position_winners = [];

            while ($candidate = mysqli_fetch_assoc($candidates)) {
                $candidate_type = normalize_position_label($candidate['election_type'] ?? 'SSG');
                $candidate_position = normalize_position_label($candidate['position'] ?? '');
                $candidate['election_type'] = $candidate_type;
                $candidate['position'] = $candidate_position;
                if (!isset($candidates_by_election[$candidate_type])) {
                    $candidates_by_election[$candidate_type] = [];
                }

                $candidates_by_election[$candidate_type][] = $candidate;

                $winner_key = $candidate_type . '::' . $candidate_position;
                if (!isset($position_winners[$winner_key])) {
                    $position_winners[$winner_key] = $candidate;
                } elseif ($candidate['votes_count'] > $position_winners[$winner_key]['votes_count']) {
                    $position_winners[$winner_key] = $candidate;
                }
            }
            ?>
            <div class="results-accordion">
                <?php foreach (['SSG', 'FTP'] as $election_type): ?>
                    <?php if (!empty($candidates_by_election[$election_type])): ?>
                        <details class="results-panel" <?php echo $election_type === 'SSG' ? 'id="ssg-results"' : ''; ?>>
                            <summary class="results-toggle">
                                <span class="results-title">
                                    <?php if ($election_type === 'SSG'): ?>
                                        <img src="uploads/ssg-logo.png" class="results-logo" alt="SSG logo">
                                    <?php elseif ($election_type === 'FTP'): ?>
                                        <img src="uploads/ftp-logo.png" class="results-logo" alt="FTP logo">
                                    <?php endif; ?>
                                    <span><?php echo h($election_type); ?> Election</span>
                                </span>
                                <span class="position-meta"><?php echo count($candidates_by_election[$election_type]); ?> candidate(s)</span>
                            </summary>

                            <div class="results-body">
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Picture</th>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Details</th>
                                                <th>Votes</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $current_position = '';
                                            foreach ($candidates_by_election[$election_type] as $candidate):
                                                $candidate_position = normalize_position_label($candidate['position'] ?? '');
                                                if ($candidate_position !== $current_position) {
                                                    $current_position = $candidate_position;
                                                    $position_total = $position_counts[$election_type][$current_position] ?? 0;
                                                    echo "<tr class='position-row'><td colspan='7'>" . htmlspecialchars($current_position) . "<span class='position-meta'>" . $position_total . " candidate(s)</span></td></tr>";
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php if($candidate['picture'] && file_exists($candidate['picture'])): ?>
                                                        <img src="<?php echo $candidate['picture']; ?>" class="candidate-img" alt="Candidate photo">
                                                    <?php else: ?>
                                                        <span class="muted">No photo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo h($candidate['name']); ?></td>
                                                <td><?php echo h($candidate_position); ?></td>
                                                <td><?php echo h(substr($candidate['details'], 0, 60)) . '...'; ?></td>
                                                <td><strong><?php echo (int)$candidate['votes_count']; ?></strong></td>
                                                <td>
                                                    <?php
                                                    $winner_key = $election_type . '::' . $candidate['position'];
                                                    if (isset($position_winners[$winner_key]) && $position_winners[$winner_key]['id'] == $candidate['id'] && $candidate['votes_count'] > 0) {
                                                        echo "<span class='badge'>Leading</span>";
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                        <div class="candidate-actions">
                                                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleCandidateEditor('candidate-edit-<?php echo (int)$candidate['id']; ?>')">Edit</button>
                                                            <form method="POST" onsubmit="return confirm('Remove this candidate?')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                                                <input type="hidden" name="remove_candidate" value="<?php echo (int)$candidate['id']; ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                                            </form>
                                                        </div>
                                                </td>
                                            </tr>
                                                <tr class="candidate-edit-row" id="candidate-edit-<?php echo (int)$candidate['id']; ?>" <?php echo $active_edit_candidate_id === (int)$candidate['id'] ? '' : 'hidden'; ?>>
                                                    <td colspan="7">
                                                        <div class="candidate-edit-shell">
                                                            <div class="candidate-edit-head">
                                                                <div>
                                                                    <strong>Edit Candidate</strong>
                                                                    <div class="progress-subtitle">Update the candidate photo, name, position, and details.</div>
                                                                </div>
                                                                <?php if($candidate['picture'] && file_exists($candidate['picture'])): ?>
                                                                    <img src="<?php echo h($candidate['picture']); ?>" class="candidate-edit-preview" alt="Candidate photo preview">
                                                                <?php endif; ?>
                                                            </div>
                                                            <form method="POST" enctype="multipart/form-data">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                                                <input type="hidden" name="update_candidate" value="1">
                                                                <input type="hidden" name="candidate_id" value="<?php echo (int)$candidate['id']; ?>">
                                                                <div class="candidate-edit-grid">
                                                                    <div class="form-field">
                                                                        <label>Election Type</label>
                                                                        <select name="election_type" required>
                                                                            <option value="SSG" <?php echo strtoupper(trim($candidate['election_type'] ?? 'SSG')) === 'SSG' ? 'selected' : ''; ?>>SSG Election</option>
                                                                            <option value="FTP" <?php echo strtoupper(trim($candidate['election_type'] ?? 'SSG')) === 'FTP' ? 'selected' : ''; ?>>FTP Election</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-field">
                                                                        <label>Full Name</label>
                                                                        <input type="text" name="name" required value="<?php echo h($candidate['name']); ?>">
                                                                    </div>
                                                                    <div class="form-field">
                                                                        <label>Position</label>
                                                                        <input type="text" name="position" required value="<?php echo h($candidate['position']); ?>">
                                                                    </div>
                                                                    <div class="form-field">
                                                                        <label>Picture (Optional)</label>
                                                                        <input type="file" name="picture" accept="image/*">
                                                                        <div class="progress-subtitle">Leave blank to keep the current photo.</div>
                                                                    </div>
                                                                    <div class="form-field">
                                                                        <label>Details/Bio</label>
                                                                        <textarea name="details" rows="4"><?php echo h($candidate['details']); ?></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="candidate-edit-footer">
                                                                    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleCandidateEditor('candidate-edit-<?php echo (int)$candidate['id']; ?>')">Cancel</button>
                                                                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if($candidate_count == 0): ?>
                    <div class="notice card" style="margin-top: 0;">
                        <h3>No candidates added yet.</h3>
                        <p>Add candidates above.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(!empty($position_winners)): ?>
                    <details class="winners">
                        <summary class="winners-summary">
                            <h4>Current Winners (by position)</h4>
                            <span class="position-meta"><?php echo count($position_winners); ?> position(s)</span>
                        </summary>
                        <div class="winners-body">
                    <div class="winners-grid">
                        <?php foreach($position_winners as $pos => $winner): ?>
                            <div class="winner-card winner-card--text">
                                <div class="winner-body">
                                    <?php [$winner_type, $winner_position] = array_pad(explode('::', $pos, 2), 2, ''); ?>
                                    <div class="winner-position"><?php echo h(trim($winner_type) . ' Election - ' . trim($winner_position)); ?></div>
                                    <div class="winner-name"><?php echo h($winner['name']); ?></div>
                                    <div class="winner-meta">
                                        <span class="votes-count"><?php echo (int)$winner['votes_count']; ?></span>
                                        <span class="votes-label">vote<?php echo ((int)$winner['votes_count'] !== 1) ? 's' : ''; ?></span>
                                        <?php if((int)$winner['votes_count'] > 0): ?>
                                            <span class="badge winner-leading">Leading</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </main>
        <script>
            // Keep the admin dashboard open and force logout through a modal when the browser back button is used.
            (function lockDashboardHistory() {
                window.history.scrollRestoration = 'manual';

                let promptingLogout = false;
                let logoutInProgress = false;
                let handlingBack = false;

                const logoutButton = document.querySelector('.logout-btn');
                if (logoutButton) {
                    logoutButton.addEventListener('click', () => {
                        logoutInProgress = true;
                    });
                }

                const showLogoutNotice = () => {
                    if (promptingLogout) {
                        return;
                    }

                    promptingLogout = true;
                    Swal.fire({
                        title: 'Logout required',
                        text: 'Please click Logout to leave the admin dashboard.',
                        icon: 'warning',
                        confirmButtonText: 'Logout',
                        confirmButtonColor: '#111111',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        showCancelButton: false,
                    }).then((result) => {
                        promptingLogout = false;
                        if (result.isConfirmed) {
                        logoutInProgress = true;
                            window.location.href = 'logout.php';
                        }
                    });
                };

                window.history.replaceState({ dashboard: true }, '', window.location.href);
                window.history.pushState({ dashboard: true }, '', window.location.href);

                window.addEventListener('popstate', () => {
                    if (handlingBack) {
                        return;
                    }

                    handlingBack = true;
                    window.history.go(1);
                    setTimeout(() => {
                        showLogoutNotice();
                        handlingBack = false;
                    }, 0);
                });

                window.addEventListener('pageshow', (event) => {
                    if (event.persisted) {
                        window.history.replaceState({ dashboard: true }, '', window.location.href);
                        window.history.pushState({ dashboard: true }, '', window.location.href);
                    }
                });

                window.addEventListener('beforeunload', (event) => {
                    if (!logoutInProgress) {
                        event.preventDefault();
                        event.returnValue = '';
                    }
                });

                // Allow form submissions to proceed without the "leave site" warning
                document.addEventListener('submit', () => {
                    logoutInProgress = true;
                });
            })();

            const adminBody = document.body;
            let lastTotalVotes = Number(adminBody.getAttribute('data-total-votes') || 0);

            async function checkForVoteUpdates() {
                try {
                    const response = await fetch('admin.php?stats=1&t=' + Date.now(), {
                        cache: 'no-store'
                    });
                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    if (typeof data.total_votes === 'number' && data.total_votes !== lastTotalVotes) {
                        window.location.reload();
                    }
                } catch (error) {
                    console.error('Failed to check vote updates:', error);
                }
            }

            setInterval(checkForVoteUpdates, 5000);

            function toggleCandidateEditor(rowId) {
                const row = document.getElementById(rowId);
                if (!row) {
                    return;
                }

                row.hidden = !row.hidden;
                if (!row.hidden) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

        </script>
</body>
</html>