<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return ($R * $c) * 1000;
}

function isWithinAttendanceWindow($classEndTime, $sessionDateTime) {
    $sessionDate = new DateTime($sessionDateTime);
    $endTime = new DateTime($classEndTime);
    $sessionDate->setTime($endTime->format('H'), $endTime->format('i'), 0);
    $windowStart = clone $sessionDate;
    $windowStart->modify('-30 minutes');
    $now = new DateTime();
    return $now >= $windowStart && $now <= $sessionDate;
}

function sendEmail($to, $subject, $body) {
    $headers = "From: noreply@attendpro.com\r\n";
    $headers .= "Reply-To: support@attendpro.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}
?>