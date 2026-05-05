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

// Reset election (delete all votes and reset voting status)
if (isset($_POST['reset_election'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        mysqli_query($conn, "TRUNCATE TABLE votes");
        mysqli_query($conn, "UPDATE candidates SET votes_count = 0");
        // Reset students' voting status but try to avoid referencing missing columns
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'is_admin'");
        if ($col_check && mysqli_num_rows($col_check) > 0) {
            mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE is_admin = 0");
        } else {
            // fallback: prefer email_hash if present, otherwise plaintext email
            $hash_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
            if ($hash_check && mysqli_num_rows($hash_check) > 0) {
                $admin_hash = hash('sha256', strtolower('admin.ssg@bisu.edu.ph'));
                $admin_hash_esc = mysqli_real_escape_string($conn, $admin_hash);
                mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE email_hash != '" . $admin_hash_esc . "'");
            } else {
                $admin_email_esc = mysqli_real_escape_string($conn, 'admin.ssg@bisu.edu.ph');
                mysqli_query($conn, "UPDATE students SET has_voted = FALSE WHERE email != '" . $admin_email_esc . "'");
            }
        }
        $success = 'Election has been reset for the next election!';
    }
}

// Get all candidates
$candidates = $candidate_has_election_type
    ? mysqli_query($conn, "SELECT * FROM candidates ORDER BY CASE WHEN election_type = 'SSG' THEN 0 WHEN election_type = 'FTP' THEN 1 ELSE 2 END, position, votes_count DESC, name ASC")
    : mysqli_query($conn, "SELECT *, 'SSG' AS election_type FROM candidates ORDER BY position, votes_count DESC, name ASC");
$total_votes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM votes"))['total'];
$candidate_count = mysqli_num_rows($candidates);
$position_counts = [];
$position_count_result = $candidate_has_election_type
    ? mysqli_query($conn, "SELECT election_type, position, COUNT(*) as total FROM candidates GROUP BY election_type, position")
    : mysqli_query($conn, "SELECT 'SSG' AS election_type, position, COUNT(*) as total FROM candidates GROUP BY position");
while ($row = mysqli_fetch_assoc($position_count_result)) {
    $type_key = strtoupper(trim($row['election_type'] ?? 'SSG'));
    $position_key = trim((string)$row['position']);
    $position_counts[$type_key][$position_key] = (int)$row['total'];
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
            <span class="brand-mark"></span>
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
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Picture</th>
                            <th>Election</th>
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
                        $current_election_type = '';
                        $position_winners = [];
                        while($candidate = mysqli_fetch_assoc($candidates)): 
                            $candidate_type = strtoupper(trim($candidate['election_type'] ?? 'SSG'));
                            if ($candidate_type !== $current_election_type) {
                                $current_election_type = $candidate_type;
                                $current_position = '';
                                echo "<tr class='position-row'><td colspan='8'>" . htmlspecialchars($current_election_type) . " Election</td></tr>";
                            }

                            if ($candidate['position'] !== $current_position) {
                                $current_position = $candidate['position'];
                                $position_total = $position_counts[$candidate_type][$current_position] ?? 0;
                                echo "<tr class='position-row'><td colspan='8'>" . htmlspecialchars($current_position) . "<span class='position-meta'>" . $position_total . " candidate(s)</span></td></tr>";
                            }
                            // Determine winner per position
                            $winner_key = $candidate_type . '::' . $candidate['position'];
                            if(!isset($position_winners[$winner_key])) {
                                $position_winners[$winner_key] = $candidate;
                            } else {
                                if($candidate['votes_count'] > $position_winners[$winner_key]['votes_count']) {
                                    $position_winners[$winner_key] = $candidate;
                                }
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
                            <td><?php echo h($candidate_type . ' Election'); ?></td>
                            <td><?php echo h($candidate['name']); ?></td>
                            <td><?php echo h($candidate['position']); ?></td>
                            <td><?php echo h(substr($candidate['details'], 0, 60)) . '...'; ?></td>
                            <td><strong><?php echo (int)$candidate['votes_count']; ?></strong></td>
                            <td>
                                <?php 
                                if($position_winners[$winner_key]['id'] == $candidate['id'] && $candidate['votes_count'] > 0) {
                                    echo "<span class='badge'>Leading</span>";
                                }
                                ?>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Remove this candidate?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                    <input type="hidden" name="remove_candidate" value="<?php echo (int)$candidate['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($candidate_count == 0): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No candidates added yet. Add candidates above.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if(!empty($position_winners)): ?>
                <div class="winners">
                    <h4>Current Winners (by position)</h4>
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
        </script>
</body>
</html>