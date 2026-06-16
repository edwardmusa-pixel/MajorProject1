<?php
echo "<h1>🔍 SYSTEM DIAGNOSTIC</h1>";

// 1. Check if files exist
echo "<h2>📁 File Check:</h2>";
$files = ['config/database.php', 'api/login.php', 'index.php'];
foreach($files as $file) {
    echo file_exists($file) ? "✅ $file exists<br>" : "❌ $file MISSING<br>";
}

// 2. Test Database Connection
echo "<h2>🗄️ Database Check:</h2>";
$host = 'localhost';
$dbname = 'attendpro_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully!<br>";
    
    // Check users table
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "✅ Users table has $count records<br>";
    
    // Show all users
    $users = $pdo->query("SELECT id, email, name, role FROM users")->fetchAll();
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th></tr>";
    foreach($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['email']}</td><td>{$u['name']}</td><td>{$u['role']}</td></tr>";
    }
    echo "</table>";
    
} catch(PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

// 3. Check Session
echo "<h2>🔐 Session Check:</h2>";
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>";
print_r($_SESSION);
echo "</pre>";

// 4. Test password hashing
echo "<h2>🔑 Password Test:</h2>";
$testPassword = 'password123';
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "Testing password '$testPassword' against hash...<br>";
echo password_verify($testPassword, $hash) ? "✅ Password VERIFIED!<br>" : "❌ Password FAILED!<br>";

// 5. Test admin login directly
echo "<h2>👑 Direct Admin Login Test:</h2>";
if(isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute(['admin@attendpro.com', 'admin']);
    $admin = $stmt->fetch();
    if($admin) {
        echo "✅ Admin found in database<br>";
        $verify = password_verify('password123', $admin['password']);
        echo $verify ? "✅ Password matches!<br>" : "❌ Password does NOT match!<br>";
    } else {
        echo "❌ Admin NOT found! Run this SQL:<br>";
        echo "<code>INSERT INTO users (email, password, name, role) VALUES ('admin@attendpro.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'admin');</code>";
    }
}
?>