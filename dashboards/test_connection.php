<?php
// C:\xampp\htdocs\hiring-system\test_connection.php
// Browser mein open karo: http://localhost/hiring-system/test_connection.php
// Test karne ke baad DELETE kar dena!

echo "<h2>🔧 Connection Test</h2><pre>";

$GEMINI_API_KEY = "AIzaSyDAv7Fx0xaLfjLyHk66WQQMVjwVgiLav7Y";

$google_ips = [
    '142.250.185.74',
    '142.250.186.74',
    '216.58.214.74',
    '172.217.160.74',
    '74.125.24.95',
];

$working_ip = null;
foreach ($google_ips as $ip) {
    echo "Testing IP: $ip ... ";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://generativelanguage.googleapis.com/v1beta/models?key=$GEMINI_API_KEY",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_RESOLVE        => ["generativelanguage.googleapis.com:443:$ip"],
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if (empty($err) && $code === 200) {
        echo "✅ WORKS! (HTTP $code)\n";
        $working_ip = $ip;
        break;
    } else {
        echo "❌ Failed (HTTP $code, Error: $err)\n";
    }
}

echo "\n";
if ($working_ip) {
    echo "🎉 SUCCESS! Working IP: $working_ip\n";
    echo "AI Generate button will work now!\n";
} else {
    echo "⚠️ ALL IPs FAILED.\n\n";
    echo "Fix steps:\n";
    echo "1. Windows key → search 'Windows Defender Firewall'\n";
    echo "2. 'Allow an app through firewall' → Add C:\\xampp\\php\\php.exe\n";
    echo "3. Restart XAMPP Apache\n";
    echo "4. Run this test again\n";
}
echo "</pre>";
