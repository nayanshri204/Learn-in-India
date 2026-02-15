<?php
// Script to sync database user data to users.json file
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';
$usersFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.json';

// Load existing JSON file
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?: [];
}

// Connect to database and get all users
$db_users = [];
if (!empty($db_name)) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $result = $mysqli->query('SELECT name, email, gender, course FROM users');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $db_users[$row['email']] = $row;
            }
        }
        $mysqli->close();
    }
}

// Update JSON users with database data
$updated = false;
foreach ($users as &$user) {
    if (isset($user['email']) && isset($db_users[$user['email']])) {
        $db_data = $db_users[$user['email']];
        // Update fields from database
        $user['name'] = $db_data['name'] ?? null;
        $user['gender'] = $db_data['gender'] ?? null;
        $user['course'] = $db_data['course'] ?? null;
        if (!isset($user['created_at'])) {
            $user['created_at'] = date('d-m-y');
        }
        $updated = true;
    }
}

// Add any database users that don't exist in JSON
foreach ($db_users as $email => $db_data) {
    $exists = false;
    foreach ($users as $user) {
        if (isset($user['email']) && strcasecmp($user['email'], $email) === 0) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $users[] = [
            'name' => $db_data['name'] ?? null,
            'email' => $email,
            'gender' => $db_data['gender'] ?? null,
            'course' => $db_data['course'] ?? null,
            'projects' => [],
            'created_at' => date('d-m-y')
        ];
        $updated = true;
    }
}

// Save updated JSON file
if ($updated) {
    $dataDir = dirname($usersFile);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo "Successfully updated users.json with database data!\n";
    echo "Updated " . count($users) . " users.\n";
} else {
    echo "No updates needed. JSON file is already in sync.\n";
}

