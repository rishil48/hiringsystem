<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php");
    exit();
}

/* INTERVIEW */
if(isset($_GET['interview'])){
    $id = intval($_GET['interview']);

    $conn->query("UPDATE applications SET status='interview' WHERE id=$id");

    $date = date("Y-m-d");
    $time = date("H:i:s");

    $conn->query("
        INSERT INTO interviews 
        (application_id, interview_date, interview_time, mode, video_link, status)
        VALUES 
        ($id, '$date', '$time', 'Online', 'https://zoom.us', 'Scheduled')
    ");

    header("Location: hr.php?page=applications");
    exit();
}

/* SELECT */
if(isset($_GET['select'])){
    $id = intval($_GET['select']);
    $conn->query("UPDATE applications SET status='selected' WHERE id=$id");
    header("Location: hr.php?page=applications");
    exit();
}

/* REJECT */
if(isset($_GET['reject'])){
    $id = intval($_GET['reject']);

    // ❌ Delete application
    $conn->query("DELETE FROM applications WHERE id=$id");

    header("Location: hr.php?page=applications");
    exit();
}
?>