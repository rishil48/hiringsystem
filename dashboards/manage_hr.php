<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_POST['add_hr'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Password encrypt
    $pass = password_hash($password, PASSWORD_DEFAULT);

    $conn->query("INSERT INTO users (name,email,password,role)
                  VALUES ('$name','$email','$pass','hr')");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage HR</title>
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', sans-serif;
}

body{
    background: linear-gradient(135deg,#0f172a,#1e293b);
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:40px 20px;
    color:white;
}

h2{
    margin-bottom:25px;
    font-size:28px;
}

form{
    background:#1e293b;
    padding:20px;
    border-radius:12px;
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:30px;
    width:100%;
    max-width:700px;
}

form input{
    flex:1;
    padding:10px;
    border:none;
    border-radius:8px;
}

form button{
    padding:10px 20px;
    border:none;
    border-radius:8px;
    background:#06b6d4;
    color:white;
    font-weight:600;
    cursor:pointer;
}

table{
    width:100%;
    max-width:700px;
    border-collapse:collapse;
    background:#1e293b;
}

table th{
    background:#06b6d4;
    padding:12px;
}

table td{
    padding:12px;
    border-bottom:1px solid #334155;
}
</style>
</head>
<body>

<h2>Manage HR</h2>

<form method="post">
    <input type="text" name="name" placeholder="HR Name" required>
    <input type="email" name="email" placeholder="HR Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button name="add_hr">Add HR</button>
</form>

<table>
<tr>
<th>Name</th>
<th>Email</th>
<th>Password (Encrypted)</th>
</tr>

<?php
$res = $conn->query("SELECT * FROM users WHERE role='hr'");
while ($r = $res->fetch_assoc()) {
    echo "<tr>
            <td>{$r['name']}</td>
            <td>{$r['email']}</td>
            <td>{$r['password']}</td>
          </tr>";
}
?>

</table>

</body>
</html>