<?php
require 'header.php';

if (empty($_SESSION['intern_email'])) {
    header('Location: login.php');
    exit;
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';
$usersFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.json';
$me = null;

// Try database first
if (!empty($db_name)) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $stmt = $mysqli->prepare('SELECT name, email, gender, course FROM users WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('s', $_SESSION['intern_email']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $me = $row;
                // Sync database data to JSON file
                $users = [];
                if (file_exists($usersFile)) $users = json_decode(file_get_contents($usersFile), true) ?: [];
                $userFound = false;
                foreach ($users as &$u) {
                    if (isset($u['email']) && strcasecmp($u['email'], $_SESSION['intern_email']) === 0) {
                        $u['name'] = $me['name'];
                        $u['gender'] = $me['gender'];
                        $u['course'] = $me['course'];
                        if (!isset($u['created_at'])) {
                            $u['created_at'] = date('d-m-y');
                        }
                        $userFound = true;
                        break;
                    }
                }
                if (!$userFound) {
                    $users[] = [
                        'name' => $me['name'],
                        'email' => $me['email'],
                        'gender' => $me['gender'],
                        'course' => $me['course'],
                        'projects' => [],
                        'created_at' => date('d-m-y')
                    ];
                }
                $dataDir = dirname($usersFile);
                if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                
                // Load created_at from JSON file (database doesn't support it)
                foreach ($users as $u) {
                    if (isset($u['email']) && strcasecmp($u['email'], $_SESSION['intern_email']) === 0) {
                        $me['created_at'] = $u['created_at'] ?? date('d-m-y');
                        break;
                    }
                }
            }
            $stmt->close();
        }
        $mysqli->close();
    }
}

// Fallback to file-based storage
if (!$me) {
    $users = [];
    if (file_exists($usersFile)) $users = json_decode(file_get_contents($usersFile), true) ?: [];
    foreach ($users as $u) {
        if (isset($u['email']) && strcasecmp($u['email'], $_SESSION['intern_email']) === 0) {
            $me = $u;
            break;
        }
    }
}

if (!$me) {
    echo '<div class="card"><h1>User not found</h1><p>Your account could not be located.</p></div>';
    require 'footer.php';
    exit;
}

?>
<div class="card">
    <h1>Your Profile</h1>
    <ul>
    <li><strong>Name:</strong>
        <?php echo isset($me['name']) ? htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8') : "Not Available"; ?>
    </li>

    <li><strong>Email:</strong>
        <?php echo isset($me['email']) ? htmlspecialchars($me['email'], ENT_QUOTES, 'UTF-8') : "Not Available"; ?>
    </li>

    <li><strong>Gender:</strong>
        <?php echo isset($me['gender']) ? htmlspecialchars($me['gender'], ENT_QUOTES, 'UTF-8') : "Not Available"; ?>
    </li>

    <li><strong>Course:</strong>
        <?php echo isset($me['course']) ? htmlspecialchars($me['course'], ENT_QUOTES, 'UTF-8') : "Not Available"; ?>
    </li>

    <li><strong>Member since:</strong>
        <?php echo isset($me['created_at']) ? htmlspecialchars($me['created_at'], ENT_QUOTES, 'UTF-8') : "Not Available"; ?>
    </li>
</ul>

    <p><a href="dashboard.php">Go to dashboard</a> — <a href="logout.php">Logout</a></p>
</div>

<?php require 'footer.php'; ?>
