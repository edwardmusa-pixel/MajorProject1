<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Clear any existing session data to prevent contamination
if(isset($_SESSION['user'])) {
    // Don't destroy completely, just clear if invalid
    if(!isset($_SESSION['user']['role'])) {
        session_destroy();
        session_start();
    }
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if(empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit;
}

try {
    $db = getDB();
    
    // Find user by email only - auto detect role (no role selection needed!)
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Check if user exists and password matches
    if ($user && password_verify($password, $user['password'])) {
        // Check if user is active
        if(isset($user['is_active']) && $user['is_active'] == 0) {
            echo json_encode(['success' => false, 'error' => 'Your account is deactivated. Please contact admin.']);
            exit;
        }
        
        // Set session data
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        
        // Update last login time if column exists
        try {
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        } catch(Exception $e) {
            // Column might not exist, ignore
        }
        
        // Return success with redirect URL
        echo json_encode([
            'success' => true, 
            'redirect' => $user['role'] . '/dashboard.php',
            'role' => $user['role'],
            'name' => $user['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>