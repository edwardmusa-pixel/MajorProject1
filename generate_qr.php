<?php
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'lecturer') {
    header('Location: ../index.php');
    exit;
}

$host = 'localhost';
$dbname = 'attendpro_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get lecturer ID
$stmt = $pdo->prepare("SELECT id FROM lecturers WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$lecturer = $stmt->fetch();
$lecturerId = $lecturer['id'];

// Get lecturer's classes
$classes = $pdo->prepare("SELECT * FROM classes WHERE lecturer_id = ?");
$classes->execute([$lecturerId]);
$myClasses = $classes->fetchAll();

$message = '';
$qrCodeUrl = '';
$generatedClass = '';
$sessionTime = '';
$generatedQrData = '';
$studentsNotified = 0;
$qrDbId = null;
$enrolledStudents = [];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $classId = $_POST['class_id'];
    $sessionTime = $_POST['session_time'] ?: date('Y-m-d H:i:s');
    $pushToStudents = isset($_POST['push_to_students']) ? true : false;
    
    // Get class details
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    $generatedClass = $class;
    
    // Get enrolled students for this class
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, u.id as user_id, u.email, u.name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        JOIN student_enrollments e ON s.id = e.student_id 
        WHERE e.class_id = ?
    ");
    $stmt->execute([$classId]);
    $enrolledStudents = $stmt->fetchAll();
    
    // Create unique QR session ID
    $qrSessionId = 'QR_' . uniqid() . '_' . date('Ymd_His');
    $qrToken = bin2hex(random_bytes(32));
    
    // Create QR data with session ID
    $generatedQrData = json_encode([
        'qr_session_id' => $qrSessionId,
        'qr_token' => $qrToken,
        'class_id' => $classId,
        'class_code' => $class['class_code'],
        'class_name' => $class['class_name'],
        'class_latitude' => $class['latitude'],
        'class_longitude' => $class['longitude'],
        'class_end_time' => $class['end_time'],
        'lecturer_id' => $lecturerId,
        'lecturer_name' => $_SESSION['user']['name'],
        'session_time' => $sessionTime,
        'room' => $class['room'],
        'building' => $class['building'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Generate QR code image URL using QuickChart API
    $qrCodeUrl = "https://quickchart.io/qr?text=" . urlencode($generatedQrData) . "&size=300&dark=059669&light=ffffff";
    
    // CRITICAL: Deactivate any old QR session keys for this class before generating the new broadcast
    $deactivate = $pdo->prepare("UPDATE qr_sessions SET is_active = 0 WHERE class_id = ?");
    $deactivate->execute([$classId]);

    // Save active QR session to database
    $stmt = $pdo->prepare("INSERT INTO qr_sessions (class_id, session_time, qr_code, qr_token, created_by, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$classId, $sessionTime, $generatedQrData, $qrToken, $lecturerId]);
    $qrDbId = $pdo->lastInsertId();
    
    // Push QR code notifications to enrolled student matrix pools
    if($pushToStudents && count($enrolledStudents) > 0) {
        foreach($enrolledStudents as $student) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, subject, message, related_id, related_type, status, created_at) 
                VALUES (?, 'qr_code', ?, ?, ?, 'qr_session', 'unread', NOW())
            ");
            $subject = "📱 New Attendance QR Code - {$class['class_code']}";
            $message_body = "Your lecturer {$_SESSION['user']['name']} has published a QR code for {$class['class_code']} - {$class['class_name']} on " . date('F j, Y g:i A', strtotime($sessionTime)) . ".\n\n📍 Location: {$class['room']}, {$class['building']}\n\nPlease scan your face to mark attendance.";
            $stmt->execute([$student['user_id'], $subject, $message_body, $qrDbId]);
            $studentsNotified++;
        }
        
        $message = '<div class="success">✅ QR Code live broadcast completed and pushed to <strong>' . $studentsNotified . '</strong> students dashboard queues!</div>';
    } elseif($pushToStudents && count($enrolledStudents) == 0) {
        $message = '<div class="warning">⚠️ QR Code generated but no students are enrolled in this class!</div>';
    } else {
        $message = '<div class="success">✅ QR Code generated successfully!<br>⚠️ Not pushed to students. Use "Push to Students" button below.</div>';
    }
}

// Handle Share to Students (Manual push broadcast engine switch)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_to_students'])) {
    $qrSessionId = $_POST['qr_session_id'];
    $classId = $_POST['class_id'];
    $sessionTime = $_POST['session_time'];
    
    // Reactivate this session globally in case it was toggled down
    $deactivate = $pdo->prepare("UPDATE qr_sessions SET is_active = 0 WHERE class_id = ?");
    $deactivate->execute([$classId]);
    $activate = $pdo->prepare("UPDATE qr_sessions SET is_active = 1 WHERE id = ?");
    $activate->execute([$qrSessionId]);

    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, u.id as user_id, u.email, u.name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        JOIN student_enrollments e ON s.id = e.student_id 
        WHERE e.class_id = ?
    ");
    $stmt->execute([$classId]);
    $enrolledStudents = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    
    $notified = 0;
    foreach($enrolledStudents as $student) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, subject, message, related_id, related_type, status, created_at) 
            VALUES (?, 'qr_code', ?, ?, ?, 'qr_session', 'unread', NOW())
        ");
        $subject = "📱 New Attendance QR Code - {$class['class_code']}";
        $message_body = "Your lecturer {$_SESSION['user']['name']} has published a QR code for {$class['class_code']} - {$class['class_name']} on " . date('F j, Y g:i A', strtotime($sessionTime)) . ".\n\n📍 Location: {$class['room']}, {$class['building']}\n\nPlease scan your face to mark attendance.";
        $stmt->execute([$student['user_id'], $subject, $message_body, $qrSessionId]);
        $notified++;
    }
    
    $message = '<div class="success">✅ QR Code broadcast actively redistributed to <strong>' . $notified . '</strong> student profiles!</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate QR Code | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%); min-height: 100vh; }
        
        .animated-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: hidden; }
        .gradient-bg { position: absolute; width: 200%; height: 200%; top: -50%; left: -50%; background: radial-gradient(circle at 30% 40%, rgba(5,150,105,0.15) 0%, rgba(16,185,129,0.05) 50%, transparent 100%); animation: rotateGradient 20s ease infinite; }
        @keyframes rotateGradient { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .moving-circles { position: absolute; width: 100%; height: 100%; }
        .circle { position: absolute; border-radius: 50%; background: rgba(5,150,105,0.08); animation: floatCircle linear infinite; }
        .circle-1 { width: 300px; height: 300px; top: 10%; left: -100px; animation-duration: 25s; }
        .circle-2 { width: 500px; height: 500px; bottom: -200px; right: -150px; animation-duration: 35s; }
        .circle-3 { width: 200px; height: 200px; top: 50%; right: 20%; animation-duration: 20s; }
        @keyframes floatCircle { 0% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(50px,30px) rotate(180deg); } 100% { transform: translate(0,0) rotate(360deg); } }
        .floating-shapes { position: absolute; width: 100%; height: 100%; pointer-events: none; }
        .shape { position: absolute; font-size: 2rem; opacity: 0.2; animation: floatShape 15s ease-in-out infinite; }
        .shape-1 { top: 15%; left: 10%; } .shape-2 { top: 70%; right: 15%; } .shape-3 { bottom: 20%; left: 20%; }
        @keyframes floatShape { 0%,100% { transform: translateY(0) rotate(0deg); opacity: 0.15; } 50% { transform: translateY(-30px) rotate(10deg); opacity: 0.3; } }
        .particles { position: absolute; width: 100%; height: 100%; background-image: radial-gradient(circle at 20% 40%, rgba(5,150,105,0.15) 1px, transparent 1px); background-size: 50px 50px; animation: moveParticles 30s linear infinite; }
        @keyframes moveParticles { 0% { background-position: 0 0; } 100% { background-position: 100px 100px; } }
        
        .dashboard { display: flex; position: relative; z-index: 1; }
        .sidebar { width: 280px; background: rgba(31,41,55,0.9); backdrop-filter: blur(12px); color: white; position: fixed; height: 100vh; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .logo-highlight { color: #10B981; }
        .role-badge { background: #3B82F6; display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-top: 8px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); position: absolute; bottom: 0; width: 100%; }
        .user-info { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 1rem; }
        .user-avatar { width: 40px; height: 40px; background: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .logout-btn { display: block; background: #EF4444; text-align: center; padding: 10px; border-radius: 12px; text-decoration: none; color: white; }
        .main-content { margin-left: 280px; flex: 1; padding: 1.5rem; }
        .top-bar { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1rem 1.5rem; border-radius: 20px; margin-bottom: 2rem; display: flex; justify-content: space-between; }
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.3); }
        .form-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 1rem; margin-top: 1rem; }
        select, input { width: 100%; padding: 12px; border: 1px solid rgba(5,150,105,0.2); border-radius: 12px; background: rgba(255,255,255,0.9); font-size: 1rem; }
        .btn { background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; border: none; padding: 12px 24px; border-radius: 40px; cursor: pointer; margin-top: 1rem; font-weight: 600; transition: all 0.3s; }
        .btn-green { background: linear-gradient(135deg, #059669, #10B981); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .qr-container { text-align: center; padding: 20px; background: white; border-radius: 16px; margin-top: 1rem; }
        .qr-container img { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .warning { background: #FEF3C7; color: #92400E; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .info { background: #E0E7FF; color: #1E40AF; padding: 12px; border-radius: 12px; margin-bottom: 1rem; font-size: 14px; }
        .share-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 15px; flex-wrap: wrap; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .main-content { margin-left: 70px; } .sidebar-nav a span:last-child { display: none; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="gradient-bg"></div>
        <div class="moving-circles">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
        </div>
        <div class="floating-shapes">
            <div class="shape shape-1">🎓</div>
            <div class="shape shape-2">📍</div>
            <div class="shape shape-3">😀</div>
        </div>
        <div class="particles"></div>
    </div>

    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header"><div class="logo">🎓 Attend<span class="logo-highlight">Pro</span></div><div class="role-badge">LECTURER</div></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="generate_qr.php" class="active">🎫 Generate QR</a>
                <a href="verify-pending.php">✅ Verify Pending</a>
                <a href="reports.php">📈 Reports</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div><div><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br><small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small></div></div>
                <a href="../logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </aside>
        <main class="main-content">
            <div class="top-bar"><h1>🎫 Generate & Push QR Code</h1><div id="clock"></div></div>
            
            <?php echo $message; ?>
            
            <div class="info">
                📌 Generate a QR code for your class session. Students will receive an instant broadcast push notification on their dashboards immediately!
                <br><br>
                <strong>👥 Enrolled Target Matrix:</strong> 
                <?php 
                    foreach($myClasses as $class) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_enrollments WHERE class_id = ?");
                        $stmt->execute([$class['id']]);
                        $count = $stmt->fetchColumn();
                        echo "{$class['class_code']}: <strong>{$count}</strong> students ";
                    }
                ?>
            </div>
            
            <div class="card">
                <h3>Create Attendance Session</h3>
                <form method="POST">
                    <div class="form-grid">
                        <select name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach($myClasses as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo $class['class_code']; ?> - <?php echo $class['class_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="datetime-local" name="session_time" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div style="margin: 1rem 0;">
                        <label style="cursor: pointer;">
                            <input type="checkbox" name="push_to_students" value="1" checked style="margin-right: 8px;">
                            📱 Automatically push QR code to all enrolled students instantly
                        </label>
                    </div>
                    <button type="submit" name="generate" class="btn">🎫 Generate & Push QR Code</button>
                </form>
            </div>
            
            <?php if($qrCodeUrl && $generatedClass): ?>
            <div class="card">
                <h3>📱 QR Code for Students</h3>
                <div class="qr-container">
                    <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" id="qrImage" style="border-radius: 16px; width: 250px; height: 250px;">
                    <div class="info" style="margin-top: 15px; text-align: left;">
                        <strong>📋 Session Information:</strong><br>
                        📚 Class: <?php echo $generatedClass['class_code']; ?> - <?php echo $generatedClass['class_name']; ?><br>
                        📅 Date: <?php echo date('F j, Y', strtotime($sessionTime)); ?><br>
                        ⏰ Time: <?php echo date('g:i A', strtotime($sessionTime)); ?><br>
                        📍 Room: <?php echo $generatedClass['room']; ?>, <?php echo $generatedClass['building']; ?>
                    </div>
                    
                    <div class="share-buttons">
                        <button onclick="downloadQR()" class="btn">📥 Download QR Code</button>
                        <?php if($qrDbId): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="qr_session_id" value="<?php echo $qrDbId; ?>">
                            <input type="hidden" name="class_id" value="<?php echo $generatedClass['id']; ?>">
                            <input type="hidden" name="session_time" value="<?php echo $sessionTime; ?>">
                            <button type="submit" name="share_to_students" class="btn btn-green">📧 Re-Broadcast Push Notification</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <script>
                function downloadQR() {
                    const qrImage = document.getElementById('qrImage');
                    const link = document.createElement('a');
                    link.download = 'attendance_qr_<?php echo $generatedClass['class_code']; ?>.png';
                    link.href = qrImage.src;
                    link.click();
                }
            </script>
            <?php endif; ?>
        </aside>
    </div>
    <script>
        function updateClock() { 
            const now = new Date();
            document.getElementById('clock').innerHTML = now.toLocaleString('en-US', {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }
        setInterval(updateClock, 1000); updateClock();
    </script>
</body>
</html>