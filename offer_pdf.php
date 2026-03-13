<?php
require 'vendor/autoload.php';
include "config/db.php";

$id=$_GET['id'];
$o=$conn->query("
SELECT o.*,u.name,j.title
FROM offer_letters o
JOIN users u ON o.user_id=u.id
JOIN jobs j ON o.job_id=j.id
WHERE o.id=$id
")->fetch_assoc();

$mpdf=new \Mpdf\Mpdf();
$mpdf->WriteHTML("
<h2>Offer Letter</h2>
<p>Name: {$o['name']}</p>
<p>Position: {$o['title']}</p>
<p>Salary: {$o['salary']}</p>
<p>Joining: {$o['joining_date']}</p>
<p>{$o['message']}</p>
<p>Signature: {$o['signature_name']}</p>
");
$mpdf->Output();
