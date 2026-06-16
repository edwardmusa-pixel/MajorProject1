<?php
session_start();
if(!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$host = 'localhost';
$dbname = 'attendpro_db';
$username = 'root';
$password = '';

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if($newPassword !== $confirmPassword) {
        $error = '<div class="error">❌ New passwords do not match!</div>';
    } elseif(strlen($newPassword) < 6) {
        $error = '<div class="error">❌ Password must be at least 6 characters!</div>';
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $user = $stmt->fetch();
            
            if(password_verify($currentPassword, $user['password'])) {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHash, $_SESSION['user']['id']]);
                
                $message = '<div class="success">✅ Password changed successfully! Please login again.</div>';
                
                // Logout after password change
                session_destroy();
                echo '<meta http-equiv="refresh" content="3;url=index.php">';
            } else {
                $error = '<div class="error">❌ Current password is incorrect!</div>';
            }
        } catch(PDOException $e) {
            $error = '<div class="error">❌ Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

$userRole = $_SESSION['user']['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        
        .password-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            padding: 2rem;
            border-radius: 24px;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 { text-align: center; color: #059669; margin-bottom: 1rem; }
        .input-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 12px; font-size: 1rem; }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; border-radius: 12px; cursor: pointer; font-size: 1rem; font-weight: 600; margin-top: 1rem; }
        .back-btn { background: #6B7280; margin-top: 0.5rem; text-align: center; display: block; text-decoration: none; }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1rem; text-align: center; }
        .error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 12px; margin-bottom: 1rem; text-align: center; }
        .info { text-align: center; color: #6B7280; margin-bottom: 1rem; font-size: 14px; }
    </style>
</head>
<body>
    <div class="password-container">
        <h1>🔐 Change Password</h1>
        <div class="info">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong> (<?php echo ucfirst($userRole); ?>)</div>
        
        <?php echo $message; ?>
        <?php echo $error; ?>
        
        <form method="POST">
            <div class="input-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="6">
            </div>
            <div class="input-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">Change Password</button>
        </form>
        <a href="<?php echo $userRole; ?>/dashboard.php" class="btn back-btn">← Back to Dashboard</a>
    </div>
</body>
</html>