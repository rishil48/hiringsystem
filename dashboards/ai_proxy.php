<?php
// ============================================================
// dashboards/ai_proxy.php
// ============================================================
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$GEMINI_API_KEY = "AIzaSyDAv7Fx0xaLfjLyHk66WQQMVjwVgiLav7Y";

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$topic = trim($input['topic'] ?? '');
$type  = trim($input['type']  ?? 'mcq');

if (empty($topic)) {
    echo json_encode(['error' => 'Topic required hai']);
    exit();
}

// Very strict prompt - sirf JSON array return karo
$prompt = 'You are a JSON generator. Generate exactly 5 MCQ questions about "' . addslashes($topic) . '".

Respond with ONLY this exact JSON format, nothing else, no explanation:

[
{"question":"sample question 1?","option_a":"Answer A","option_b":"Answer B","option_c":"Answer C","option_d":"Answer D","correct_option":"a"},
{"question":"sample question 2?","option_a":"Answer A","option_b":"Answer B","option_c":"Answer C","option_d":"Answer D","correct_option":"b"},
{"question":"sample question 3?","option_a":"Answer A","option_b":"Answer B","option_c":"Answer C","option_d":"Answer D","correct_option":"c"},
{"question":"sample question 4?","option_a":"Answer A","option_b":"Answer B","option_c":"Answer C","option_d":"Answer D","correct_option":"d"},
{"question":"sample question 5?","option_a":"Answer A","option_b":"Answer B","option_c":"Answer C","option_d":"Answer D","correct_option":"a"}
]

Replace sample questions with real questions about "' . addslashes($topic) . '". correct_option must be a, b, c, or d only.';

$models = [
    'gemini-2.5-flash',
    'gemini-2.0-flash',
    'gemini-2.0-flash-001',
    'gemini-2.0-flash-lite',
];

$response   = null;
$http_code  = 0;
$curl_err   = '';
$used_model = '';

foreach ($models as $model) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $GEMINI_API_KEY;

    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature'      => 0.3,   // kam temperature = zyada consistent JSON
            'maxOutputTokens'  => 2000,
            'responseMimeType' => 'application/json',  // ✅ Force JSON response
        ]
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200) {
        $used_model = $model;
        break;
    }
}

if ($curl_err) {
    echo json_encode(['error' => 'Internet error: ' . $curl_err]);
    exit();
}

$result = json_decode($response, true);

if ($http_code !== 200) {
    $msg = $result['error']['message'] ?? 'Unknown error';
    echo json_encode(['error' => '❌ Error (' . $http_code . '): ' . $msg]);
    exit();
}

// Text nikalo
$text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($text)) {
    echo json_encode(['error' => 'AI se khaali response aaya. Dobara try karo.']);
    exit();
}

// ── Aggressive JSON cleaning ──────────────────────────────────────────────

$clean = $text;

// Step 1: markdown fences hatao
$clean = preg_replace('/```json/i', '', $clean);
$clean = preg_replace('/```/',      '', $clean);

// Step 2: trim whitespace
$clean = trim($clean);

// Step 3: seedha parse try karo
$questions = json_decode($clean, true);

// Step 4: agar fail - [ se ] tak extract karo
if (json_last_error() !== JSON_ERROR_NONE) {
    $start = strpos($clean, '[');
    $end   = strrpos($clean, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $extracted = substr($clean, $start, $end - $start + 1);
        $questions = json_decode($extracted, true);
    }
}

// Step 5: agar abhi bhi fail - JSON repair try karo
if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
    // Trailing commas hatao (common Gemini mistake)
    $repaired = preg_replace('/,\s*([\]}])/', '$1', $clean);
    $start = strpos($repaired, '[');
    $end   = strrpos($repaired, ']');
    if ($start !== false && $end !== false) {
        $questions = json_decode(substr($repaired, $start, $end - $start + 1), true);
    }
}

// Agar sab fail ho jaye - debug info bhejo
if (!$questions || !is_array($questions) || count($questions) === 0) {
    echo json_encode([
        'error' => 'JSON parse fail. Raw AI response dekho.',
        'raw'   => substr($text, 0, 500),
        'model' => $used_model,
    ]);
    exit();
}

// Validate + clean karo
$valid = [];
foreach ($questions as $q) {
    // Keys normalize karo (lowercase)
    $q = array_change_key_case($q, CASE_LOWER);

    // correct_option ke alag alag naam handle karo
    if (!isset($q['correct_option']) && isset($q['answer']))        $q['correct_option'] = $q['answer'];
    if (!isset($q['correct_option']) && isset($q['correct']))       $q['correct_option'] = $q['correct'];
    if (!isset($q['correct_option']) && isset($q['correctoption'])) $q['correct_option'] = $q['correctoption'];

    // option keys normalize karo
    if (!isset($q['option_a']) && isset($q['a'])) $q['option_a'] = $q['a'];
    if (!isset($q['option_b']) && isset($q['b'])) $q['option_b'] = $q['b'];
    if (!isset($q['option_c']) && isset($q['c'])) $q['option_c'] = $q['c'];
    if (!isset($q['option_d']) && isset($q['d'])) $q['option_d'] = $q['d'];

    // correct_option se sirf a/b/c/d nikalo
    $co = strtolower(trim($q['correct_option'] ?? ''));
    $co = preg_replace('/[^abcd]/', '', $co);  // sirf a b c d rakhho
    if (strlen($co) > 1) $co = $co[0];         // pehla character lo

    if (
        !empty($q['question']) &&
        !empty($q['option_a']) &&
        !empty($q['option_b']) &&
        !empty($q['option_c']) &&
        !empty($q['option_d']) &&
        in_array($co, ['a','b','c','d'])
    ) {
        $valid[] = [
            'question'       => $q['question'],
            'option_a'       => $q['option_a'],
            'option_b'       => $q['option_b'],
            'option_c'       => $q['option_c'],
            'option_d'       => $q['option_d'],
            'correct_option' => $co,
        ];
    }
}

if (empty($valid)) {
    echo json_encode([
        'error' => 'Validation fail. Raw response:',
        'raw'   => substr($text, 0, 500),
        'model' => $used_model,
    ]);
    exit();
}

echo json_encode([
    'success'   => true,
    'questions' => $valid,
    'model'     => $used_model,
    'count'     => count($valid),
]);
