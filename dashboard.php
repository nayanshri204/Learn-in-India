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
$usersFile = __DIR__ . '/data/users.json';

// Helper functions
function loadUsers() {
    global $usersFile;
    return file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) ?: [] : [];
}

function saveUsers($users) {
    global $usersFile;
    $dataDir = dirname($usersFile);
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getUserIndex($users, $email) {
    foreach ($users as $i => $u) {
        if (isset($u['email']) && strcasecmp($u['email'], $email) === 0) return $i;
    }
    return null;
}

// Load user data
$me = null;
$users = loadUsers();
$userIndex = getUserIndex($users, $_SESSION['intern_email']);

// Try database first
if (!empty($db_name)) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $stmt = $mysqli->prepare('SELECT name, email, gender, course FROM users WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('s', $_SESSION['intern_email']);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $me = $row;
                $me['projects'] = [];
            }
            $stmt->close();
        }
        $mysqli->close();
    }
}

// Fallback to file-based or load projects for DB user
if (!$me && $userIndex !== null) {
    $me = $users[$userIndex];
} elseif ($me && $userIndex !== null) {
    $me['projects'] = $users[$userIndex]['projects'] ?? [];
} elseif ($me) {
    $users[] = ['email' => $_SESSION['intern_email'], 'projects' => []];
    $userIndex = count($users) - 1;
    saveUsers($users);
}

if (!$me) {
    echo '<div class="card"><h1>User not found</h1><p>Your account could not be located.</p></div>';
    require 'footer.php';
    exit;
}

$me['projects'] = $me['projects'] ?? [];

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$errors = [];

// Add project
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $errors[] = 'Enter a project title.';
        } else {
            $users = loadUsers();
            $userIndex = getUserIndex($users, $_SESSION['intern_email']);
            if ($userIndex === null) {
                $users[] = ['email' => $_SESSION['intern_email'], 'projects' => []];
                $userIndex = count($users) - 1;
            }
            $users[$userIndex]['projects'][] = [
                'id' => uniqid('p_', true),
                'title' => $title,
                'description' => trim($_POST['description'] ?? ''),
                'completed' => false,
                'created_at' => date('c')
            ];
            saveUsers($users);
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Complete project
if (($_GET['action'] ?? '') === 'complete' && !empty($_GET['id'])) {
    $users = loadUsers();
    $userIndex = getUserIndex($users, $_SESSION['intern_email']);
    if ($userIndex !== null) {
        foreach ($users[$userIndex]['projects'] ?? [] as $pi => $p) {
            if ($p['id'] === $_GET['id']) {
                $users[$userIndex]['projects'][$pi]['completed'] = true;
                $users[$userIndex]['projects'][$pi]['completed_at'] = date('c');
                saveUsers($users);
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

// Reload projects after actions
$users = loadUsers();
$userIndex = getUserIndex($users, $_SESSION['intern_email']);
if ($userIndex !== null) {
    $me['projects'] = $users[$userIndex]['projects'] ?? [];
}

?>
<div class="card">
    <h1>Project Dashboard</h1>
    <p>Welcome back, <?php echo htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>

    <h2>Your Projects</h2>
    <?php if (empty($me['projects'])): ?>
        <p>No projects yet. Add one below.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse">
            <thead><tr><th style="text-align:left">Title</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($me['projects'] as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $p['completed'] ? 'Completed' : 'In progress'; ?></td>
                    <td>
                        <?php if (!$p['completed']): ?>
                            <a href="dashboard.php?action=complete&id=<?php echo urlencode($p['id']); ?>">Mark complete</a>
                        <?php else: ?>
                            <a href="certificate.php?project_id=<?php echo urlencode($p['id']); ?>">Download certificate (PDF)</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Add Project</h2>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>'; ?></ul></div><?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <label for="title">Project title</label>
        <input id="title" name="title" type="text" required>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" style="width:100%"></textarea>
        <div class="actions"><button type="submit">Add Project</button></div>
    </form>

    <p><a href="profile.php">View profile</a> — <a href="logout.php">Logout</a></p>
</div>

<?php require 'footer.php'; ?>
