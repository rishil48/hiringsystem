<?php
session_start();
include "../config/db.php";

$uid=$_SESSION['user_id'];

if(isset($_POST['accept'])){
    $conn->query("
    UPDATE offer_letters 
    SET status='Accepted',
        signature_name='{$_POST['sign']}',
        response_date=NOW()
    WHERE id={$_POST['id']}
    ");
}

if(isset($_POST['reject'])){
    $conn->query("
    UPDATE offer_letters 
    SET status='Rejected',
        response_date=NOW()
    WHERE id={$_POST['id']}
    ");
}

$offers=$conn->query("
SELECT o.*,j.title 
FROM offer_letters o
JOIN jobs j ON o.job_id=j.id
WHERE o.user_id=$uid
");
?>

<h2>My Offer Letters</h2>

<?php while($o=$offers->fetch_assoc()): ?>
<div class="card">
<h3><?= $o['title'] ?></h3>
<p>Salary: <?= $o['salary'] ?></p>
<p>Joining: <?= $o['joining_date'] ?></p>
<p><?= nl2br($o['message']) ?></p>

<?php if($o['status']=='Sent'): ?>
<form method="post">
<input type="hidden" name="id" value="<?= $o['id'] ?>">
<input name="sign" placeholder="Type Full Name" required>
<button name="accept">Accept</button>
<button name="reject">Reject</button>
</form>
<?php else: ?>
<p>Status: <?= $o['status'] ?></p>
<a href="../offer_pdf.php?id=<?= $o['id'] ?>">Download PDF</a>
<a href="user_offer_letter.php">My Offer Letters</a>

<?php endif; ?>
</div>
<?php endwhile; ?>
