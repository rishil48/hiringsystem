<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php"); exit();
}

$user_id = (int)$_SESSION['id'];

// Get latest offer letter for this user
$offer = $conn->query("
    SELECT ol.*,
           j.title  AS job_title,
           j.city   AS job_city,
           j.description AS job_desc,
           u.name   AS candidate_name,
           u.email  AS candidate_email
    FROM offer_letters ol
    JOIN jobs j ON ol.job_id = j.id
    JOIN users u ON ol.user_id = u.id
    WHERE ol.user_id = $user_id
    ORDER BY ol.sent_at DESC
    LIMIT 1
")->fetch_assoc();

if (!$offer) {
    // No offer yet — redirect
    header("Location: my_tests.php"); exit();
}

$today     = date('d F Y');
$sent_date = date('d F Y', strtotime($offer['sent_at']));
$join_date = date('d F Y', strtotime($offer['joining_date']));
$offer_id  = 'OL-' . str_pad($offer['id'], 5, '0', STR_PAD_LEFT);
$company   = 'Hiring Management System'; // change as needed
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Offer Letter</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',Arial,sans-serif;background:#f0f4f8;min-height:100vh}

/* Sidebar */
.sidebar{width:220px;min-height:100vh;background:#0f1e46;position:fixed;top:0;left:0;padding-top:24px;z-index:10}
.sidebar h2{text-align:center;color:white;padding:0 12px 20px;font-size:17px}
.sidebar a{display:block;padding:13px 22px;color:#94a3b8;text-decoration:none;font-size:14px}
.sidebar a:hover,.sidebar a.active{background:#1e3a8a;color:white}

.wrap{margin-left:220px;padding:32px;display:flex;flex-direction:column;align-items:center}

/* Top bar */
.topbar{width:100%;max-width:820px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.topbar h1{font-size:22px;font-weight:800;color:#0f1e46}
.topbar .sub{font-size:13px;color:#64748b;margin-top:3px}
.dl-btn{background:linear-gradient(135deg,#16a34a,#22c55e);color:white;border:none;
        padding:12px 26px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;
        display:flex;align-items:center;gap:8px;transition:.2s;text-decoration:none}
.dl-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(22,163,74,.35);color:white}
.dl-btn:disabled{opacity:.6;pointer-events:none}

/* ── THE OFFER LETTER ─────────────────────────────────────────── */
#offer-letter{
    width:820px;max-width:100%;
    background:white;
    border-radius:4px;
    box-shadow:0 4px 40px rgba(0,0,0,.12);
    overflow:hidden;
    font-family:'DM Sans',Arial,sans-serif;
    color:#1e1e1e;
}

/* Top accent bar */
.ol-topbar{height:10px;background:linear-gradient(90deg,#0f1e46,#1e3a8a,#3b82f6)}

/* Header */
.ol-head{padding:36px 52px 28px;background:linear-gradient(135deg,#0f1e46 0%,#1e3a8a 100%);color:white;
         display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px}
.ol-company{font-family:'DM Serif Display',serif;font-size:26px;letter-spacing:.5px}
.ol-company span{font-family:'DM Sans',sans-serif;font-size:12px;color:rgba(255,255,255,.6);display:block;margin-top:4px;letter-spacing:2px;text-transform:uppercase}
.ol-meta{text-align:right;font-size:12px;color:rgba(255,255,255,.7);line-height:1.8}
.ol-meta b{color:white;font-size:13px}

/* Sub header stripe */
.ol-stripe{background:#f0f7ff;border-top:3px solid #3b82f6;border-bottom:3px solid #e2e8f0;
           padding:14px 52px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.ol-stripe .tag{font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:1px}
.ol-stripe .offer-no{font-size:12px;color:#64748b}

/* Body */
.ol-body{padding:40px 52px}
.ol-date{font-size:13px;color:#64748b;margin-bottom:24px}
.ol-to{font-size:13px;color:#374151;line-height:1.8;margin-bottom:24px;
       padding:16px 20px;background:#f8fafc;border-left:4px solid #3b82f6;border-radius:4px}
.ol-to .name{font-size:17px;font-weight:700;color:#0f1e46;margin-bottom:4px}
.ol-subject{font-size:16px;font-weight:700;color:#0f1e46;margin-bottom:18px;
            padding-bottom:12px;border-bottom:2px solid #e2e8f0}
.ol-salute{font-size:14px;color:#374151;margin-bottom:16px}
.ol-para{font-size:13.5px;color:#374151;line-height:1.85;margin-bottom:16px}

/* Details table */
.details-box{background:linear-gradient(135deg,#f0f7ff,#eff6ff);border:1.5px solid #bfdbfe;
             border-radius:12px;padding:24px 28px;margin:24px 0;display:grid;
             grid-template-columns:1fr 1fr;gap:18px}
.det-item .lbl{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
.det-item .val{font-size:14px;font-weight:700;color:#0f1e46}
.det-item.full{grid-column:1/-1}

/* Acceptance section */
.accept-box{background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;
            padding:16px 20px;margin:20px 0;font-size:13px;color:#166534;line-height:1.7}
.accept-box b{color:#16a34a}

/* Signature */
.ol-sign{margin-top:36px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:30px}
.sign-block{min-width:180px}
.sign-name{font-family:'DM Serif Display',serif;font-size:22px;color:#0f1e46;
           border-bottom:2px solid #0f1e46;padding-bottom:4px;margin-bottom:8px;
           display:inline-block;min-width:160px}
.sign-title{font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.sign-company{font-size:12px;color:#374151;margin-top:3px}
.cand-sign{text-align:right}
.sign-line{width:180px;border-bottom:1.5px dashed #94a3b8;margin-bottom:8px;height:32px;
           display:flex;align-items:flex-end;justify-content:flex-end;padding-bottom:4px;
           font-size:13px;color:#94a3b8}

/* Footer */
.ol-footer{background:#0f1e46;color:rgba(255,255,255,.6);font-size:11px;text-align:center;
           padding:14px 52px;border-top:4px solid #1e3a8a;letter-spacing:.3px}
</style>
</head>
<body>

<div class="sidebar">
    <h2>User Panel</h2>
    <a href="index.php">Dashboard</a>
    <a href="my_applications.php">My Applications</a>
    <a href="my_tests.php">My Tests</a>
    <a href="offer_letter.php" class="active">🎉 Offer Letter</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="wrap">

    <div class="topbar">
        <div>
            <h1>🎉 Your Offer Letter</h1>
            <div class="sub">Congratulations! You have been selected.</div>
        </div>
        <button class="dl-btn" id="dlBtn" onclick="downloadPDF()">
            📥 Download PDF
        </button>
    </div>

    <!-- ── OFFER LETTER ─────────────────────────────────────────── -->
    <div id="offer-letter">
        <div class="ol-topbar"></div>

        <!-- Header -->
        <div class="ol-head">
            <div>
                <div class="ol-company">
                    <?= htmlspecialchars($company) ?>
                    <span>Official Offer Letter</span>
                </div>
            </div>
            <div class="ol-meta">
                <div>Date: <b><?= $sent_date ?></b></div>
                <div>Ref No: <b><?= $offer_id ?></b></div>
                <div>Status: <b style="color:#4ade80">✅ Confirmed</b></div>
            </div>
        </div>

        <!-- Stripe -->
        <div class="ol-stripe">
            <span class="tag">🏆 Appointment Letter / Offer of Employment</span>
            <span class="offer-no">Offer ID: <?= $offer_id ?></span>
        </div>

        <!-- Body -->
        <div class="ol-body">
            <div class="ol-date"><?= $sent_date ?></div>

            <div class="ol-to">
                <div class="name"><?= htmlspecialchars($offer['candidate_name']) ?></div>
                <div><?= htmlspecialchars($offer['candidate_email']) ?></div>
            </div>

            <div class="ol-subject">
                Subject: Offer of Employment — <?= htmlspecialchars($offer['job_title']) ?>
            </div>

            <p class="ol-salute">Dear <b><?= htmlspecialchars(explode(' ', $offer['candidate_name'])[0]) ?></b>,</p>

            <?php if (!empty($offer['message'])): ?>
            <p class="ol-para"><?= nl2br(htmlspecialchars($offer['message'])) ?></p>
            <?php else: ?>
            <p class="ol-para">
                We are delighted to extend this offer of employment to you at
                <b><?= htmlspecialchars($company) ?></b>. After careful review of your qualifications and
                your performance during the interview process, we are pleased to welcome you to our team.
            </p>
            <p class="ol-para">
                Please review the details of your offer below. Kindly confirm your acceptance
                by your joining date. We look forward to having you join our organization.
            </p>
            <?php endif; ?>

            <!-- Details box -->
            <div class="details-box">
                <div class="det-item">
                    <div class="lbl">👤 Candidate Name</div>
                    <div class="val"><?= htmlspecialchars($offer['candidate_name']) ?></div>
                </div>
                <div class="det-item">
                    <div class="lbl">💼 Designation / Role</div>
                    <div class="val"><?= htmlspecialchars($offer['job_title']) ?></div>
                </div>
                <div class="det-item">
                    <div class="lbl">📍 Location</div>
                    <div class="val"><?= htmlspecialchars($offer['job_city'] ?? 'Head Office') ?></div>
                </div>
                <div class="det-item">
                    <div class="lbl">💰 Offered Salary</div>
                    <div class="val"><?= !empty($offer['salary']) ? '₹ ' . htmlspecialchars($offer['salary']) : 'As discussed' ?></div>
                </div>
                <div class="det-item">
                    <div class="lbl">📅 Date of Joining</div>
                    <div class="val" style="color:#16a34a;font-size:15px"><?= $join_date ?></div>
                </div>
                <div class="det-item">
                    <div class="lbl">📄 Offer Date</div>
                    <div class="val"><?= $sent_date ?></div>
                </div>
                <div class="det-item full">
                    <div class="lbl">🏢 Company</div>
                    <div class="val"><?= htmlspecialchars($company) ?></div>
                </div>
            </div>

            <div class="accept-box">
                ✅ <b>Please note:</b> This offer is valid for 7 days from the date of issue.
                Kindly report to our office on your joining date: <b><?= $join_date ?></b>.
                Please bring all original documents for verification on your first day.
            </div>

            <p class="ol-para">
                We are excited about the prospect of you joining our team and contributing to our
                continued growth. Should you have any questions, please do not hesitate to reach out.
            </p>

            <p class="ol-para">Congratulations and welcome aboard! 🎉</p>

            <!-- Signature -->
            <div class="ol-sign">
                <div class="sign-block">
                    <div class="sign-name"><?= htmlspecialchars($offer['signature_name'] ?? 'HR Manager') ?></div>
                    <div class="sign-title">HR Manager / Authorized Signatory</div>
                    <div class="sign-company"><?= htmlspecialchars($company) ?></div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:6px"><?= $sent_date ?></div>
                </div>
                <div class="sign-block cand-sign">
                    <div class="lbl" style="font-size:11px;color:#94a3b8;margin-bottom:6px;text-align:right">CANDIDATE ACCEPTANCE</div>
                    <div class="sign-line">Signature</div>
                    <div class="sign-title" style="text-align:right"><?= htmlspecialchars($offer['candidate_name']) ?></div>
                    <div style="font-size:11px;color:#94a3b8;text-align:right;margin-top:3px">Date: _______________</div>
                </div>
            </div>
        </div>

        <div class="ol-footer">
            <?= htmlspecialchars($company) ?> &nbsp;|&nbsp;
            This is a computer-generated offer letter. &nbsp;|&nbsp;
            Ref: <?= $offer_id ?> &nbsp;|&nbsp; <?= $sent_date ?>
        </div>
    </div>
    <br><br>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
async function downloadPDF() {
    const btn = document.getElementById('dlBtn');
    btn.textContent = '⏳ Generating...';
    btn.disabled = true;

    await new Promise(r => setTimeout(r, 300));

    try {
        const { jsPDF } = window.jspdf;
        const el = document.getElementById('offer-letter');

        const canvas = await html2canvas(el, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            width: el.offsetWidth,
            height: el.offsetHeight
        });

        const pdf   = new jsPDF('p', 'mm', 'a4');
        const pW    = pdf.internal.pageSize.getWidth();
        const pH    = pdf.internal.pageSize.getHeight();
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const imgH    = (canvas.height / canvas.width) * pW;

        if (imgH <= pH) {
            pdf.addImage(imgData, 'JPEG', 0, 0, pW, imgH);
        } else {
            let yPos = 0;
            while (yPos < imgH) {
                if (yPos > 0) pdf.addPage();
                pdf.addImage(imgData, 'JPEG', 0, -yPos, pW, imgH);
                yPos += pH;
            }
        }

        pdf.save('Offer_Letter_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $offer['candidate_name']) ?>_<?= $offer_id ?>.pdf');
    } catch(e) {
        alert('Download failed. Please try again.');
        console.error(e);
    }
    btn.textContent = '📥 Download PDF';
    btn.disabled = false;
}
</script>
</body>
</html>
