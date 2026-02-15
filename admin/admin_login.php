<?php
require __DIR__ . '/../includes/header.php';

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email)) {
            $errors[] = 'Enter your email.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (empty($password)) {
            $errors[] = 'Enter your password.';
        }
        
        if (empty($errors)) {
            $authenticated = false;
            
            // Try database authentication
            if (!empty($db_name)) {
                $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$mysqli->connect_errno) {
                    $stmt = $mysqli->prepare('SELECT id, email, password FROM admins WHERE email = ?');
                    if ($stmt) {
                        $stmt->bind_param('s', $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            if (password_verify($password, $row['password'])) {
                                $_SESSION['admin_id'] = $row['id'];
                                $_SESSION['admin_email'] = $row['email'];
                                $authenticated = true;
                            }
                        }
                        $stmt->close();
                    }
                    $mysqli->close();
                }
            }
            
            if ($authenticated) {
                unset($_SESSION['csrf_token']);
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
    }
}

// Redirect if already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}
?>
<div class="card">
    <h1>Admin Login</h1>
    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
        
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        
        <div class="actions">
            <button type="submit">Login</button>
        </div>
    </form>
    <p style="margin-top: 20px; text-align: center;">
        <a href="/Learn-in-India/index.php">Back to Home</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

