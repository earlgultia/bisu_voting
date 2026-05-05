<?php include 'config/db.php';

$college_courses = [
    'College of Science' => [
        'Computer Science (BSCS)',
        'Environmental Science (BSES)',
    ],
    'College of Business Management' => [
        'Hospitality Management (BSHM)',
        'Office Administration (BSOA)',
    ],
    'College of Fisheries and Marine Sciences' => [
        'Fisheries (BSF)',
        'Marine Biology (BSMB)',
    ],
    'College of Teachers Education' => [
        'Elementary Education (BEEd)',
        'Secondary Education (BSEd)',
    ],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $address = trim($_POST['address']);
        $college = trim($_POST['college']);
        $course = trim($_POST['course']);
        $email = strtolower(trim($_POST['email']));
        $email_hash = hash('sha256', $email);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $error = 'Passwords do not match!';
        } elseif ($college === '' || $course === '') {
            $error = 'College and course are required.';
        } elseif (!isset($college_courses[$college]) || !in_array($course, $college_courses[$college], true)) {
            $error = 'Please choose a course that matches your college.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@bisu.edu.ph')) {
            $error = 'Email must end with @bisu.edu.ph';
        } else {
            // Detect whether the students table has the email_hash column
            $col_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'email_hash'");
            $use_hash = ($col_check && mysqli_num_rows($col_check) > 0);
            $college_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'college'");
            $course_check = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'course'");
            $use_profile_fields = (
                $college_check && mysqli_num_rows($college_check) > 0
                && $course_check && mysqli_num_rows($course_check) > 0
            );

            if ($use_hash) {
                $email_check = mysqli_prepare($conn, 'SELECT 1 FROM students WHERE email_hash = ? LIMIT 1');
                mysqli_stmt_bind_param($email_check, 's', $email_hash);
            } else {
                $email_check = mysqli_prepare($conn, 'SELECT 1 FROM students WHERE email = ? LIMIT 1');
                mysqli_stmt_bind_param($email_check, 's', $email);
            }
            mysqli_stmt_execute($email_check);
            $email_result = mysqli_stmt_get_result($email_check);

            if ($email_result && mysqli_num_rows($email_result) > 0) {
                $error = 'Email already exists!';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert profile fields when the current schema supports them.
                if ($use_profile_fields) {
                    if ($use_hash) {
                        $stmt = mysqli_prepare($conn, 'INSERT INTO students (first_name, last_name, complete_address, college, course, email_hash, password) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        mysqli_stmt_bind_param($stmt, 'sssssss', $first_name, $last_name, $address, $college, $course, $email_hash, $hashed_password);
                    } else {
                        $stmt = mysqli_prepare($conn, 'INSERT INTO students (first_name, last_name, complete_address, college, course, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        mysqli_stmt_bind_param($stmt, 'sssssss', $first_name, $last_name, $address, $college, $course, $email, $hashed_password);
                    }
                } else {
                    if ($use_hash) {
                        $stmt = mysqli_prepare($conn, 'INSERT INTO students (first_name, last_name, complete_address, email_hash, password) VALUES (?, ?, ?, ?, ?)');
                        mysqli_stmt_bind_param($stmt, 'sssss', $first_name, $last_name, $address, $email_hash, $hashed_password);
                    } else {
                        $stmt = mysqli_prepare($conn, 'INSERT INTO students (first_name, last_name, complete_address, email, password) VALUES (?, ?, ?, ?, ?)');
                        mysqli_stmt_bind_param($stmt, 'sssss', $first_name, $last_name, $address, $email, $hashed_password);
                    }
                }

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    mysqli_stmt_close($email_check);
                    header('Location: login.php?registered=1');
                    exit();
                }

                $error = 'Registration failed. Please try again.';
                mysqli_stmt_close($stmt);
            }

            mysqli_stmt_close($email_check);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BISU Voting System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-shell {
            width: min(760px, 100%);
            margin: 0 auto;
        }

        .auth-card {
            padding: 2rem;
            position: relative;
            overflow: hidden;
            animation: rise 0.7s ease both;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 6px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            margin-bottom: 1.2rem;
        }

        .brand-badge {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            box-shadow: 0 16px 28px rgba(23, 59, 114, 0.22);
            flex: 0 0 auto;
        }

        .brand-copy strong {
            display: block;
            color: var(--accent-strong);
            font-size: 1.02rem;
        }

        .brand-copy span {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.28em;
            font-size: 0.72rem;
            color: var(--accent-2);
            margin-bottom: 0.8rem;
            font-weight: 700;
        }

        .auth-card h1 {
            margin-bottom: 0.4rem;
            font-size: clamp(2rem, 4vw, 2.55rem);
        }

        .auth-card .muted {
            margin-bottom: 1.4rem;
            line-height: 1.65;
        }

        .auth-note {
            display: flex;
            gap: 0.8rem;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            padding: 0.9rem 1rem;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(23, 59, 114, 0.06), rgba(17, 124, 107, 0.07));
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .auth-note-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            flex: 0 0 auto;
        }

        .auth-note-text {
            color: var(--muted);
            line-height: 1.55;
            font-size: 0.95rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.1rem;
        }

        .form-field.full {
            grid-column: 1 / -1;
        }

        .form-field label {
            display: block;
            margin-bottom: 0.45rem;
            color: var(--accent-strong);
            font-weight: 700;
        }

        .form-field select,
        .form-field input,
        .form-field textarea {
            width: 100%;
        }

        .field-hint {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.45;
        }

        .password-field {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-field input {
            padding-right: 3rem;
        }

        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .toggle-password:hover {
            color: var(--accent);
            transform: translateY(-50%) scale(1.08);
        }

        .toggle-password:focus {
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 112px;
        }

        .btn-block {
            width: 100%;
        }

        .helper {
            margin-top: 1.15rem;
            text-align: center;
            color: var(--muted);
            font-weight: 600;
        }

        .text-link {
            color: var(--accent);
        }

        .password-match {
            color: #b91c1c;
            display: none;
            margin-top: 0.45rem;
        }

        @media (max-width: 900px) {
            .auth-card {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .auth-card {
                padding: 1.25rem;
                border-radius: 18px;
            }

            .brand-row {
                align-items: flex-start;
            }
        }

        /* Modal removed along with College/Course fields */
    </style>
</head>
<body>
    <main class="container">
        <section class="auth-shell">
            <div class="auth-card card">
                <div class="brand-row">
                    <span class="brand-badge"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
                    <div class="brand-copy">
                        <strong>BISU Voting System</strong>
                        <span>Student Registration</span>
                    </div>
                </div>

                <p class="eyebrow">Create account</p>
                <h1>Create your voting account</h1>
                <p class="muted">Use your BISU email to register for the election portal.</p>

                <div class="auth-note">
                    <span class="auth-note-icon"><i class="fas fa-shield-alt" aria-hidden="true"></i></span>
                    <div class="auth-note-text">Complete this form once to access the ballot. Keep your details accurate so your account can be verified quickly.</div>
                </div>

                <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
                <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

                <form method="POST" class="form-grid" id="register-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="form-field">
                        <label>First Name</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-field">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-field full">
                        <label>Complete Address</label>
                        <textarea name="address" required></textarea>
                    </div>
                    <div class="form-field">
                        <label>College</label>
                        <select name="college" required>
                            <option value="" disabled selected>Select your college</option>
                            <option value="College of Science">College of Science</option>
                            <option value="College of Business Management">College of Business Management</option>
                            <option value="College of Fisheries and Marine Sciences">College of Fisheries and Marine Sciences</option>
                            <option value="College of Teachers Education">College of Teachers Education</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Course</label>
                        <select name="course" id="course-select" required disabled>
                            <option value="" disabled selected>Select your college first</option>
                        </select>
                        <small class="field-hint">Choose a course after selecting your college.</small>
                    </div>
                    <div class="form-field">
                        <label>Email (@bisu.edu.ph)</label>
                        <input type="email" name="email" required placeholder="example@bisu.edu.ph">
                    </div>
                    
                    <div class="form-field full">
                        <label>Password</label>
                        <div class="password-field">
                            <input type="password" id="register-password" name="password" required>
                            <button type="button" class="toggle-password" data-target="register-password" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-field full">
                        <label>Confirm Password</label>
                        <div class="password-field">
                            <input type="password" id="confirm-password" name="confirm_password" required>
                            <button type="button" class="toggle-password" data-target="confirm-password" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small id="password-match" class="password-match">Passwords do not match</small>
                    </div>
                    <div class="form-field full">
                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                    </div>
                </form>

                <div class="helper">
                    Already registered? <a class="text-link" href="login.php">Login here</a>
                </div>
            </div>
        </section>
            <!-- College & Course UI removed -->
    </main>

    <script>
        const collegeCourses = <?php echo json_encode($college_courses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const collegeSelect = document.querySelector('select[name="college"]');
        const courseSelect = document.getElementById('course-select');
        const passwordInput = document.getElementById('register-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const passwordMatchMessage = document.getElementById('password-match');

        function setCourseOptions(college) {
            if (!courseSelect) {
                return;
            }

            courseSelect.innerHTML = '';

            if (!college || !collegeCourses[college]) {
                courseSelect.disabled = true;
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.disabled = true;
                placeholder.selected = true;
                placeholder.textContent = 'Select your college first';
                courseSelect.appendChild(placeholder);
                return;
            }

            courseSelect.disabled = false;

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.textContent = 'Select your course';
            courseSelect.appendChild(placeholder);

            collegeCourses[college].forEach((course) => {
                const option = document.createElement('option');
                option.value = course;
                option.textContent = course;
                courseSelect.appendChild(option);
            });
        }

        if (collegeSelect && courseSelect) {
            collegeSelect.addEventListener('change', () => {
                setCourseOptions(collegeSelect.value);
            });

            setCourseOptions(collegeSelect.value);
        }

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
                if (icon) {
                    icon.classList.toggle('fa-eye', isText);
                    icon.classList.toggle('fa-eye-slash', !isText);
                }

                button.setAttribute('aria-pressed', String(!isText));
                button.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
            });
        });

        function checkPasswordMatch() {
            if (!confirmPasswordInput.value) {
                passwordMatchMessage.style.display = 'none';
                return true;
            }

            const matches = passwordInput.value === confirmPasswordInput.value;
            passwordMatchMessage.style.display = matches ? 'none' : 'block';
            return matches;
        }

        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        document.getElementById('register-form').addEventListener('submit', (e) => {
            if (!checkPasswordMatch()) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>