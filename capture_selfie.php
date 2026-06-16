<?php
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
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
    die("Database connection failed");
}

$stmt = $pdo->prepare("SELECT id, student_id, face_image_path FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$student = $stmt->fetch();

$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selfie'])) {
    $imageData = $_POST['selfie_image'];
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $imageBinary = base64_decode($imageData);
    
    $uploadDir = '../uploads/faces/';
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $fileName = 'student_' . $student['student_id'] . '_' . time() . '.png';
    $filePath = $uploadDir . $fileName;
    
    if(file_put_contents($filePath, $imageBinary)) {
        $stmt = $pdo->prepare("UPDATE students SET face_image_path = ? WHERE id = ?");
        $stmt->execute([$fileName, $student['id']]);
        $message = '<div class="success">✅ Selfie captured successfully! Redirecting...</div>';
        echo '<meta http-equiv="refresh" content="2;url=dashboard.php">';
    } else {
        $message = '<div class="error">❌ Failed to save selfie</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Capture Selfie | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container {
            background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
            border-radius: 32px; padding: 2rem; width: 500px; max-width: 90%;
            text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 { color: #059669; margin-bottom: 0.5rem; }
        .info { background: #E0E7FF; padding: 12px; border-radius: 12px; margin-bottom: 1rem; font-size: 14px; }
        .btn { background: #059669; color: white; border: none; padding: 12px 24px; border-radius: 40px; cursor: pointer; font-weight: 600; margin-top: 1rem; }
        .btn-warning { background: #F59E0B; }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        video, canvas { width: 100%; border-radius: 16px; background: #000; }
        .preview { margin-top: 1rem; }
        .preview img { width: 150px; border-radius: 50%; border: 3px solid #059669; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📸 Capture Your Selfie</h1>
        <div class="info">This selfie will be used for facial recognition when marking attendance.</div>
        
        <?php echo $message; ?>
        
        <div id="cameraSection">
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <button onclick="captureSelfie()" class="btn">📸 Capture Selfie</button>
        </div>
        
        <div id="previewSection" style="display: none;">
            <h3>Preview:</h3>
            <img id="previewImage">
            <form method="POST">
                <input type="hidden" name="selfie_image" id="selfieImage">
                <button type="submit" name="save_selfie" class="btn btn-warning">✅ Save Selfie</button>
                <button type="button" onclick="retake()" class="btn">🔄 Retake</button>
            </form>
        </div>
    </div>

    <script>
        let videoStream = null;
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        
        async function startCamera() {
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = videoStream;
            } catch(err) {
                alert('Camera access required for attendance verification.');
            }
        }
        
        function captureSelfie() {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            let ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            let imageData = canvas.toDataURL('image/png');
            document.getElementById('previewImage').src = imageData;
            document.getElementById('selfieImage').value = imageData;
            
            document.getElementById('cameraSection').style.display = 'none';
            document.getElementById('previewSection').style.display = 'block';
            
            if(videoStream) videoStream.getTracks().forEach(track => track.stop());
        }
        
        function retake() {
            document.getElementById('cameraSection').style.display = 'block';
            document.getElementById('previewSection').style.display = 'none';
            startCamera();
        }
        
        startCamera();
    </script>
</body>
</html>