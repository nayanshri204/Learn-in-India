<?php
require 'header.php';

// Database configuration (should match registration.php)
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
    // if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $errors[] = 'Invalid form submission.';
    // }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($password === '') $errors[] = 'Enter your password.';

    if (empty($errors)) {
        $authenticated = false;
        
        // Try database authentication first if configured
        if (!empty($db_name)) {
            $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
            if (!$mysqli->connect_errno) {
                $stmt = $mysqli->prepare('SELECT email, password FROM users WHERE email = ?');
                if ($stmt) {
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        if (password_verify($password, $row['password'])) {
                            $authenticated = true;
                        }
                    }
                    $stmt->close();
                }
                $mysqli->close();
            }
        }
        
        // If database auth failed or not configured, try file-based authentication
        if (!$authenticated) {
            $usersFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.json';
            $users = [];
            if (file_exists($usersFile)) {
                $users = json_decode(file_get_contents($usersFile), true) ?: [];
            }
            $found = null;
            foreach ($users as $u) {
                if (isset($u['email']) && strcasecmp($u['email'], $email) === 0) {
                    $found = $u; break;
                }
            }
            if ($found && isset($found['password_hash']) && password_verify($password, $found['password_hash'])) {
                $authenticated = true;
            }
        }
        
        if ($authenticated) {
            $_SESSION['intern_email'] = $email;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<div class="card">
    <h1>Intern Login</h1>
    <?php if ($errors): ?>
        <div class="errors"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>'; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <div class="actions"><button type="submit">Login</button></div>
    </form>
</div>

<?php require 'footer.php'; ?>