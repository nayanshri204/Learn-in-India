<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';
$usersFile = __DIR__ . '/data/users.json';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$errors = [];
$values = ['name' => '', 'email' => '', 'password' => '', 'gender' => '', 'course' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    }
    
    $values = array_map('trim', array_intersect_key($_POST, $values));
    $values['password'] = $_POST['password'] ?? '';
    
    // Validation
    if (empty($values['name']) || !preg_match('/^[\p{L} .\'-]{2,100}$/u', $values['name'])) {
        $errors[] = 'Please enter a valid full name (2-100 characters).';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (strlen($values['password']) < 6) {
        $errors[] = 'Please provide a password of at least 6 characters.';
    }
    if (!in_array($values['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }
    if (empty($values['course']) || mb_strlen($values['course']) > 40) {
        $errors[] = 'Please enter a course (max 40 characters).';
    }
    
    if (empty($errors)) {
        $hashed = password_hash($values['password'], PASSWORD_DEFAULT);
        $inserted = false;
        
        // Try database
        if (!empty($db_name)) {
            $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
            if (!$mysqli->connect_errno) {
                $stmt = $mysqli->prepare('INSERT INTO users (name, email, password, gender, course) VALUES (?, ?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('sssss', $values['name'], $values['email'], $hashed, $values['gender'], $values['course']);
                    if ($stmt->execute()) $inserted = true;
                    else $errors[] = 'Failed to save registration.';
                    $stmt->close();
                }
                $mysqli->close();
            }
        }
        
        // Always update JSON file (for sync with database or as fallback)
        if ($inserted) {
            // Database insert succeeded, sync to JSON
            $dataDir = dirname($usersFile);
            if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
            $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) ?: [] : [];
            
            // Check if user already exists in JSON
            $userExists = false;
            foreach ($users as &$u) {
                if (isset($u['email']) && strcasecmp($u['email'], $values['email']) === 0) {
                    // Update existing user
                    $u['name'] = $values['name'];
                    $u['gender'] = $values['gender'];
                    $u['course'] = $values['course'];
                    if (!isset($u['created_at'])) {
                        $u['created_at'] = date('c');
                    }
                    $userExists = true;
                    break;
                }
            }
            
            // Add new user if doesn't exist
            if (!$userExists) {
                $users[] = [
                    'name' => $values['name'],
                    'email' => $values['email'],
                    'gender' => $values['gender'],
                    'course' => $values['course'],
                    'password_hash' => $hashed,
                    'projects' => [],
                    'created_at' => date('d-m-y')
                ];
            }
            
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        
        // File-based storage (only if database failed or wasn't configured)
        if (!$inserted && empty($db_name)) {
            $dataDir = dirname($usersFile);
            if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
            $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) ?: [] : [];
            foreach ($users as $u) {
                if (isset($u['email']) && strcasecmp($u['email'], $values['email']) === 0) {
                    $errors[] = 'An account with that email already exists.';
                    break;
                }
            }
            if (empty($errors)) {
                $users[] = [
                    'name' => $values['name'],
                    'email' => $values['email'],
                    'gender' => $values['gender'],
                    'course' => $values['course'],
                    'password_hash' => $hashed,
                    'projects' => [],
                    'created_at' => date('d-m-y')
                ];
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                $inserted = true;
            }
        }
        
        // Login and redirect on success
        if ($inserted && empty($errors)) {
            $_SESSION['intern_email'] = $values['email'];
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Render form (initial load or errors)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Student Registration</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f5f7fa;margin:0;padding:40px}
        .container{max-width:680px;margin:0 auto}
        .card{background:#fff;padding:24px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        label{display:block;margin-top:12px;font-weight:600}
        input[type=text], input[type=email], select{width:100%;padding:10px;margin-top:6px;border:1px solid #cfd8dc;border-radius:4px}
        .actions{margin-top:18px}
        button{background:#0078d4;color:#fff;padding:10px 16px;border:none;border-radius:4px;cursor:pointer}
        .errors{background:#ffecec;border:1px solid #ffb3b3;padding:10px;border-radius:4px;margin-bottom:12px}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Student Registration</h1>
        <?php if ($errors): ?>
            <div class="errors"><strong>Please fix the following:</strong><ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <?php 
            $fields = ['name' => 'text', 'email' => 'email', 'password' => 'password'];
            foreach ($fields as $field => $type): ?>
                <label for="<?= $field ?>"><?= ucfirst($field === 'name' ? 'Full name' : $field) ?></label>
                <input id="<?= $field ?>" name="<?= $field ?>" type="<?= $type ?>" value="<?= htmlspecialchars($values[$field], ENT_QUOTES, 'UTF-8') ?>" required>
            <?php endforeach; ?>

            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                <option value="">-- Select --</option>
                <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                    <option value="<?= $g ?>" <?= $values['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>

            <label for="course">Course</label>
            <input id="course" name="course" type="text" value="<?= htmlspecialchars($values['course'], ENT_QUOTES, 'UTF-8') ?>" required>

            <div class="actions">
                <button type="submit">Register</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
<?php
// end of file



