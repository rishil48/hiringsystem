<?php
// Include this snippet in your HR dashboard (index.php) to show pending technical rounds
// Usage: include 'hr_tech_notifications.php';

$pending_tech = $conn->query("
    SELECT
        ir.id AS round_id,
        ir.round_title,
        ir.scheduled_at,
        u.name AS candidate_name,
        j.title AS job_title,
        a.id AS app_id,
        (SELECT COUNT(*) FROM technical_rounds WHERE round_id = ir.id) AS prob_count
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN users u ON a.user_id = u.id
    JOIN jobs j ON a.job_id = j.id
    WHERE LOWER(ir.round_type) = 'technical'
      AND LOWER(ir.status) = 'pending'
      AND (SELECT COUNT(*) FROM technical_rounds WHERE round_id = ir.id) = 0
    ORDER BY ir.id DESC
    LIMIT 10
");
$ptech_count = $pending_tech ? $pending_tech->num_rows : 0;

if ($ptech_count > 0):
?>
<div style="background:#fef9c3;border:2px solid #fcd34d;border-radius:14px;padding:20px 24px;margin-bottom:24px">
    <div style="font-size:16px;font-weight:800;color:#92400e;margin-bottom:14px">
        ⚡ <?= $ptech_count ?> Technical Round(s) Need Your Attention!
        <span style="font-size:12px;font-weight:500;color:#b45309;margin-left:8px">Candidates passed MCQ — add problems now</span>
    </div>
    <?php while($pt = $pending_tech->fetch_assoc()): ?>
    <div style="background:white;border:1px solid #fcd34d;border-radius:10px;padding:14px 18px;margin-bottom:10px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <div style="font-weight:700;color:#0f1e46;font-size:14px">👤 <?= htmlspecialchars($pt['candidate_name']) ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px">💼 <?= htmlspecialchars($pt['job_title']) ?></div>
        </div>
        <div style="font-size:12px;color:#92400e;font-weight:600">
            ⚠️ No problems added yet
        </div>
        <a href="add_technical_round.php?round_id=<?= $pt['round_id'] ?>&application_id=<?= $pt['app_id'] ?>"
           style="background:linear-gradient(135deg,#92400e,#d97706);color:white;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap">
            💻 Add Problems →
        </a>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>
