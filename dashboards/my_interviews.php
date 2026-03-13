<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['id'];
$now     = time();
?>

<!DOCTYPE html>
<html>
<head>
<title>My Interviews</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial; background: #f4f6f9; padding: 30px; }
h2 { color: #0b1d39; margin-bottom: 24px; }

.section-title {
    font-size: 16px; font-weight: bold; color: #2563eb;
    margin: 30px 0 12px; padding-bottom: 6px;
    border-bottom: 2px solid #2563eb;
}

.card {
    background: #fff; border-radius: 12px; padding: 20px;
    margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.07);
    border-left: 5px solid #2563eb;
}
.card.completed { border-left-color: #16a34a; }
.card.failed    { border-left-color: #dc2626; }
.card.Scheduled { border-left-color: #f59e0b; }
.card.pending   { border-left-color: #2563eb; }

.card h3 { font-size: 16px; color: #0b1d39; margin-bottom: 12px; }
.card p  { font-size: 14px; color: #555; margin: 5px 0; }

.badge {
    display: inline-block; padding: 3px 12px;
    border-radius: 20px; font-size: 12px; font-weight: bold;
}
.badge-pending, .badge-Scheduled { background: #fef3c7; color: #92400e; }
.badge-completed                  { background: #d1fae5; color: #065f46; }
.badge-failed                     { background: #fee2e2; color: #991b1b; }

.join-btn {
    display: inline-block; margin-top: 12px; padding: 10px 22px;
    background: #2563eb; color: #fff; border-radius: 8px;
    text-decoration: none; font-size: 14px; font-weight: bold;
}
.join-btn:hover { background: #1d4ed8; }
.join-btn.offline { background: #16a34a; }

.countdown {
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 8px; padding: 10px 14px; margin-top: 12px;
    font-size: 13px; color: #1e40af; font-weight: bold;
}

.mode-tag {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 12px; font-weight: bold;
    background: #e0f2fe; color: #0369a1;
}

.no-data {
    text-align: center; padding: 40px; color: #888;
    font-size: 15px; background: #fff; border-radius: 10px;
}

.divider { height: 1px; background: #e5e7eb; margin: 8px 0; }
</style>
</head>
<body>

<h2>🗓️ My Interview Schedule</h2>

<?php
// =============================================
// TABLE 1 — interviews (HR ne schedule kiya)
// =============================================
$stmt1 = $conn->prepare("
    SELECT i.id, i.interview_date, i.interview_time,
           i.mode, i.video_link, i.status,
           j.title AS job_title,
           'scheduled' AS source
    FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = ?
    ORDER BY i.interview_date ASC, i.interview_time ASC
");
$stmt1->bind_param("i", $user_id);
$stmt1->execute();
$interviews = $stmt1->get_result();

// =============================================
// TABLE 2 — interview_rounds (round tracking)
// =============================================
$stmt2 = $conn->prepare("
    SELECT ir.id, ir.round_type, ir.round_date,
           ir.status, ir.zoom_link,
           j.title AS job_title,
           'round' AS source
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = ?
    ORDER BY ir.round_date ASC
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$rounds = $stmt2->get_result();
?>

<!-- ============ SECTION 1: SCHEDULED INTERVIEWS ============ -->
<div class="section-title">📅 Scheduled Interviews</div>

<?php if($interviews->num_rows == 0): ?>
    <div class="no-data">📭 Koi scheduled interview nahi hai abhi.</div>
<?php else:
    while($row = $interviews->fetch_assoc()):
        $datetime_str = $row['interview_date'] . ' ' . $row['interview_time'];
        $interview_ts = strtotime($datetime_str);
        $diff         = $interview_ts - $now;
        $statusClass  = strtolower($row['status']);
?>
    <div class="card <?= $statusClass ?>">
        <h3>💼 <?= htmlspecialchars($row['job_title']) ?></h3>
        <div class="divider"></div>
        <p>📅 <b>Date:</b> <?= date('d M Y', strtotime($row['interview_date'])) ?></p>
        <p>🕐 <b>Time:</b> <?= date('h:i A', strtotime($row['interview_time'])) ?></p>
        <p>📍 <b>Mode:</b>
            <span class="mode-tag"><?= htmlspecialchars($row['mode']) ?></span>
        </p>
        <p>🔖 <b>Status:</b>
            <span class="badge badge-<?= $row['status'] ?>">
                <?= ucfirst($row['status']) ?>
            </span>
        </p>

        <?php if(strtolower($row['status']) == 'scheduled'): ?>

            <?php if($diff > 3600): ?>
                <!-- Abhi door hai -->
                <div class="countdown">
                    ⏳ Interview starts in:
                    <span class="timer" data-time="<?= $interview_ts ?>">calculating...</span>
                </div>
                <?php if($row['mode'] == 'Online' && $row['video_link']): ?>
                    <p style="color:#f59e0b;font-size:13px;margin-top:8px">
                        🔒 Join link 1 hour before interview activate hoga.
                    </p>
                <?php endif; ?>

            <?php elseif($diff >= 0 && $diff <= 3600): ?>
                <!-- 1 ghante ke andar — JOIN ACTIVE -->
                <?php if($row['mode'] == 'Online'): ?>
                    <?php if($row['video_link']): ?>
                        <a href="<?= htmlspecialchars($row['video_link']) ?>"
                           target="_blank" class="join-btn">
                            🎥 Join Interview Now
                        </a>
                    <?php else: ?>
                        <p style="color:#dc2626;margin-top:10px;font-size:13px">
                            ❌ Video link HR ne abhi add nahi kiya. Unse contact karein.
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="join-btn offline">📍 Offline Interview — Venue par pahunchein</a>
                <?php endif; ?>

            <?php else: ?>
                <!-- Time nikal gaya -->
                <?php if($row['video_link']): ?>
                    <a href="<?= htmlspecialchars($row['video_link']) ?>"
                       target="_blank" class="join-btn">
                        🎥 Join Interview
                    </a>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif(strtolower($row['status']) == 'completed'): ?>
            <p style="color:#16a34a;margin-top:10px">✅ Yeh interview complete ho gaya hai.</p>

        <?php elseif(strtolower($row['status']) == 'failed'): ?>
            <p style="color:#dc2626;margin-top:10px">❌ Is interview mein aap select nahi hue.</p>
        <?php endif; ?>
    </div>
<?php endwhile; endif; ?>


<!-- ============ SECTION 2: INTERVIEW ROUNDS ============ -->
<div class="section-title">🔄 Interview Rounds</div>

<?php if($rounds->num_rows == 0): ?>
    <div class="no-data">📭 Koi interview round schedule nahi hai abhi.</div>
<?php else:
    while($row = $rounds->fetch_assoc()):
        $round_ts   = strtotime($row['round_date']);
        $diff       = $round_ts - $now;
        $statusClass = strtolower($row['status']);
?>
    <div class="card <?= $statusClass ?>">
        <h3>💼 <?= htmlspecialchars($row['job_title']) ?></h3>
        <div class="divider"></div>
        <p>📋 <b>Round:</b> <?= htmlspecialchars($row['round_type']) ?></p>
        <p>📅 <b>Date & Time:</b> <?= date('d M Y, h:i A', $round_ts) ?></p>
        <p>🔖 <b>Status:</b>
            <span class="badge badge-<?= $row['status'] ?>">
                <?= ucfirst($row['status']) ?>
            </span>
        </p>

        <?php if($row['status'] == 'pending'): ?>

            <?php if($diff > 3600): ?>
                <div class="countdown">
                    ⏳ Round starts in:
                    <span class="timer" data-time="<?= $round_ts ?>">calculating...</span>
                </div>
                <?php if($row['zoom_link']): ?>
                    <p style="color:#f59e0b;font-size:13px;margin-top:8px">
                        🔒 Join link 1 hour before activate hoga.
                    </p>
                <?php endif; ?>

            <?php elseif($diff >= 0 && $diff <= 3600): ?>
                <?php if($row['zoom_link']): ?>
                    <a href="<?= htmlspecialchars($row['zoom_link']) ?>"
                       target="_blank" class="join-btn">
                        🎥 Join Round Now
                    </a>
                <?php else: ?>
                    <p style="color:#dc2626;margin-top:10px;font-size:13px">
                        ❌ Zoom link HR ne abhi add nahi kiya.
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <?php if($row['zoom_link']): ?>
                    <a href="<?= htmlspecialchars($row['zoom_link']) ?>"
                       target="_blank" class="join-btn">🎥 Join Round</a>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif($row['status'] == 'completed'): ?>
            <p style="color:#16a34a;margin-top:10px">✅ Yeh round complete ho gaya.</p>

        <?php elseif($row['status'] == 'failed'): ?>
            <p style="color:#dc2626;margin-top:10px">❌ Is round mein aap select nahi hue.</p>
        <?php endif; ?>
    </div>
<?php endwhile; endif; ?>


<!-- ✅ COUNTDOWN TIMER -->
<script>
function updateTimers() {
    document.querySelectorAll('.timer').forEach(function(el) {
        const target = parseInt(el.getAttribute('data-time')) * 1000;
        const now    = Date.now();
        const diff   = target - now;

        if (diff <= 0) {
            el.closest('.countdown').innerHTML =
                '🔔 Interview time aa gaya! <a href="" onclick="location.reload()">Refresh karein</a>';
            return;
        }

        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);

        el.textContent = h + ' hr ' + m + ' min ' + s + ' sec';
    });
}

updateTimers();
setInterval(updateTimers, 1000);
</script>

</body>
</html>