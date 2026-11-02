<?php
// models.php - TEMPORARY helper. Lists the model names your API key can use.
// Open it in the browser once, copy a name, then DELETE this file.
header('Content-Type: text/plain');

const GEMINI_KEY = 'KUHA KAYO SARILI NYONG API KEY SA GEMINI';

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=100');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['x-goog-api-key: ' . GEMINI_KEY],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$response = curl_exec($ch);
if ($response === false) {
    die('Connection problem: ' . curl_error($ch));   // SSL error 60 -> fix cacert.pem in php.ini
}

$data = json_decode($response, true);

if (empty($data['models'])) {
    die($response);   // shows Google's error message (e.g. bad key)
}

// Only show models that can answer chat questions (generateContent)
foreach ($data['models'] as $m) {
    if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [])) {
        echo str_replace('models/', '', $m['name']) . "\n";   // this is the name you need
    }
}
