<?php
session_start();
include "../config/db.php";

/* 
   Get redirect URL if exists
   Example:
   login.php?redirect=dashboards/apply_job.php?job_id=5
*/
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : "";

if (isset($_POST['login'])) {

    $email = $_POST['email'];
    $pass  = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if ($row && password_verify($pass, $row['password'])) {

        session_regenerate_id(true); // Security improvement

        $_SESSION['id']   = $row['id'];
        $_SESSION['name'] = $row['name'];
        $_SESSION['role'] = $row['role'];

        // ✅ If redirect exists, go there
        if (!empty($_POST['redirect'])) {
            header("Location: ../" . $_POST['redirect']);
        } else {
            header("Location: ../dashboards/" . $row['role'] . ".php");
        }
        exit();

    } else {
        echo "<script>alert('Invalid Login');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login | Hiring System</title>

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
    background:linear-gradient(135deg,#1e3a8a,#2563eb,#0f172a);
}

/* Main Card */
.login-container{
    width:900px;
    height:500px;
    display:flex;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 20px 40px rgba(0,0,0,0.4);
}

/* Left Side */
.left{
    flex:1;
    background:linear-gradient(135deg,#2563eb,#38bdf8);
    color:#fff;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    padding:40px;
    text-align:center;
}

.left h1{
    font-size:32px;
    margin-bottom:15px;
}

.left p{
    font-size:15px;
    opacity:0.9;
}

/* Right Side */
.right{
    flex:1;
    background:rgba(255,255,255,0.95);
    padding:60px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.right h2{
    margin-bottom:30px;
}

/* Input Group */
.input-group{
    position:relative;
    margin-bottom:25px;
}

.input-group input{
    width:100%;
    padding:12px 10px;
    border:none;
    border-bottom:2px solid #ccc;
    outline:none;
    font-size:14px;
    background:transparent;
}

.input-group label{
    position:absolute;
    left:10px;
    top:12px;
    color:#888;
    font-size:14px;
    transition:0.3s;
}

.input-group input:focus ~ label,
.input-group input:valid ~ label{
    top:-10px;
    font-size:12px;
    color:#2563eb;
}

/* Button */
button{
    padding:12px;
    background:#2563eb;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
    transition:0.3s;
}

button:hover{
    background:#1d4ed8;
    transform:scale(1.05);
}

/* Links */
.links{
    margin-top:20px;
}

.links a{
    display:block;
    text-decoration:none;
    color:#2563eb;
    margin-top:10px;
    font-size:14px;
}

.links a:hover{
    text-decoration:underline;
}
</style>
</head>

<body>

<div class="login-container">

    <!-- Left Info Panel -->
    <div class="left">
        <h1>Welcome Back!</h1>
        <p>Login to continue your hiring journey with top companies.</p>
    </div>

    <!-- Right Login Form -->
    <div class="right">
        <h2>Login</h2>

        <form method="post">

            <!-- Hidden Redirect Field -->
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="input-group">
                <input type="email" name="email" required>
                <label>Email</label>
            </div>

            <div class="input-group">
                <input type="password" name="password" required>
                <label>Password</label>
            </div>

            <button name="login">Login</button>

            <div class="links">
                <a href="forgot_password.php">Forgot Password?</a>
                <a href="register.php">Create Account</a>
            </div>

        </form>
    </div>

</div>

</body>
</html>