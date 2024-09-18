<?php
session_start();

// Hardcoded username and password for demonstration. You should replace this with a database lookup.
$valid_username = "user";
$valid_password = "agritech";  // Replace this with a hashed password in production.

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Simple authentication (replace this logic with proper database queries).
    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['username'] = $username;
        header("Location: index.php");
    } else {
        echo "Invalid username or password.";
        echo "<br><a href='login.php'>Try again</a>";
    }
} else {
    // Redirect if accessed without POST.
    header("Location: login.php");
}
?>
