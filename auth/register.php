<?php
include "../config/db.php";

if (isset($_POST['register'])) {

    $name  = $_POST['name'];
    $email = $_POST['email'];
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role  = $_POST['role'];

    // Secure prepared statement
    $stmt = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $name, $email, $pass, $role);
    $stmt->execute();

    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Register | Hiring System</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body{
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(-45deg,#1e3a8a,#2563eb,#0f172a,#38bdf8);
    background-size:400% 400%;
    animation:gradientBG 12s ease infinite;
}

/* Animated Gradient */
@keyframes gradientBG{
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}

/* Glass Card */
.register-box{
    width:400px;
    padding:40px;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(15px);
    border-radius:20px;
    box-shadow:0 15px 35px rgba(0,0,0,0.4);
    color:#fff;
}

.register-box h2{
    text-align:center;
    margin-bottom:25px;
}

/* Inputs */
.register-box input,
.register-box select{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:none;
    border-radius:8px;
    outline:none;
    font-size:14px;
}

/* Dropdown styling */
.register-box select{
    cursor:pointer;
}

/* Button */
.register-box button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#2563eb;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}

.register-box button:hover{
    background:#1d4ed8;
    transform:scale(1.05);
}

/* Link */
.register-box a{
    display:block;
    text-align:center;
    margin-top:15px;
    color:#fff;
    text-decoration:none;
    font-size:14px;
}

.register-box a:hover{
    text-decoration:underline;
}
</style>
</head>

<body>

<form method="post" class="register-box">
    <h2>Create Account</h2>

    <input type="text" name="name" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email Address" required>
    <input type="password" name="password" placeholder="Password" required>

    <select name="role">
        <option value="user">User</option>
        <option value="hr">HR</option>
        <option value="admin">Admin</option>
    </select>

    <button name="register">Register</button>

    <a href="login.php">Already have an account? Login</a>
</form>

</body>
</html>