<?php
// ============================================================
// dashboards/test_models.php
// Ye file aapke API key se available models list karega
// ============================================================

$GEMINI_API_KEY = "AIzaSyDAv7Fx0xaLfjLyHk66WQQMVjwVgiLav7Y";

$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $GEMINI_API_KEY;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);
?>
<!DOCTYPE html>
<html>
<head>
<title>Gemini Models Test</title>
<style>
body{font-family:Arial;background:#f4f7fb;padding:30px}
.box{background:#fff;border-radius:10px;padding:24px;max-width:700px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.1)}
h2{color:#0b1d39;margin-bottom:16px}
.model{background:#f0fdf4;border:1px solid #86efac;border-radius:7px;padding:10px 14px;margin-bottom:8px;font-size:14px;color:#166534;font-weight:600}
.err{background:#fee2e2;border:1px solid #fecaca;border-radius:7px;padding:12px;color:#991b1b;font-size:13px}
.ok{background:#dcfce7;border:1px solid #86efac;border-radius:7px;padding:10px;color:#166534;font-weight:700;margin-bottom:14px}
</style>
</head>
<body>
<div class="box">
<h2>🔍 Aapke API Key Ke Available Models</h2>

<?php if ($curl_err): ?>
    <div class="err">❌ Connection Error: <?= $curl_err ?><br>XAMPP internet check karo.</div>
<?php elseif ($http_code !== 200): ?>
    <div class="err">❌ HTTP Error: <?= $http_code ?><br><?= htmlspecialchars($response) ?></div>
<?php else:
    $data = json_decode($response, true);
    $models = $data['models'] ?? [];
    // Only show generateContent supported models
    $generate_models = [];
    foreach ($models as $m) {
        if (isset($m['supportedGenerationMethods']) &&
            in_array('generateContent', $m['supportedGenerationMethods'])) {
            $generate_models[] = $m['name'];
        }
    }
?>
    <div class="ok">✅ <?= count($generate_models) ?> models mil gaye jo generateContent support karte hain!</div>
    <p style="font-size:13px;color:#374151;margin-bottom:12px">Neeche se koi bhi model naam copy karo aur mujhe batao:</p>
    <?php foreach ($generate_models as $name): ?>
        <div class="model">📦 <?= htmlspecialchars($name) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

</div>
</body>
</html>
