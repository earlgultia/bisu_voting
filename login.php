<?php include 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email']));
        $password = $_POST['password'];
        $email_hash = hash('sha256', $email);

        // Use hashed email column if present, otherwise fall back to plaintext email
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
        $use_hash = ($col_check && mysqli_num_rows($col_check) > 0);

        if ($use_hash) {
            $stmt = mysqli_prepare($conn, 'SELECT id, first_name, last_name, password FROM students WHERE email_hash = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $email_hash);
        } else {
            $stmt = mysqli_prepare($conn, 'SELECT id, first_name, last_name, password FROM students WHERE email = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $email);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $stored_password = $user['password'];
            $password_matches = password_verify($password, $stored_password) || hash_equals($stored_password, md5($password));

            if ($password_matches) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

                // Determine admin status safely (only query if column exists)
                $is_admin = false;
                $admin_col = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'is_admin'");
                if ($admin_col && mysqli_num_rows($admin_col) > 0) {
                    $checkAdmin = mysqli_prepare($conn, 'SELECT is_admin FROM students WHERE id = ? LIMIT 1');
                    mysqli_stmt_bind_param($checkAdmin, 'i', $user['id']);
                    mysqli_stmt_execute($checkAdmin);
                    $admin_res = mysqli_stmt_get_result($checkAdmin);
                    if ($admin_res && mysqli_num_rows($admin_res) == 1) {
                        $admin_row = mysqli_fetch_assoc($admin_res);
                        $is_admin = !empty($admin_row['is_admin']);
                    }
                    mysqli_stmt_close($checkAdmin);
                } else {
                    // fallback to plaintext admin email if schema is old
                    if (!$use_hash && $email === 'admin.ssg@bisu.edu.ph') {
                        $is_admin = true;
                    }
                }
                $_SESSION['is_admin'] = $is_admin;

                if (!password_verify($password, $stored_password)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update = mysqli_prepare($conn, 'UPDATE students SET password = ? WHERE id = ?');
                    mysqli_stmt_bind_param($update, 'si', $new_hash, $user['id']);
                    mysqli_stmt_execute($update);
                    mysqli_stmt_close($update);
                }

                mysqli_stmt_close($stmt);
                $redirect_target = $_SESSION['is_admin'] ? 'admin.php' : 'student.php';
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Signing in...</title>
                </head>
                <body>
                    <script>
                        window.location.replace(<?php echo json_encode($redirect_target); ?>);
                    </script>
                </body>
                </html>
                <?php
                exit();
            }
        }

        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            mysqli_stmt_close($stmt);
        }
        $error = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BISU Voting System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .auth-shell {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            align-items: stretch;
            max-width: 520px;
            margin: 0 auto;
        }
        .auth-card {
            padding: 2rem;
            animation: rise 0.7s ease both;
            position: relative;
            z-index: 1;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.28em;
            font-size: 0.72rem;
            color: var(--muted);
            margin-bottom: 0.8rem;
        }
        .auth-card h1 {
            margin-bottom: 0.4rem;
            font-size: clamp(2rem, 4vw, 2.5rem);
        }
        .auth-card .muted {
            margin-bottom: 1.6rem;
            line-height: 1.6;
        }
        .form-stack {
            display: grid;
            gap: 1rem;
        }
        .form-actions {
            margin-top: 0.6rem;
            display: grid;
            gap: 0.8rem;
        }
        .password-field {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-field input {
            padding-right: 3.2rem;
        }
        .toggle-password {
            position: absolute;
            right: 0.7rem;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 15px;
            width: 34px;
            height: 34px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .toggle-password:hover {
            color: var(--accent);
            transform: scale(1.1);
        }
        .toggle-password:focus {
            outline: none;
        }
        .btn-block {
            width: 100%;
        }
        .helper {
            margin-top: 1.2rem;
            font-weight: 600;
            display: grid;
            gap: 0.6rem;
            color: var(--muted);
        }
        .helper-line {
            text-align: left;
        }
        .text-link {
            color: var(--accent);
        }
        .home-link {
            color: var(--muted);
            font-weight: 600;
            text-decoration: none;
            justify-self: center;
        }
        .home-link:hover {
            color: var(--accent-strong);
            text-decoration: underline;
        }
        @media (max-width: 900px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }
            .auth-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .auth-card {
                padding: 1.25rem;
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <section class="auth-shell">
            <div class="auth-card card">
                <p class="eyebrow">Student Portal</p>
                <h1>Sign in to vote</h1>
                <p class="muted">Use your BISU email and password to access the ballot.</p>
                <?php if(isset($_GET['registered'])) echo "<div class='alert alert-success'>Registration complete. You can now sign in.</div>"; ?>
                <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
                <form method="POST" class="form-stack">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="form-field">
                        <label>Email</label>
                        <input type="email" name="email" required placeholder="your.email@bisu.edu.ph">
                    </div>
                    <div class="form-field">
                        <label>Password</label>
                        <div class="password-field">
                            <input type="password" id="login-password" name="password" required>
                            <button type="button" class="toggle-password" data-target="login-password" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </div>
                </form>
                <div class="helper">
                    <div class="helper-line">No account yet? <a class="text-link" href="register.php">Create one here.</a></div>
                    <a class="home-link" href="index.php">Back to Home</a>
                </div>
            </div>
        </section>
    </main>
    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach((button) => {
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) {
                return;
            }
            button.addEventListener('click', () => {
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                const icon = button.querySelector('i');
                if (isText) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
                button.setAttribute('aria-pressed', String(!isText));
                button.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
            });
        });
    </script>
</body>
</html>