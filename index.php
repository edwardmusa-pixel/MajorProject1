<?php 
session_start();

// Database connection
$host = 'localhost';
$dbname = 'attendpro_db';
$username = 'root';
$password = '';

$error = '';

// Handle login
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $email = trim($_POST['email'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        
        // Find user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if($user && password_verify($pass, $user['password'])) {
            // No email verification check - direct login
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            header("Location: {$user['role']}/dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// FIX: Destroy any existing session so hitting index.php always forces a fresh login screen view
if(isset($_SESSION['user'])) {
    $_SESSION = []; // Clear current script memory references
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy(); // Wipe the session file tracking asset inside the server temp directory
    
    // Perform a clean redirect to completely display the login wrapper
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AttendPro | Smart Attendance System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%);
        }
        
        /* ========== ANIMATED BACKGROUND ========== */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        .gradient-bg {
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background: radial-gradient(circle at 30% 40%, rgba(5, 150, 105, 0.15) 0%, rgba(16, 185, 129, 0.05) 50%, transparent 100%);
            animation: rotateGradient 20s ease infinite;
        }
        
        @keyframes rotateGradient {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .moving-circles {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        
        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(5, 150, 105, 0.08);
            animation: floatCircle linear infinite;
        }
        
        .circle-1 { width: 300px; height: 300px; top: 10%; left: -100px; animation-duration: 25s; }
        .circle-2 { width: 500px; height: 500px; bottom: -200px; right: -150px; animation-duration: 35s; animation-direction: reverse; }
        .circle-3 { width: 200px; height: 200px; top: 50%; right: 20%; animation-duration: 20s; }
        .circle-4 { width: 150px; height: 150px; bottom: 30%; left: 15%; animation-duration: 18s; }
        .circle-5 { width: 400px; height: 400px; top: 60%; left: -150px; animation-duration: 30s; }
        
        @keyframes floatCircle {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(50px, 30px) rotate(180deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            font-size: 2rem;
            opacity: 0.2;
            animation: floatShape 15s ease-in-out infinite;
        }
        
        .shape-1 { top: 15%; left: 10%; animation-delay: 0s; }
        .shape-2 { top: 70%; right: 15%; animation-delay: 2s; animation-duration: 18s; }
        .shape-3 { bottom: 20%; left: 20%; animation-delay: 4s; animation-duration: 20s; }
        .shape-4 { top: 40%; right: 25%; animation-delay: 1s; animation-duration: 22s; }
        .shape-5 { bottom: 50%; left: 35%; animation-delay: 3s; animation-duration: 16s; }
        
        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.15; }
            50% { transform: translateY(-30px) rotate(10deg); opacity: 0.3; }
        }
        
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle at 20% 40%, rgba(5, 150, 105, 0.15) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveParticles 30s linear infinite;
        }
        
        @keyframes moveParticles {
            0% { background-position: 0 0; }
            100% { background-position: 100px 100px; }
        }
        
        /* ========== LOGIN CARD ========== */
        .login-card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 32px;
            padding: 2.5rem;
            width: 450px;
            max-width: 92%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            font-size: 3.5rem;
            margin-bottom: 0.5rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        h1 {
            text-align: center;
            color: #059669;
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
        }
        
        .subtitle {
            text-align: center;
            color: #6B7280;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        .smart-badge {
            background: rgba(5, 150, 105, 0.1);
            padding: 8px;
            border-radius: 40px;
            text-align: center;
            font-size: 0.8rem;
            color: #059669;
            margin-bottom: 1.5rem;
        }
        
        .input-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        input {
            width: 100%;
            padding: 14px;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.9);
        }
        
        input:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3);
        }
        
        .error {
            background: rgba(254, 226, 226, 0.95);
            color: #991B1B;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
            border-left: 4px solid #EF4444;
        }
        
        .demo {
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(243, 244, 246, 0.7);
            border-radius: 16px;
            font-size: 0.8rem;
            text-align: center;
        }
        
        .demo strong {
            color: #059669;
            display: block;
            margin-bottom: 8px;
        }
        
        .demo-row {
            margin: 5px 0;
            font-family: monospace;
            padding: 6px 10px;
            background: white;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .demo-row span {
            color: #059669;
            font-weight: bold;
        }
        
        .demo-row code {
            background: #f3f4f6;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .demo-note {
            font-size: 0.7rem;
            color: #6B7280;
            margin-top: 8px;
        }
        
        .hide-credentials {
            display: none;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
            .logo {
                font-size: 2.5rem;
            }
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="gradient-bg"></div>
        <div class="moving-circles">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
            <div class="circle circle-4"></div>
            <div class="circle circle-5"></div>
        </div>
        <div class="floating-shapes">
            <div class="shape shape-1">🎓</div>
            <div class="shape shape-2">📍</div>
            <div class="shape shape-3">😀</div>
            <div class="shape shape-4">✅</div>
            <div class="shape shape-5">📊</div>
        </div>
        <div class="particles"></div>
    </div>

    <div class="login-card">
        <div class="logo">🎓</div>
        <h1>AttendPro</h1>
        <div class="subtitle">Smart Attendance Management System</div>
        <div class="smart-badge">🤖 Smart Login - Auto detects your role</div>
        
        <?php if($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-group">
                <label>📧 Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="input-group">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit">Login →</button>
        </form>
        
        <div class="demo">
             Simpson <strong>📋 Demo Credentials</strong>
            <div class="demo-row">
                <span>👨‍🎓 Student:</span>
                <code>alice.johnson@student.edu</code>
                <code>password123</code>
            </div>
            <div class="demo-note">
                💡 Contact your administrator for Admin or Lecturer access
            </div>
        </div>
    </div>
</body>
</html>