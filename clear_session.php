<?php
session_start();
session_destroy();
echo "Session cleared! <a href='index.php'>Go to Login</a>";
?>