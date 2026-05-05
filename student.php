<?php 
include 'config/db.php';

// FIXED: Check if user is logged in AND is NOT admin
// The original condition was redirecting ALL students
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true)) {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Check if student has already voted
$stmt = mysqli_prepare($conn, 'SELECT has_voted FROM students WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$student) {
    $error = 'Unable to load student information.';
    $student = ['has_voted' => false];
}

// Ensure `has_voted` matches actual votes in the votes table.
if (!empty($student) && isset($student['has_voted'])) {
    $reported = (bool)$student['has_voted'];
    $check_votes = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM votes WHERE student_id = ?');
    mysqli_stmt_bind_param($check_votes, 'i', $student_id);
    mysqli_stmt_execute($check_votes);
    $votes_res = mysqli_stmt_get_result($check_votes);
    $votes_row = mysqli_fetch_assoc($votes_res);
    mysqli_stmt_close($check_votes);

    $real_votes = isset($votes_row['cnt']) ? (int)$votes_row['cnt'] : 0;

    if ($real_votes > 0) {
        // Student really has votes
        $student['has_voted'] = true;
    } else {
        // No votes found — if DB flag says voted, fix it
        if ($reported) {
            $fix = mysqli_prepare($conn, 'UPDATE students SET has_voted = FALSE WHERE id = ?');
            mysqli_stmt_bind_param($fix, 'i', $student_id);
            mysqli_stmt_execute($fix);
            mysqli_stmt_close($fix);
        }
        $student['has_voted'] = false;
    }
}

// Get all candidates grouped by election type and position
$candidates_by_group = [];
$candidate_type_check = mysqli_query($conn, "SHOW COLUMNS FROM candidates LIKE 'election_type'");
$candidate_has_election_type = $candidate_type_check && mysqli_num_rows($candidate_type_check) > 0;

$query = $candidate_has_election_type
    ? "SELECT * FROM candidates ORDER BY CASE WHEN election_type = 'SSG' THEN 0 WHEN election_type = 'FTP' THEN 1 ELSE 2 END, position, name"
    : "SELECT *, 'SSG' AS election_type FROM candidates ORDER BY position, name";
$result = mysqli_query($conn, $query);

if (!$result) {
    $error = 'Database Error: ' . mysqli_error($conn);
} else {
    while ($row = mysqli_fetch_assoc($result)) {
        $raw_position = isset($row['position']) ? (string)$row['position'] : '';
        $position = trim(strtoupper($raw_position));
        if ($position === '') {
            $position = 'OTHER';
        }

        $election_type = strtoupper(trim($row['election_type'] ?? 'SSG'));
        $group_key = $election_type . '::' . $position;
        if (!isset($candidates_by_group[$group_key])) {
            $candidates_by_group[$group_key] = [
                'election_type' => $election_type,
                'position' => $position,
                'candidates' => [],
            ];
        }
        $candidates_by_group[$group_key]['candidates'][] = $row;
    }
    mysqli_free_result($result);
}

// Order groups by election type first, then position.
if (!empty($candidates_by_group)) {
    $election_order = ['SSG' => 0, 'FTP' => 1];
    $group_list = array_values($candidates_by_group);

    usort($group_list, function ($a, $b) use ($election_order) {
        $left_election = $election_order[$a['election_type']] ?? 2;
        $right_election = $election_order[$b['election_type']] ?? 2;

        if ($left_election !== $right_election) {
            return $left_election <=> $right_election;
        }

        return strcasecmp($a['position'] ?? '', $b['position'] ?? '');
    });

    $ordered_candidates = [];
    foreach ($group_list as $group) {
        usort($group['candidates'], function ($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        $ordered_candidates[$group['election_type'] . '::' . $group['position']] = $group;
    }

    $candidates_by_group = $ordered_candidates;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ballot']) && !$student['has_voted']) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $selected_votes = isset($_POST['votes']) && is_array($_POST['votes']) ? $_POST['votes'] : [];
        $missing_positions = [];
        $chosen_candidates = [];

        if (empty($candidates_by_group)) {
            $error = 'No candidates available yet.';
        } else {
            foreach ($candidates_by_group as $group_key => $group) {
                if (!isset($selected_votes[$group_key])) {
                    $missing_positions[] = $group['election_type'] . ' ' . $group['position'];
                    continue;
                }

                $candidate_id = (int)$selected_votes[$group_key];
                $valid_ids = array_map('intval', array_column($group['candidates'], 'id'));
                if (!in_array($candidate_id, $valid_ids, true)) {
                    $missing_positions[] = $group['election_type'] . ' ' . $group['position'];
                    continue;
                }

                $chosen_candidates[] = $candidate_id;
            }

            if (!empty($missing_positions)) {
                $error = 'Please select a candidate for each position.';
            } else {
                $check = mysqli_prepare($conn, 'SELECT 1 FROM votes WHERE student_id = ? LIMIT 1');
                mysqli_stmt_bind_param($check, 'i', $student_id);
                mysqli_stmt_execute($check);
                $check_result = mysqli_stmt_get_result($check);

                if ($check_result && mysqli_num_rows($check_result) == 0) {
                    mysqli_begin_transaction($conn);
                    $vote_stmt = mysqli_prepare($conn, 'INSERT INTO votes (student_id, candidate_id) VALUES (?, ?)');
                    $count_stmt = mysqli_prepare($conn, 'UPDATE candidates SET votes_count = votes_count + 1 WHERE id = ?');
                    $student_stmt = mysqli_prepare($conn, 'UPDATE students SET has_voted = TRUE WHERE id = ?');

                    $vote_ok = true;
                    foreach ($chosen_candidates as $candidate_id) {
                        mysqli_stmt_bind_param($vote_stmt, 'ii', $student_id, $candidate_id);
                        $vote_ok = $vote_ok && mysqli_stmt_execute($vote_stmt);

                        mysqli_stmt_bind_param($count_stmt, 'i', $candidate_id);
                        $vote_ok = $vote_ok && mysqli_stmt_execute($count_stmt);
                    }

                    mysqli_stmt_bind_param($student_stmt, 'i', $student_id);
                    $vote_ok = $vote_ok && mysqli_stmt_execute($student_stmt);

                    if ($vote_ok) {
                        mysqli_commit($conn);
                        $success = 'Ballot submitted successfully! Thank you for voting.';
                        $student['has_voted'] = true;
                    } else {
                        mysqli_rollback($conn);
                        $error = 'Unable to submit ballot. Please try again.';
                    }

                    mysqli_stmt_close($vote_stmt);
                    mysqli_stmt_close($count_stmt);
                    mysqli_stmt_close($student_stmt);
                } else {
                    $error = 'You have already submitted your ballot.';
                    $student['has_voted'] = true;
                }

                mysqli_stmt_close($check);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - BISU Voting</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        .welcome-card {
            padding: 1.6rem 1.7rem;
            margin-bottom: 1.6rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.25rem;
            animation: rise 0.65s ease both;
            background: linear-gradient(135deg, rgba(23, 59, 114, 0.05), rgba(17, 124, 107, 0.06)), #fff;
        }

        .welcome-card p {
            color: var(--muted);
            margin-top: 0.35rem;
        }

        .status-chip {
            border: 1px solid rgba(23, 59, 114, 0.12);
            padding: 0.48rem 0.9rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.84rem;
            background: linear-gradient(135deg, rgba(23, 59, 114, 0.1), rgba(17, 124, 107, 0.12));
        }

        .ballot-form {
            display: grid;
            gap: 1.5rem;
        }

        .election-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem;
        }

        .election-panel {
            padding: 1rem;
            border-radius: 20px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
            animation: rise 0.6s ease both;
        }

        .election-panel[open] {
            border-color: rgba(23, 59, 114, 0.16);
        }

        .panel-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            cursor: pointer;
            list-style: none;
            font-weight: 800;
            color: var(--accent-strong);
            padding: 0.2rem 0.1rem 0.9rem;
        }

        .panel-title {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }

        .panel-toggle::-webkit-details-marker {
            display: none;
        }

        .panel-toggle::after {
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
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .election-panel[open] .panel-toggle::after {
            content: '–';
            background: rgba(17, 124, 107, 0.12);
        }

        .panel-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.35rem;
            color: var(--muted);
            font-weight: 600;
            font-size: 0.86rem;
        }

        .panel-logo {
            width: 1.7rem;
            height: 1.7rem;
            object-fit: contain;
            display: block;
            flex: 0 0 auto;
        }

        .panel-body {
            display: grid;
            gap: 1rem;
            padding-top: 0.35rem;
        }

        .position-section {
            padding: 1.55rem;
            margin-bottom: 0;
            animation: rise 0.6s ease both;
            border-top: 4px solid var(--accent);
        }

        .position-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 1.15rem;
        }

        .position-title span {
            font-size: 0.82rem;
            color: var(--muted);
            white-space: nowrap;
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .candidate-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            padding: 0.85rem;
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .candidate-card:hover,
        .candidate-card:focus-within {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.1);
            border-color: rgba(23, 59, 114, 0.18);
        }

        .candidate-photo {
            aspect-ratio: 16 / 9;
            width: 100%;
            min-height: 100px;
            padding: 0.35rem;
            border-radius: 14px;
            background: radial-gradient(circle at top, rgba(255, 255, 255, 0.9), transparent 40%),
                        linear-gradient(135deg, rgba(23, 59, 114, 0.06), rgba(17, 124, 107, 0.08));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .candidate-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            transition: transform 0.35s ease;
        }

        .candidate-card:hover .candidate-img {
            transform: scale(1.02);
        }

        .photo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: var(--muted);
            font-weight: 700;
            letter-spacing: 0.02em;
            font-size: 0.82rem;
        }

        .candidate-name {
            font-size: 1rem;
            font-weight: 800;
            color: var(--accent-strong);
        }

        .candidate-details {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.55;
            flex: 1;
        }

        .candidate-choice {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-weight: 700;
            color: var(--accent-strong);
            padding: 0.8rem 0.9rem;
            border-radius: 14px;
            background: rgba(23, 59, 114, 0.04);
        }

        .candidate-choice input {
            width: 18px;
            height: 18px;
            margin: 0.15rem 0 0;
            accent-color: var(--accent);
            flex: 0 0 auto;
        }

        .candidate-choice input:focus-visible {
            outline: 2px solid rgba(23, 59, 114, 0.3);
            outline-offset: 2px;
        }

        .ballot-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.25rem;
            padding: 1.5rem;
            margin-top: 0.2rem;
            background: linear-gradient(135deg, rgba(23, 59, 114, 0.04), rgba(17, 124, 107, 0.04));
        }

        .ballot-actions p {
            color: var(--muted);
            max-width: 42rem;
        }

        .notice {
            text-align: center;
            padding: 2.2rem 1.6rem;
            background: linear-gradient(180deg, #ffffff, #f7fbff);
        }

        .notice h3 {
            margin-bottom: 0.45rem;
        }

        .notice p {
            color: var(--muted);
        }

        @media (max-width: 720px) {
            .welcome-card,
            .ballot-actions,
            .position-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .election-grid {
                grid-template-columns: 1fr;
            }

            .status-chip,
            .position-title span {
                width: 100%;
            }

            .position-section,
            .ballot-actions,
            .notice {
                padding: 1.15rem;
            }
        }

        @media (max-width: 640px) {
            .dashboard-header,
            .user-meta {
                width: 100%;
            }

            .logout-btn,
            .ballot-actions .btn {
                width: 100%;
            }

            .candidates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="topbar dashboard-header">
        <div class="brand">
            <span class="brand-mark"></span>
            <div>
                <div class="brand-title">BISU Voting System</div>
                <div class="brand-sub">Student Dashboard</div>
            </div>
        </div>
        <div class="user-meta">
            <span class="user-name">Welcome, <?php echo h($_SESSION['user_name'] ?? 'Student'); ?></span>
            <a href="logout.php" class="btn btn-ghost logout-btn">Logout</a>
        </div>
    </header>
    
    <main class="container">
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div class="welcome-card card">
            <div>
                <h3>Hello, <?php echo h($_SESSION['user_name'] ?? 'Student'); ?>!</h3>
                <p>Review each candidate carefully before submitting your final vote.</p>
            </div>
            <div class="status-chip">
                <?php echo $student['has_voted'] ? '✓ Vote submitted' : '🗳️ Voting open'; ?>
            </div>
        </div>
        
        <?php if($student['has_voted']): ?>
            <div class="notice card">
                <h3>Thank you for voting.</h3>
                <p>Your ballot has been recorded successfully.</p>
                <button type="button" class="btn btn-primary" id="view-votes-btn">View your votes</button>
            </div>
        <?php else: ?>
            <?php if(!empty($candidates_by_group)): ?>
                <form method="POST" class="ballot-form" onsubmit="return confirm('Submit your ballot now? You cannot change it afterward.')">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <?php
                    $election_groups = [
                        'SSG' => [],
                        'FTP' => [],
                    ];

                    foreach ($candidates_by_group as $group_key => $group) {
                        $election_type = strtoupper(trim($group['election_type'] ?? 'SSG'));
                        if (!isset($election_groups[$election_type])) {
                            $election_groups[$election_type] = [];
                        }
                        $election_groups[$election_type][] = [
                            'group_key' => $group_key,
                            'group' => $group,
                        ];
                    }
                    ?>

                    <div class="election-grid">
                        <?php foreach ($election_groups as $election_type => $groups): ?>
                            <?php if (!empty($groups)): ?>
                                <details class="election-panel">
                                    <summary class="panel-toggle">
                                        <span class="panel-title">
                                            <?php if ($election_type === 'SSG'): ?>
                                                <img src="uploads/ssg-logo.png" class="panel-logo" alt="SSG logo">
                                            <?php elseif ($election_type === 'FTP'): ?>
                                                <img src="uploads/ftp-logo.png" class="panel-logo" alt="FTP logo">
                                            <?php endif; ?>
                                            <span><?php echo h($election_type); ?> Election</span>
                                        </span>
                                        <span class="panel-meta">
                                            <span><?php echo count($groups); ?> position(s)</span>
                                        </span>
                                    </summary>

                                    <div class="panel-body">
                                        <?php foreach ($groups as $entry): ?>
                                            <?php $group_key = $entry['group_key']; $group = $entry['group']; ?>
                                            <div class="position-section card">
                                                <div class="position-title">
                                                    <h3><?php echo h($group['election_type'] . ' Election - ' . $group['position']); ?></h3>
                                                    <span><?php echo count($group['candidates']); ?> candidate(s)</span>
                                                </div>

                                                <div class="candidates-grid">
                                                    <?php foreach($group['candidates'] as $candidate): ?>
                                                        <label class="candidate-card">
                                                            <div class="candidate-photo">
                                                                <?php
                                                                $picture_path = trim($candidate['picture'] ?? '');
                                                                $picture_src = '';
                                                                if ($picture_path !== '') {
                                                                    // Check as-provided path
                                                                    if (file_exists($picture_path)) {
                                                                        $picture_src = $picture_path;
                                                                    } elseif (file_exists(__DIR__ . '/' . $picture_path)) {
                                                                        $picture_src = $picture_path;
                                                                    } elseif (file_exists(__DIR__ . '/uploads/' . basename($picture_path))) {
                                                                        $picture_src = 'uploads/' . basename($picture_path);
                                                                    }
                                                                }
                                                                if ($picture_src !== ''):
                                                                ?>
                                                                    <img src="<?php echo h($picture_src); ?>" class="candidate-img" alt="<?php echo h($candidate['name']); ?>">
                                                                <?php else: ?>
                                                                    <div class="photo-placeholder">
                                                                        📷 No Photo Available
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="candidate-name"><?php echo h($candidate['name']); ?></div>

                                                            <div class="candidate-details">
                                                                <?php echo !empty($candidate['details']) ? nl2br(h($candidate['details'])) : 'No details provided'; ?>
                                                            </div>

                                                            <div class="candidate-choice">
                                                                <input type="radio"
                                                                       name="votes[<?php echo h($group_key); ?>]"
                                                                       value="<?php echo (int)$candidate['id']; ?>"
                                                                       required>
                                                                <span>Vote for this candidate</span>
                                                            </div>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="ballot-actions card">
                        <div>
                            <h3>Submit your ballot</h3>
                            <p class="muted">You can only submit once. Review your choices carefully.</p>
                        </div>
                        <button type="submit" name="submit_ballot" class="btn btn-primary">🗳️ Submit Ballot</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="notice card">
                    <h3>No Candidates Available</h3>
                    <p>There are currently no candidates in the voting system.</p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem;">Please contact the administrator.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <script>
        // Make candidate cards clickable
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.type !== 'radio') {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                }
            });
        });
        
        // View submitted votes
        const viewVotesBtn = document.getElementById('view-votes-btn');
        if (viewVotesBtn) {
            viewVotesBtn.addEventListener('click', () => {
                fetch('get_student_votes.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.votes.length > 0) {
                            let votesHTML = '<div style="text-align: left;">';
                            votesHTML += '<div style="display: grid; gap: 1rem;">';
                            
                            data.votes.forEach(vote => {
                                const electionLabel = vote.election_type ? `${vote.election_type} Election` : 'Election';
                                votesHTML += `
                                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #2c3e50;">
                                        <div style="font-weight: 600; color: #666; margin-bottom: 0.5rem;">${electionLabel} - ${vote.position}</div>
                                        <div style="font-weight: 700; font-size: 1.1rem;">${vote.candidate_name}</div>
                                    </div>
                                `;
                            });
                            
                            votesHTML += '</div></div>';
                            
                            Swal.fire({
                                title: 'Your Votes',
                                html: votesHTML,
                                icon: 'success',
                                confirmButtonText: 'Close'
                            });
                        } else {
                            Swal.fire({
                                title: 'No votes found',
                                text: 'You haven\'t submitted any votes yet.',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Could not load your votes.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            });
        }
    </script>
</body>
</html>