<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php"); exit();
}

$round_id = (int)($_GET['round_id'] ?? 0);
if (!$round_id) { header("Location: interview_round.php"); exit(); }

$round = $conn->query("
    SELECT ir.*, a.id AS app_id, a.user_id, a.job_id,
           j.title AS job_title, j.salary AS job_salary, j.city, j.description AS job_desc,
           u.name AS candidate_name, u.email AS candidate_email
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j         ON a.job_id = j.id
    JOIN users u        ON a.user_id = u.id
    WHERE ir.id = $round_id
")->fetch_assoc();
if (!$round) { header("Location: interview_round.php"); exit(); }

$app_id  = (int)$round['app_id'];
$user_id = (int)$round['user_id'];
$job_id  = (int)$round['job_id'];

$video   = $conn->query("SELECT * FROM video_rounds WHERE round_id=$round_id LIMIT 1")->fetch_assoc();
$hr_info = $conn->query("SELECT * FROM users WHERE id={$_SESSION['id']} LIMIT 1")->fetch_assoc();
$offer   = $conn->query("SELECT * FROM offer_letters WHERE application_id=$app_id LIMIT 1")->fetch_assoc();

$success = $error = '';

// ── Save meeting link ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meeting'])) {
    $platform     = $conn->real_escape_string($_POST['platform']         ?? 'google_meet');
    $meeting_link = $conn->real_escape_string(trim($_POST['meeting_link'] ?? ''));
    $interviewer  = $conn->real_escape_string(trim($_POST['interviewer_name'] ?? ''));
    $duration     = (int)($_POST['duration'] ?? 60);
    $notes        = $conn->real_escape_string(trim($_POST['meeting_notes'] ?? ''));
    $scheduled_at = $conn->real_escape_string($_POST['scheduled_at'] ?? '');

    if (empty($meeting_link)) { $error = 'Meeting link required.'; }
    else {
        if (!empty($scheduled_at))
            $conn->query("UPDATE interview_rounds SET scheduled_at='$scheduled_at', status='ongoing' WHERE id=$round_id");
        if ($video) {
            $conn->query("UPDATE video_rounds SET meeting_platform='$platform', meeting_link='$meeting_link',
                interviewer_name='$interviewer', duration_minutes=$duration, meeting_notes='$notes'
                WHERE round_id=$round_id");
        } else {
            $conn->query("INSERT INTO video_rounds (round_id,meeting_platform,meeting_link,interviewer_name,duration_minutes,meeting_notes)
                VALUES ($round_id,'$platform','$meeting_link','$interviewer',$duration,'$notes')");
        }
        $video   = $conn->query("SELECT * FROM video_rounds WHERE round_id=$round_id LIMIT 1")->fetch_assoc();
        $success = '✅ Meeting link saved! Candidate can now see it.';
    }
}

// ── Mark Pass / Fail ───────────────────────────────────────────────────
if (isset($_GET['mark']) && in_array($_GET['mark'], ['pass','fail'])) {
    $res = $_GET['mark'];
    $pct = ($res === 'pass') ? 100.00 : 0.00;
    $ob  = ($res === 'pass') ? 10 : 0;

    $conn->query("UPDATE interview_rounds SET status='completed', result='$res' WHERE id=$round_id");

    $rr = $conn->query("SELECT id FROM round_results WHERE round_id=$round_id AND application_id=$app_id")->fetch_assoc();
    if ($rr) {
        $conn->query("UPDATE round_results SET result='$res', percentage=$pct, obtained_marks=$ob
                      WHERE round_id=$round_id AND application_id=$app_id");
    } else {
        $conn->query("INSERT INTO round_results (application_id,round_id,total_marks,obtained_marks,percentage,result)
                      VALUES ($app_id,$round_id,10,$ob,$pct,'$res')");
    }

    if ($res === 'pass') {
        $hr_sig  = $conn->real_escape_string($hr_info['name'] ?? 'HR Manager');
        $salary  = $conn->real_escape_string($round['job_salary'] ?? '');
        $joining = date('Y-m-d', strtotime('+30 days'));
        $ex_off  = $conn->query("SELECT id FROM offer_letters WHERE application_id=$app_id")->fetch_assoc();
        if (!$ex_off) {
            $conn->query("INSERT INTO offer_letters (application_id,user_id,job_id,salary,joining_date,signature_name,status)
                          VALUES ($app_id,$user_id,$job_id,'$salary','$joining','$hr_sig','Sent')");
        }
        header("Location: add_video_round.php?round_id=$round_id&offer_sent=1"); exit();
    }
    header("Location: add_video_round.php?round_id=$round_id"); exit();
}

// ── Update offer letter ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_offer'])) {
    $salary  = $conn->real_escape_string(trim($_POST['offer_salary']   ?? ''));
    $joining = $conn->real_escape_string($_POST['joining_date']        ?? date('Y-m-d', strtotime('+30 days')));
    $hr_sig  = $conn->real_escape_string(trim($_POST['hr_signature']   ?? $hr_info['name']));
    $msg     = $conn->real_escape_string(trim($_POST['offer_message']  ?? ''));

    if ($offer) {
        $conn->query("UPDATE offer_letters SET salary='$salary', joining_date='$joining',
                      signature_name='$hr_sig', message='$msg', status='Sent'
                      WHERE application_id=$app_id");
    } else {
        $conn->query("INSERT INTO offer_letters (application_id,user_id,job_id,salary,joining_date,signature_name,message,status)
                      VALUES ($app_id,$user_id,$job_id,'$salary','$joining','$hr_sig','$msg','Sent')");
    }
    $offer   = $conn->query("SELECT * FROM offer_letters WHERE application_id=$app_id LIMIT 1")->fetch_assoc();
    $success = '✅ Offer letter sent to candidate!';
}

$result  = strtolower($round['result'] ?? 'pending');
$is_pass = $result === 'pass';
$is_fail = $result === 'fail';
$is_done = $is_pass || $is_fail;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title>Video Round — HR</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;display:flex;min-height:100vh;background:#f0f4f8}
.sidebar{width:220px;min-height:100vh;background:#0f1e46;position:fixed;top:0;left:0;padding-top:24px}
.sidebar h2{text-align:center;color:white;padding:0 12px 20px;font-size:17px}
.sidebar a{display:block;padding:13px 22px;color:#94a3b8;text-decoration:none;font-size:14px}
.sidebar a:hover,.sidebar a.active{background:#1e3a8a;color:white}
.main{margin-left:220px;padding:32px;flex:1}
.hdr{background:linear-gradient(135deg,#0f1e46,#1e3a8a);color:white;border-radius:16px;
     padding:20px 26px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.hdr h1{font-size:19px;font-weight:800}
.hdr .meta{font-size:13px;color:rgba(255,255,255,.7);margin-top:3px}
.pill{padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700}
.p-pass{background:rgba(34,197,94,.25);color:#4ade80}
.p-fail{background:rgba(239,68,68,.25);color:#f87171}
.p-pend{background:rgba(255,255,255,.15);color:rgba(255,255,255,.8)}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px}
.al-s{background:#dcfce7;color:#166534;border:1px solid #86efac}
.al-e{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.al-i{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:800px){.grid{grid-template-columns:1fr}}
.card{background:white;border-radius:14px;padding:22px;box-shadow:0 1px 8px rgba(0,0,0,.07);margin-bottom:18px}
.card h3{font-size:15px;font-weight:800;color:#0f1e46;margin-bottom:14px}
label{display:block;font-size:11px;font-weight:700;color:#374151;margin:12px 0 4px;text-transform:uppercase;letter-spacing:.4px}
input,select,textarea{width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#111}
input:focus,select:focus,textarea:focus{outline:none;border-color:#1e3a8a}
textarea{resize:vertical;min-height:75px}
.radrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}
.ro input[type=radio]{display:none}
.ro label{padding:7px 13px;border:1.5px solid #e5e7eb;border-radius:20px;cursor:pointer;
          font-size:12px;font-weight:600;color:#374151;margin:0;transition:.15s}
.ro input:checked+label{border-color:#1e3a8a;background:#eff6ff;color:#1e3a8a}
.btn{width:100%;padding:11px;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;margin-top:14px;transition:.2s}
.btn-blue{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white}
.btn-green{background:linear-gradient(135deg,#16a34a,#22c55e);color:white}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,.2)}
.res-btns{display:flex;gap:12px;margin-top:10px}
.rb{padding:13px 22px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;
    border:none;cursor:pointer;flex:1;text-align:center;transition:.2s;display:block}
.rb-pass{background:linear-gradient(135deg,#16a34a,#22c55e);color:white}
.rb-fail{background:linear-gradient(135deg,#dc2626,#ef4444);color:white}
.rb:hover{transform:translateY(-2px);color:white}
.banner{border-radius:14px;padding:22px;text-align:center;margin-bottom:18px}
.ban-pass{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2px solid #86efac}
.ban-fail{background:linear-gradient(135deg,#fff5f5,#fee2e2);border:2px solid #fecaca}
.ban-ico{font-size:44px;margin-bottom:8px}
.ban-ttl{font-size:20px;font-weight:900}
.t-pass{color:#16a34a}.t-fail{color:#dc2626}
.preview{background:#f8fafc;border:2px dashed #bfdbfe;border-radius:10px;
         padding:13px;margin-top:10px;font-size:12px;color:#374151;line-height:1.7}
.preview b{color:#1e40af}
</style>
</head>
<body>
<div class="sidebar">
    <h2>HR Panel</h2>
    <a href="hr.php">Dashboard</a>
    <a href="interview_round.php" class="active">Interview Rounds</a>
    <a href="../auth/logout.php">Logout</a>
</div>
<div class="main">
    <div class="hdr">
        <div>
            <h1>📹 Video Round — <?= htmlspecialchars($round['candidate_name']) ?></h1>
            <div class="meta">💼 <?= htmlspecialchars($round['job_title']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($round['candidate_email']) ?></div>
        </div>
        <span class="pill <?= $is_pass?'p-pass':($is_fail?'p-fail':'p-pend') ?>">
            <?= $is_pass?'🏆 Passed':($is_fail?'❌ Failed':'⏳ Pending') ?>
        </span>
    </div>

    <?php if($success): ?><div class="alert al-s"><?= $success ?></div><?php endif; ?>
    <?php if($error):   ?><div class="alert al-e"><?= $error ?></div><?php endif; ?>
    <?php if(isset($_GET['offer_sent'])): ?>
    <div class="alert al-i">🎉 <b>Offer Letter Sent!</b> Candidate can now view and download it from their dashboard.</div>
    <?php endif; ?>

    <?php if($is_done): ?>
    <div class="banner <?= $is_pass?'ban-pass':'ban-fail' ?>">
        <div class="ban-ico"><?= $is_pass?'🏆':'❌' ?></div>
        <div class="ban-ttl <?= $is_pass?'t-pass':'t-fail' ?>">
            <?= $is_pass?'Candidate Passed — Offer Letter Sent!':'Candidate Failed' ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid">
        <!-- LEFT: Meeting -->
        <div>
            <div class="card">
                <h3>📹 Meeting Setup</h3>
                <form method="POST">
                    <label>Platform</label>
                    <div class="radrow">
                        <?php foreach(['google_meet'=>'🟢 Meet','zoom'=>'🔵 Zoom','teams'=>'🟣 Teams','jitsi'=>'🟠 Jitsi'] as $v=>$l): ?>
                        <div class="ro"><input type="radio" name="platform" id="p_<?=$v?>" value="<?=$v?>" <?=($video['meeting_platform']??'google_meet')===$v?'checked':''?>>
                        <label for="p_<?=$v?>"><?=$l?></label></div>
                        <?php endforeach; ?>
                    </div>
                    <label>Meeting Link *</label>
                    <input type="url" name="meeting_link" value="<?=htmlspecialchars($video['meeting_link']??'')?>" placeholder="https://meet.google.com/..." required>
                    <label>Interviewer Name</label>
                    <input type="text" name="interviewer_name" value="<?=htmlspecialchars($video['interviewer_name']??$hr_info['name']??'')?>">
                    <label>Schedule Date & Time</label>
                    <input type="datetime-local" name="scheduled_at" value="<?=$round['scheduled_at']?date('Y-m-d\TH:i',strtotime($round['scheduled_at'])):''?>">
                    <label>Duration</label>
                    <div class="radrow">
                        <?php foreach([30,45,60,90] as $d): ?>
                        <div class="ro"><input type="radio" name="duration" id="d<?=$d?>" value="<?=$d?>" <?=($video['duration_minutes']??60)==$d?'checked':''?>>
                        <label for="d<?=$d?>"><?=$d?> min</label></div>
                        <?php endforeach; ?>
                    </div>
                    <label>Notes for Candidate</label>
                    <textarea name="meeting_notes"><?=htmlspecialchars($video['meeting_notes']??'')?></textarea>
                    <button type="submit" name="save_meeting" class="btn btn-blue">💾 Save Meeting Details</button>
                </form>
            </div>

            <?php if(!$is_done): ?>
            <div class="card">
                <h3>✅ Mark Interview Result</h3>
                <p style="font-size:13px;color:#64748b;margin-bottom:12px">After interview, mark result. <b>Pass = offer letter auto-sent.</b></p>
                <div class="res-btns">
                    <a href="?round_id=<?=$round_id?>&mark=pass" class="rb rb-pass"
                       onclick="return confirm('Mark PASSED? Offer letter will be sent to candidate.')">🏆 Pass</a>
                    <a href="?round_id=<?=$round_id?>&mark=fail" class="rb rb-fail"
                       onclick="return confirm('Mark as FAILED?')">❌ Fail</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Offer Letter -->
        <div>
            <div class="card">
                <h3>📄 Offer Letter</h3>
                <?php if($offer): ?>
                <div class="alert al-s" style="margin-bottom:12px">✅ Offer sent on <?=date('d M Y',strtotime($offer['sent_at']))?></div>
                <?php endif; ?>
                <form method="POST">
                    <label>Offered Salary</label>
                    <input type="text" name="offer_salary" value="<?=htmlspecialchars($offer['salary']??$round['job_salary']??'')?>" placeholder="e.g. ₹30,000 per month">
                    <label>Joining Date</label>
                    <input type="date" name="joining_date" value="<?=$offer['joining_date']??date('Y-m-d',strtotime('+30 days'))?>" min="<?=date('Y-m-d')?>">
                    <label>HR Signature Name</label>
                    <input type="text" name="hr_signature" value="<?=htmlspecialchars($offer['signature_name']??$hr_info['name']??'')?>" placeholder="HR Manager">
                    <label>Message to Candidate</label>
                    <textarea name="offer_message" placeholder="Congratulations! We are pleased to offer you..."><?=htmlspecialchars($offer['message']??'')?></textarea>
                    <div class="preview">
                        📄 <b>Offer for:</b> <?=htmlspecialchars($round['candidate_name'])?><br>
                        🏢 <b>Role:</b> <?=htmlspecialchars($round['job_title'])?><br>
                        📅 <b>Joining:</b> <?=$offer?date('d M Y',strtotime($offer['joining_date'])):date('d M Y',strtotime('+30 days'))?>
                    </div>
                    <button type="submit" name="send_offer" class="btn btn-green">
                        📤 <?=$offer?'Update & Resend Offer':'Send Offer Letter'?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
