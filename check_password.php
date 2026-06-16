<?php
$host = 'localhost';
$dbname = 'attendpro_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Get the admin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@attendpro.com']);
    $user = $stmt->fetch();
    
    echo "<h1>Password Test</h1>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    $testPassword = 'password123';
    $storedHash = $user['password'];
    
    echo "<p><strong>Testing password: '{$testPassword}'</strong></p>";
    echo "<p>Stored hash: {$storedHash}</p>";
    
    if(password_verify($testPassword, $storedHash)) {
        echo "<p style='color:green;font-size:20px;'>✅✅✅ PASSWORD MATCHES! ✅✅✅</p>";
    } else {
        echo "<p style='color:red;font-size:20px;'>❌ PASSWORD DOES NOT MATCH</p>";
        
        // Try to fix it
        $newHash = password_hash('password123', PASSWORD_DEFAULT);
        echo "<p>New hash generated: {$newHash}</p>";
        
        // Update the database
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->execute([$newHash, 'admin@attendpro.com']);
        echo "<p>✅ Database updated with new hash! Try logging in now.</p>";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>