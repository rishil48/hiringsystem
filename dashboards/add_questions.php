<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['round_id'])) {
    die("Invalid Access");
}

$round_id = $_GET['round_id'];

if(isset($_POST['add_question'])){
    $question = $_POST['question'];
    $op1 = $_POST['op1'];
    $op2 = $_POST['op2'];
    $op3 = $_POST['op3'];
    $op4 = $_POST['op4'];
    $answer = $_POST['answer'];

    $stmt = $conn->prepare("INSERT INTO questions (round_id, question, op1, op2, op3, op4, answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $round_id, $question, $op1, $op2, $op3, $op4, $answer);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add MCQ Questions</title>
<style>
body{font-family:Segoe UI;background:#f8fafc;padding:40px;}
.container{max-width:600px;margin:auto;background:white;padding:30px;border-radius:10px;}
input,textarea,select,button{width:100%;padding:10px;margin-bottom:15px;}
button{background:#16a34a;color:white;border:none;border-radius:6px;}
</style>
</head>

<body>

<div class="container">
<h2>Add MCQ Question</h2>

<form method="POST">
<textarea name="question" placeholder="Enter Question" required></textarea>
<input type="text" name="op1" placeholder="Option 1" required>
<input type="text" name="op2" placeholder="Option 2" required>
<input type="text" name="op3" placeholder="Option 3" required>
<input type="text" name="op4" placeholder="Option 4" required>

<select name="answer" required>
<option value="">Correct Answer</option>
<option value="1">Option 1</option>
<option value="2">Option 2</option>
<option value="3">Option 3</option>
<option value="4">Option 4</option>
</select>

<button type="submit" name="add_question">Add Question</button>
</form>

</div>
</body>
</html>