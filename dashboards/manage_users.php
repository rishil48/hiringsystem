<?php
session_start();
include "../config/db.php";
if ($_SESSION['role'] != 'admin') exit();

// ADD USER
if (isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $pass = password_hash($password, PASSWORD_DEFAULT);

    $conn->query("INSERT INTO users (name,email,password,role) 
                  VALUES ('$name','$email','$pass','user')");
}

// UPDATE USER
if (isset($_POST['update_user'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];

    $conn->query("UPDATE users SET name='$name', email='$email' WHERE id=$id");
}

// DELETE USER
if (isset($_POST['delete_user'])) {
    $id = $_POST['id'];
    $conn->query("DELETE FROM users WHERE id=$id");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Users</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;
}

body{
background:linear-gradient(135deg,#0f172a,#1e293b);
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
padding:40px;
color:white;
}

.container{
width:100%;
max-width:1200px;
}

h2{
text-align:center;
margin-bottom:30px;
font-size:32px;
}

.card{
background:#1e293b;
padding:25px;
border-radius:16px;
box-shadow:0 15px 35px rgba(0,0,0,0.4);
margin-bottom:35px;
}

.form-row{
display:flex;
gap:20px;
flex-wrap:wrap;
}

.form-row input{
flex:1;
padding:12px;
border:none;
border-radius:8px;
}

.form-row button{
padding:12px 22px;
border:none;
border-radius:8px;
background:#06b6d4;
color:white;
font-weight:600;
cursor:pointer;
}

table{
width:100%;
border-collapse:collapse;
}

table th{
background:#06b6d4;
padding:14px;
text-align:left;
}

table td{
padding:12px;
border-bottom:1px solid #334155;
}

table input{
width:100%;
padding:6px;
border-radius:6px;
border:none;
}

/* dropdown style */

select{
padding:6px;
border-radius:6px;
border:none;
}

.action-btn{
padding:6px 12px;
border:none;
border-radius:6px;
cursor:pointer;
font-size:13px;
margin-top:5px;
}

.update-btn{
background:#16a34a;
color:white;
}

.delete-btn{
background:#dc2626;
color:white;
}

</style>
</head>

<body>

<div class="container">

<h2>Manage Users</h2>

<!-- ADD USER -->
<div class="card">

<form method="post">

<div class="form-row">

<input type="text" name="name" placeholder="User Name" required>

<input type="email" name="email" placeholder="User Email" required>

<input type="password" name="password" placeholder="Password" required>

<button name="add_user">Add User</button>

</div>

</form>

</div>

<!-- USERS TABLE -->

<div class="card">

<table>

<tr>
<th>Name</th>
<th>Email</th>
<th style="width:200px;">Actions</th>
</tr>

<?php

$res = $conn->query("SELECT * FROM users WHERE role='user'");

while ($r = $res->fetch_assoc()) {

echo "

<tr>

<form method='post'>

<td>
<input type='text' name='name' value='{$r['name']}'>
</td>

<td>
<input type='email' name='email' value='{$r['email']}'>
</td>

<td>

<input type='hidden' name='id' value='{$r['id']}'>

<select onchange='handleAction(this)'>

<option value=''>Select</option>
<option value='update'>Update</option>
<option value='delete'>Delete</option>

</select>

<br>

<button class='action-btn update-btn' name='update_user' style='display:none;'>Update</button>

<button class='action-btn delete-btn' name='delete_user' style='display:none;'>Delete</button>

</td>

</form>

</tr>

";

}

?>

</table>

</div>

</div>

<script>

function handleAction(select){

let form = select.closest("form");

if(select.value === "update"){

form.querySelector("[name='update_user']").style.display="inline-block";

}

if(select.value === "delete"){

form.querySelector("[name='delete_user']").style.display="inline-block";

}

}

</script>

</body>
</html>