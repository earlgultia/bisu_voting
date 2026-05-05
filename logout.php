<?php include 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Logging out...</title>
        </head>
        <body>
            <script>
                window.location.replace('login.php');
            </script>
        </body>
        </html>
        <?php
        exit();
    }

    $error = 'Your session expired. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - BISU Voting System</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .logout-form {
            display: none;
        }
    </style>
</head>
<body>
    <?php if (isset($error)) : ?>
        <script>
            Swal.fire({
                title: 'Logout failed',
                text: <?php echo json_encode($error); ?>,
                icon: 'error',
                confirmButtonColor: '#111111'
            }).then(() => {
                window.history.back();
            });
        </script>
    <?php endif; ?>

    <form id="logout-form" class="logout-form" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
    </form>

    <script>
        Swal.fire({
            title: 'Logout Confirmation',
            text: 'Are you sure you want to logout?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#111111',
            cancelButtonColor: '#d32f2f',
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'No, stay',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('logout-form').submit();
            } else {
                window.history.back();
            }
        });
    </script>
</body>
</html>