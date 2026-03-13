<?php
// dashboards/ai_technical_proxy.php
session_start();

// ── CORS headers ─────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── Auth check ────────────────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login as HR.']);
    exit();
}

include "../config/db.php";

$GEMINI_API_KEY = "AIzaSyDAv7Fx0xaLfjLyHk66WQQMVjwVgiLav7Y";

// ── Input ─────────────────────────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
$topic = trim($input['topic'] ?? $_POST['topic'] ?? '');

if (empty($topic)) {
    echo json_encode(['success' => false, 'error' => 'Topic is required.']);
    exit();
}

// ── Prompt ────────────────────────────────────────────────────────────────
$prompt = 'Generate exactly 3 technical interview coding problems about "' . addslashes($topic) . '".
Output ONLY a valid JSON array. No markdown, no backticks, no explanation before or after.
Each object must have exactly these keys: topic, problem_statement, expected_answer.
Example format:
[{"topic":"PHP","problem_statement":"Write a function that checks if a number is prime.","expected_answer":"Loop from 2 to sqrt(n) and check divisibility."}]';

// ── Google IPs to try ─────────────────────────────────────────────────────
$google_ips = [
    '142.250.185.74',
    '142.250.186.74',
    '216.58.214.74',
    '172.217.160.74',
    '74.125.24.95',
    '142.250.80.74',
];

$models = [
    'gemini-2.0-flash',
    'gemini-2.5-flash',
    'gemini-2.0-flash-lite',
    'gemini-2.0-flash-001',
];

$response   = null;
$http_code  = 0;
$curl_err   = '';
$used_model = '';

foreach ($models as $model) {
    foreach ($google_ips as $ip) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $GEMINI_API_KEY;

        $payload = json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 2048,
            ]
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RESOLVE        => [
                "generativelanguage.googleapis.com:443:$ip",
                "generativelanguage.googleapis.com:80:$ip",
            ],
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        $response  = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if (empty($curl_err) && $http_code === 200) {
            $used_model = $model;
            break 2;
        }
    }
}

// ── Connection failed ─────────────────────────────────────────────────────
if (!empty($curl_err) || $http_code !== 200) {
    $detail = !empty($curl_err) ? $curl_err : "HTTP $http_code";
    echo json_encode([
        'success' => false,
        'error'   => "Internet connection problem ($detail). Fix: 1) Restart XAMPP Apache  2) Allow php.exe in Windows Firewall  3) Try again."
    ]);
    exit();
}

// ── Parse AI response ─────────────────────────────────────────────────────
$result = json_decode($response, true);
$text   = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($text)) {
    echo json_encode(['success' => false, 'error' => 'AI returned empty response. Try again.']);
    exit();
}

// Clean markdown
$clean = preg_replace('/```json\s*/i', '', $text);
$clean = preg_replace('/```\s*/',      '', $clean);
$clean = trim($clean);

// Extract JSON array
$start = strpos($clean, '[');
$end   = strrpos($clean, ']');

if ($start === false || $end === false) {
    echo json_encode(['success' => false, 'error' => 'AI response format invalid. Try again.']);
    exit();
}

$json_str = substr($clean, $start, $end - $start + 1);
$problems = json_decode($json_str, true);

// Try trailing comma fix
if (json_last_error() !== JSON_ERROR_NONE) {
    $fixed    = preg_replace('/,\s*([\]}])/', '$1', $json_str);
    $problems = json_decode($fixed, true);
}

if (!is_array($problems) || count($problems) === 0) {
    echo json_encode(['success' => false, 'error' => 'Could not parse problems. Try again.']);
    exit();
}

// ── Validate & return ─────────────────────────────────────────────────────
$valid = [];
foreach ($problems as $p) {
    $p    = array_change_key_case($p, CASE_LOWER);
    $prob = trim($p['problem_statement'] ?? $p['problem'] ?? $p['question'] ?? '');
    if (!empty($prob)) {
        $valid[] = [
            'topic'             => trim($p['topic'] ?? $topic),
            'problem_statement' => $prob,
            'expected_answer'   => trim($p['expected_answer'] ?? $p['solution'] ?? $p['answer'] ?? $p['hint'] ?? ''),
        ];
    }
}

if (empty($valid)) {
    echo json_encode(['success' => false, 'error' => 'No valid problems found. Try again.']);
    exit();
}

echo json_encode([
    'success'  => true,
    'problems' => $valid,
    'count'    => count($valid),
    'model'    => $used_model,
]);
