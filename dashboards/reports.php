<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php"); exit();
}

$users        = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
$hrs          = $conn->query("SELECT COUNT(*) c FROM users WHERE role='hr'")->fetch_assoc()['c'];
$jobs         = $conn->query("SELECT COUNT(*) c FROM jobs")->fetch_assoc()['c'];
$applications = $conn->query("SELECT COUNT(*) c FROM applications")->fetch_assoc()['c'];
$app_pending  = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='pending'")->fetch_assoc()['c'];
$app_accept   = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='accepted'")->fetch_assoc()['c'];
$app_reject   = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='rejected'")->fetch_assoc()['c'];

$rounds_total   = $conn->query("SELECT COUNT(*) c FROM interview_rounds")->fetch_assoc()['c'];
$rounds_pending = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE LOWER(status)='pending'")->fetch_assoc()['c'];
$rounds_ongoing = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE LOWER(status)='ongoing'")->fetch_assoc()['c'];
$rounds_done    = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE LOWER(status)='completed'")->fetch_assoc()['c'];
$rounds_pass    = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE result='pass'")->fetch_assoc()['c'];
$rounds_fail    = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE result='fail'")->fetch_assoc()['c'];
$mcq_rounds     = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE LOWER(round_type) IN ('ai_mcq','')")->fetch_assoc()['c'];
$tech_rounds    = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE LOWER(round_type)='technical'")->fetch_assoc()['c'];
$video_rounds   = $conn->query("SELECT COUNT(*) c FROM interview_rounds WHERE LOWER(round_type) IN ('hr','video_call','video')")->fetch_assoc()['c'];

$monthly = $conn->query("
    SELECT DATE_FORMAT(applied_at,'%b %Y') AS month, COUNT(*) AS cnt
    FROM applications
    WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(applied_at,'%Y-%m')
    ORDER BY MIN(applied_at) ASC
");
$months = []; $month_data = [];
while ($m = $monthly->fetch_assoc()) { $months[] = $m['month']; $month_data[] = $m['cnt']; }

$top_jobs = $conn->query("
    SELECT j.title, COUNT(a.id) AS cnt
    FROM jobs j LEFT JOIN applications a ON j.id = a.job_id
    GROUP BY j.id ORDER BY cnt DESC LIMIT 5
");
$tj_labels = []; $tj_data = [];
while ($tj = $top_jobs->fetch_assoc()) { $tj_labels[] = $tj['title']; $tj_data[] = $tj['cnt']; }

$today = date('d M Y');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Reports</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',Arial,sans-serif;background:#0f172a;color:white;display:flex;min-height:100vh}

.sidebar{width:220px;min-height:100vh;background:#0b1532;position:fixed;top:0;left:0;padding-top:24px;z-index:100}
.sidebar h2{text-align:center;color:white;padding:0 12px 20px;font-size:17px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar a{display:block;padding:13px 22px;color:#94a3b8;text-decoration:none;font-size:14px}
.sidebar a:hover,.sidebar a.active{background:#1e3a8a;color:white}

.main{margin-left:220px;padding:32px;flex:1}

/* Header */
.top-bar{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.top-bar h1{font-size:26px;font-weight:900}
.top-bar .meta{font-size:13px;color:#64748b;margin-top:4px}
.dl-btn{background:linear-gradient(135deg,#06b6d4,#0284c7);color:white;border:none;
        padding:12px 24px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;
        display:flex;align-items:center;gap:8px;transition:.2s;white-space:nowrap}
.dl-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(6,182,212,.4)}
.dl-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* Section label */
.sec{font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;
     margin:28px 0 14px;display:flex;align-items:center;gap:10px}
.sec::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}

/* Stat grid */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:4px}
.sc{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:14px;
    padding:18px 14px;text-align:center;transition:.2s}
.sc:hover{background:rgba(255,255,255,.09);transform:translateY(-2px)}
.sc .ico{font-size:24px;margin-bottom:8px}
.sc .num{font-size:30px;font-weight:900;line-height:1;margin-bottom:4px}
.sc .lbl{font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.c1{color:#22d3ee}.c2{color:#60a5fa}.c3{color:#a78bfa}.c4{color:#fbbf24}
.c5{color:#4ade80}.c6{color:#f87171}.c7{color:#2dd4bf}.c8{color:#fb923c}

/* Chart grid — KEY FIX: fixed heights, no stretching */
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.chart-grid.one{grid-template-columns:1fr}
.cbox{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px}
.cbox h4{font-size:13px;color:#cbd5e1;font-weight:700;margin-bottom:14px}
/* CRITICAL: Fixed canvas sizes prevent PDF clipping */
.cbox canvas{display:block;width:100% !important;height:220px !important}

/* Summary table */
.tbl-wrap{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:22px;margin-top:16px}
.tbl-wrap h4{font-size:13px;color:#cbd5e1;font-weight:700;margin-bottom:16px}
table{width:100%;border-collapse:collapse}
th{padding:9px 14px;text-align:left;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(255,255,255,.07)}
td{padding:11px 14px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);color:#e2e8f0}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.03)}
.tbg{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block}
.bg-blue{background:rgba(96,165,250,.15);color:#60a5fa}
.bg-green{background:rgba(74,222,128,.15);color:#4ade80}
.bg-red{background:rgba(248,113,113,.15);color:#f87171}
.bg-orange{background:rgba(251,191,36,.15);color:#fbbf24}
.bg-purple{background:rgba(167,139,250,.15);color:#a78bfa}
.bg-teal{background:rgba(45,212,191,.15);color:#2dd4bf}
</style>
</head>
<body>

<div class="sidebar">
    <h2>⚙️ Admin Panel</h2>
    <a href="admin.php">🏠 Dashboard</a>
    <a href="admin.php?page=users">👥 Users</a>
    <a href="admin.php?page=jobs">💼 Jobs</a>
    <a href="report.php" class="active">📊 Reports</a>
    <a href="../auth/logout.php">🚪 Logout</a>
</div>

<div class="main">

    <div class="top-bar">
        <div>
            <h1>📊 System Reports</h1>
            <div class="meta">Generated: <?= $today ?> &nbsp;|&nbsp; Hiring Management System</div>
        </div>
        <button class="dl-btn" id="dlBtn" onclick="downloadPDF()">📥 Download PDF</button>
    </div>

    <!-- ── Print/PDF wrapper — only this gets captured ── -->
    <div id="pdf-area">

    <div class="sec">👥 Users &amp; Jobs</div>
    <div class="stat-grid">
        <div class="sc"><div class="ico">👤</div><div class="num c1"><?= $users ?></div><div class="lbl">Candidates</div></div>
        <div class="sc"><div class="ico">🧑‍💼</div><div class="num c2"><?= $hrs ?></div><div class="lbl">HR Managers</div></div>
        <div class="sc"><div class="ico">💼</div><div class="num c3"><?= $jobs ?></div><div class="lbl">Jobs Posted</div></div>
        <div class="sc"><div class="ico">📄</div><div class="num c4"><?= $applications ?></div><div class="lbl">Applications</div></div>
        <div class="sc"><div class="ico">⏳</div><div class="num c4"><?= $app_pending ?></div><div class="lbl">Pending</div></div>
        <div class="sc"><div class="ico">✅</div><div class="num c5"><?= $app_accept ?></div><div class="lbl">Accepted</div></div>
        <div class="sc"><div class="ico">❌</div><div class="num c6"><?= $app_reject ?></div><div class="lbl">Rejected</div></div>
    </div>

    <div class="sec">🎯 Interview Rounds</div>
    <div class="stat-grid">
        <div class="sc"><div class="ico">📋</div><div class="num c3"><?= $rounds_total ?></div><div class="lbl">Total</div></div>
        <div class="sc"><div class="ico">⏳</div><div class="num c4"><?= $rounds_pending ?></div><div class="lbl">Pending</div></div>
        <div class="sc"><div class="ico">🔄</div><div class="num c2"><?= $rounds_ongoing ?></div><div class="lbl">Ongoing</div></div>
        <div class="sc"><div class="ico">✅</div><div class="num c7"><?= $rounds_done ?></div><div class="lbl">Completed</div></div>
        <div class="sc"><div class="ico">🏆</div><div class="num c5"><?= $rounds_pass ?></div><div class="lbl">Passed</div></div>
        <div class="sc"><div class="ico">❌</div><div class="num c6"><?= $rounds_fail ?></div><div class="lbl">Failed</div></div>
        <div class="sc"><div class="ico">🤖</div><div class="num c3"><?= $mcq_rounds ?></div><div class="lbl">MCQ</div></div>
        <div class="sc"><div class="ico">💻</div><div class="num c8"><?= $tech_rounds ?></div><div class="lbl">Technical</div></div>
        <div class="sc"><div class="ico">📹</div><div class="num c7"><?= $video_rounds ?></div><div class="lbl">Video/HR</div></div>
    </div>

    <div class="sec">📈 Charts</div>

    <!-- Row 1 -->
    <div class="chart-grid">
        <div class="cbox"><h4>📊 Users, HR &amp; Jobs</h4><canvas id="c1"></canvas></div>
        <div class="cbox"><h4>📄 Application Status</h4><canvas id="c2"></canvas></div>
    </div>
    <!-- Row 2 -->
    <div class="chart-grid">
        <div class="cbox"><h4>🎯 Round Status</h4><canvas id="c3"></canvas></div>
        <div class="cbox"><h4>🏆 Pass vs Fail</h4><canvas id="c4"></canvas></div>
    </div>
    <!-- Row 3 -->
    <div class="chart-grid">
        <div class="cbox"><h4>📈 Monthly Applications</h4><canvas id="c5"></canvas></div>
        <div class="cbox"><h4>💼 Top Jobs</h4><canvas id="c6"></canvas></div>
    </div>
    <!-- Row 4 -->
    <div class="chart-grid">
        <div class="cbox"><h4>🤖 Round Types</h4><canvas id="c7"></canvas></div>
        <div class="cbox"><h4>📊 System Radar</h4><canvas id="c8"></canvas></div>
    </div>

    <!-- Summary Table -->
    <div class="tbl-wrap">
        <h4>📋 Full Summary</h4>
        <table>
            <thead><tr><th>Category</th><th>Metric</th><th>Count</th><th>Status</th></tr></thead>
            <tbody>
            <tr><td>👥 Users</td><td>Candidates</td><td><b><?= $users ?></b></td><td><span class="tbg bg-blue">Active</span></td></tr>
            <tr><td>👥 Users</td><td>HR Managers</td><td><b><?= $hrs ?></b></td><td><span class="tbg bg-blue">Active</span></td></tr>
            <tr><td>💼 Jobs</td><td>Total Posted</td><td><b><?= $jobs ?></b></td><td><span class="tbg bg-purple">Live</span></td></tr>
            <tr><td>📄 Apps</td><td>Total</td><td><b><?= $applications ?></b></td><td><span class="tbg bg-orange">All</span></td></tr>
            <tr><td>📄 Apps</td><td>Pending</td><td><b><?= $app_pending ?></b></td><td><span class="tbg bg-orange">Pending</span></td></tr>
            <tr><td>📄 Apps</td><td>Accepted</td><td><b><?= $app_accept ?></b></td><td><span class="tbg bg-green">✅ Yes</span></td></tr>
            <tr><td>📄 Apps</td><td>Rejected</td><td><b><?= $app_reject ?></b></td><td><span class="tbg bg-red">❌ No</span></td></tr>
            <tr><td>🎯 Rounds</td><td>Total</td><td><b><?= $rounds_total ?></b></td><td><span class="tbg bg-purple">All</span></td></tr>
            <tr><td>🎯 Rounds</td><td>Completed</td><td><b><?= $rounds_done ?></b></td><td><span class="tbg bg-teal">Done</span></td></tr>
            <tr><td>🎯 Rounds</td><td>Passed</td><td><b><?= $rounds_pass ?></b></td><td><span class="tbg bg-green">🏆 Pass</span></td></tr>
            <tr><td>🎯 Rounds</td><td>Failed</td><td><b><?= $rounds_fail ?></b></td><td><span class="tbg bg-red">❌ Fail</span></td></tr>
            <tr><td>🤖 Types</td><td>MCQ</td><td><b><?= $mcq_rounds ?></b></td><td><span class="tbg bg-purple">MCQ</span></td></tr>
            <tr><td>💻 Types</td><td>Technical</td><td><b><?= $tech_rounds ?></b></td><td><span class="tbg bg-orange">Tech</span></td></tr>
            <tr><td>📹 Types</td><td>Video/HR</td><td><b><?= $video_rounds ?></b></td><td><span class="tbg bg-teal">Video</span></td></tr>
            </tbody>
        </table>
    </div>

    </div><!-- #pdf-area -->
</div>

<script>
Chart.defaults.color = 'rgba(255,255,255,.55)';
Chart.defaults.borderColor = 'rgba(255,255,255,.06)';

const C = { cyan:'#22d3ee',blue:'#60a5fa',purple:'#a78bfa',green:'#4ade80',
            red:'#f87171',orange:'#fbbf24',teal:'#2dd4bf',indigo:'#818cf8' };

function bar(id, labels, data, colors) {
    new Chart(id, { type:'bar', data:{
        labels, datasets:[{data, backgroundColor:colors, borderRadius:6, borderSkipped:false}]
    }, options:{ responsive:true, maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{x:{ticks:{font:{size:11}}},y:{ticks:{font:{size:11}}}} }});
}
function doughnut(id, labels, data, colors) {
    new Chart(id, { type:'doughnut', data:{
        labels, datasets:[{data, backgroundColor:colors, borderWidth:2, borderColor:'#0f172a', hoverOffset:8}]
    }, options:{ responsive:true, maintainAspectRatio:false, cutout:'60%',
        plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:12}}} }});
}
function pie(id, labels, data, colors) {
    new Chart(id, { type:'pie', data:{
        labels, datasets:[{data, backgroundColor:colors, borderWidth:2, borderColor:'#0f172a', hoverOffset:8}]
    }, options:{ responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:12}}} }});
}

// Chart 1 — Users bar
bar('c1', ['Candidates','HR Managers','Jobs Posted'],
    [<?= $users ?>,<?= $hrs ?>,<?= $jobs ?>],
    [C.cyan, C.blue, C.purple]);

// Chart 2 — Applications doughnut
doughnut('c2', ['Pending','Accepted','Rejected'],
    [<?= $app_pending ?>,<?= $app_accept ?>,<?= $app_reject ?>],
    [C.orange, C.green, C.red]);

// Chart 3 — Round status bar
bar('c3', ['Pending','Ongoing','Completed'],
    [<?= $rounds_pending ?>,<?= $rounds_ongoing ?>,<?= $rounds_done ?>],
    [C.orange, C.blue, C.teal]);

// Chart 4 — Pass/Fail pie
pie('c4', ['Passed','Failed','Pending'],
    [<?= $rounds_pass ?>,<?= $rounds_fail ?>,<?= $rounds_total - $rounds_pass - $rounds_fail ?>],
    [C.green, C.red, C.indigo]);

// Chart 5 — Monthly line
new Chart('c5', { type:'line', data:{
    labels: <?= json_encode($months ?: ['No Data']) ?>,
    datasets:[{ label:'Applications',
        data: <?= json_encode($month_data ?: [0]) ?>,
        borderColor:C.cyan, backgroundColor:'rgba(34,211,238,.1)',
        fill:true, tension:.4, pointRadius:5, pointBackgroundColor:C.cyan }]
}, options:{ responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:{ticks:{font:{size:11}}},y:{ticks:{font:{size:11}},beginAtZero:true}} }});

// Chart 6 — Top jobs horizontal bar
new Chart('c6', { type:'bar', data:{
    labels: <?= json_encode($tj_labels ?: ['No Jobs']) ?>,
    datasets:[{ data: <?= json_encode($tj_data ?: [0]) ?>,
        backgroundColor:[C.purple,C.blue,C.cyan,C.teal,C.indigo], borderRadius:5 }]
}, options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:{ticks:{font:{size:11}}},y:{ticks:{font:{size:11}}}} }});

// Chart 7 — Round types doughnut
doughnut('c7', ['AI MCQ','Technical','Video/HR'],
    [<?= $mcq_rounds ?>,<?= $tech_rounds ?>,<?= $video_rounds ?>],
    [C.purple, C.orange, C.teal]);

// Chart 8 — Radar
new Chart('c8', { type:'radar', data:{
    labels:['Candidates','HR','Jobs','Apps','Completed','Passed'],
    datasets:[{ label:'Stats',
        data:[<?= $users ?>,<?= $hrs ?>,<?= $jobs ?>,<?= $applications ?>,<?= $rounds_done ?>,<?= $rounds_pass ?>],
        backgroundColor:'rgba(129,140,248,.15)', borderColor:C.indigo,
        pointBackgroundColor:C.indigo, pointRadius:4 }]
}, options:{ responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{ r:{ ticks:{backdropColor:'transparent',font:{size:10}},
                 grid:{color:'rgba(255,255,255,.07)'},
                 pointLabels:{color:'rgba(255,255,255,.6)',font:{size:11}} }} }});

// ── PDF Download — captures each chart row separately, proper pagination ──
async function downloadPDF() {
    const btn = document.getElementById('dlBtn');
    btn.textContent = '⏳ Generating...';
    btn.disabled = true;

    // Wait for charts to fully render
    await new Promise(r => setTimeout(r, 800));

    try {
        const script1 = document.createElement('script');
        script1.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        const script2 = document.createElement('script');
        script2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';

        // Load libraries dynamically if not loaded
        const loadLib = (src) => new Promise(res => {
            if (document.querySelector(`script[src="${src}"]`)?.loaded) return res();
            const s = document.createElement('script');
            s.src = src;
            s.onload = res;
            document.head.appendChild(s);
        });

        await loadLib('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
        await loadLib('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
        await new Promise(r => setTimeout(r, 300));

        const { jsPDF } = window.jspdf;
        const pdf  = new jsPDF('p', 'mm', 'a4');
        const pW   = pdf.internal.pageSize.getWidth();   // 210
        const pH   = pdf.internal.pageSize.getHeight();  // 297
        const area = document.getElementById('pdf-area');

        const canvas = await html2canvas(area, {
            scale       : 2,
            useCORS     : true,
            logging     : false,
            backgroundColor: '#0f172a',
            windowWidth : area.scrollWidth + 60,
            width       : area.scrollWidth,
            height      : area.scrollHeight,
            scrollX     : 0,
            scrollY     : 0
        });

        const imgData  = canvas.toDataURL('image/jpeg', 0.92);
        const imgW     = pW;
        const imgH     = (canvas.height / canvas.width) * imgW;
        const pageCount= Math.ceil(imgH / pH);

        for (let i = 0; i < pageCount; i++) {
            if (i > 0) pdf.addPage();
            // Clip each page properly
            pdf.addImage(imgData, 'JPEG', 0, -(i * pH), imgW, imgH);
        }

        const date = new Date().toLocaleDateString('en-GB').replace(/\//g,'-');
        pdf.save('Admin_Report_' + date + '.pdf');

    } catch(e) {
        alert('PDF generation failed: ' + e.message);
        console.error(e);
    }

    btn.textContent = '📥 Download PDF';
    btn.disabled = false;
}
</script>
</body>
</html>