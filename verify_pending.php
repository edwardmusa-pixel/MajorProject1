<?php
session_start();
if(!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'lecturer' && $_SESSION['user']['role'] !== 'admin')) {
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

$message = '';
$selectedClass = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Handle single status updates or bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Bulk Action: Approve All Pending for the selected class
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'approve_all' && $selectedClass > 0) {
        $bulkStmt = $pdo->prepare("UPDATE attendance_records SET status = 'Present', status_time = NOW() WHERE class_id = ? AND status = 'Pending'");
        if ($bulkStmt->execute([$selectedClass])) {
            $message = '<div class="success">🚀 Approved all pending scans for this class!</div>';
        }
    }
    // 2. Single Student Action
    elseif (isset($_POST['action'], $_POST['record_id'])) {
        $recordId = intval($_POST['record_id']);
        $action = $_POST['action'];
        
        if (in_array($action, ['Present', 'Absent'])) {
            $updateStmt = $pdo->prepare("UPDATE attendance_records SET status = ?, status_time = NOW() WHERE id = ?");
            if ($updateStmt->execute([$action, $recordId])) {
                $message = '<div class="success">✅ Record updated to <strong>' . $action . '</strong> successfully!</div>';
            } else {
                $message = '<div class="error">❌ Failed to update status logs.</div>';
            }
        }
    }
}

// Fetch classes taught by this user to populate filter drop-down
$classesStmt = $pdo->query("SELECT id, class_code, class_name FROM classes ORDER BY class_code ASC");
$classes = $classesStmt->fetchAll();

// Base query matching table structures
$queryStr = "SELECT ar.id as record_id, ar.status, ar.status_time, s.student_id as matric_number, u.name as student_name, c.class_name, c.class_code 
             FROM attendance_records ar
             JOIN students s ON ar.student_id = s.id
             JOIN users u ON s.user_id = u.id
             JOIN classes c ON ar.class_id = c.id";

if ($selectedClass > 0) {
    $queryStr .= " WHERE ar.class_id = ? ORDER BY CASE WHEN ar.status = 'Pending' THEN 1 ELSE 2 END, ar.status_time DESC";
    $recordsStmt = $pdo->prepare($queryStr);
    $recordsStmt->execute([$selectedClass]);
} else {
    $queryStr .= " ORDER BY CASE WHEN ar.status = 'Pending' THEN 1 ELSE 2 END, ar.status_time DESC";
    $recordsStmt = $pdo->query($queryStr);
}
$records = $recordsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Verification | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #F0FDFA 0%, #CCFBF1 100%); min-height: 100vh; color: #1F2937; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 1.5rem; }
        .card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(16px); border-radius: 24px; padding: 2.5rem; border: 1px solid rgba(255,255,255,0.5); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 1.5rem; }
        h1 { color: #0F766E; font-size: 1.75rem; }
        
        .management-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 2rem; }
        .filter-form { display: flex; gap: 12px; align-items: center; }
        select { padding: 10px 16px; border-radius: 12px; border: 1px solid #CBD5E1; background: white; font-size: 0.95rem; outline: none; min-width: 200px; }
        
        .btn { padding: 10px 18px; border-radius: 12px; font-weight: 600; cursor: pointer; border: none; font-size: 0.9rem; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #0F766E; color: white; }
        .btn-primary:hover { background: #115E59; }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-danger:hover { background: #DC2626; }
        .btn-bulk { background: #0284C7; color: white; }
        .btn-bulk:hover { background: #0369A1; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; text-align: left; }
        th { padding: 14px; background: rgba(15, 118, 110, 0.08); color: #0F766E; font-weight: 600; font-size: 0.9rem; }
        td { padding: 14px; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.95rem; }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-pending { background: #FEF3C7; color: #D97706; animation: pulse 2s infinite; }
        .badge-present { background: #D1FAE5; color: #065F46; }
        .badge-absent { background: #FEE2E2; color: #991B1B; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .action-cell { display: flex; gap: 6px; }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #10B981; }
        .error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #EF4444; }
        .empty-row { text-align: center; color: #6B7280; padding: 30px; font-style: italic; }
    </style>
</head>
<body>

    <div class="container">
        <div class="card">
            <div class="header-section">
                <div>
                    <h1>👨‍🏫 Lecturer Verification Panel</h1>
                    <p style="color: #6B7280; font-size: 0.85rem; margin-top: 4px;">Review and sign off real-time student submissions</p>
                </div>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>

            <?php echo $message; ?>

            <div class="management-bar">
                <form method="GET" action="" class="filter-form" id="filterForm">
                    <label style="font-weight: 500;" for="class_id">Filter Class:</label>
                    <select name="class_id" id="class_id" onchange="this.form.submit()">
                        <option value="0">--- All Registered Subjects ---</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === intval($c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_code'] . ' - ' . $c['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedClass > 0): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="bulk_action" value="approve_all">
                        <button type="submit" class="btn btn-bulk" onclick="return confirm('Approve all currently pending student records?')">
                            🚀 Approve All Pending
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div id="attendanceTableContainer">
                <table>
                    <thead>
                        <tr>
                            <th>Matric / ID</th>
                            <th>Student Name</th>
                            <th>Class Subject</th>
                            <th>Scan Timestamp</th>
                            <th>Current Status</th>
                            <th>Verification Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) === 0): ?>
                            <tr>
                                <td colspan="6" class="empty-row">No attendance scans registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($records as $row): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['matric_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><small><?php echo htmlspecialchars($row['class_code'] . ': ' . $row['class_name']); ?></small></td>
                                    <td><?php echo date('h:i A (d M)', strtotime($row['status_time'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="action-cell">
                                        <?php if($row['status'] === 'Pending'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="record_id" value="<?php echo $row['record_id']; ?>">
                                                <input type="hidden" name="action" value="Present">
                                                <button type="submit" class="btn btn-success" style="padding: 6px 12px; font-size:0.8rem;">Accept</button>
                                            </form>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="record_id" value="<?php echo $row['record_id']; ?>">
                                                <input type="hidden" name="action" value="Absent">
                                                <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size:0.8rem;">Deny</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #9CA3AF; font-size: 0.85rem; font-style: italic;">Verified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function liveFetchPendingLogs() {
            const classId = document.getElementById('class_id').value;
            
            // Asynchronously fetch current page content snippet
            fetch(`verify-pending.php?class_id=${classId}`)
                .then(response => response.text())
                .then(html => {
                    // Instantly isolate table from structural updates using DOM Parser strings
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableContent = doc.getElementById('attendanceTableContainer').innerHTML;
                    
                    // Update layout visually only if lecturer isn't actively focusing on processing an entry form
                    if (document.activeElement.tagName !== 'BUTTON') {
                        document.getElementById('attendanceTableContainer').innerHTML = newTableContent;
                    }
                })
                .catch(err => console.warn('Realtime sync stall: ', err));
        }

        // Poll database for incoming scans every 4 seconds
        setInterval(liveFetchPendingLogs, 4000);
    </script>
</body>
</html>