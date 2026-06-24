<?php
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorise']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string)$rawInput, true);
$text = trim((string)($payload['text'] ?? ''));

if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Texte vide']);
    exit;
}

if (mb_strlen($text) > 12000) {
    http_response_code(400);
    echo json_encode(['error' => 'Texte trop long']);
    exit;
}

$apiKey = trim((string)(getenv('GROQ_API_KEY') ?: ''));
$model = trim((string)(getenv('GROQ_MODEL') ?: 'llama-3.1-8b-instant'));

if ($apiKey === '' || $apiKey === 'CHANGE_ME') {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration IA manquante (GROQ_API_KEY).']);
    exit;
}

$systemPrompt = "Tu es un expert support B2B. Tu dois STRICTEMENT recrire le texte fourni de maniere professionnelle, sans fautes, d'un ton neutre et clair. IMPORTANT: Tu dois UNIQUEMENT renvoyer le texte reformule. Interdiction absolue d'ajouter des formules de politesse de type 'Voici le texte' ou 'Bonjour comment puis-je vous aider'.";
$userPrompt = "Voici le rapport du technicien a reformuler :\n" . $text;

$requestBody = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ],
    'temperature' => 0.3
];

$endpoint = 'https://api.groq.com/openai/v1/chat/completions';

$responseBody = null;
$httpCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_TIMEOUT => 30,
    ]);

    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        http_response_code(502);
        echo json_encode(['error' => 'Erreur reseau IA: ' . $curlError]);
        exit;
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\n",
            'content' => json_encode($requestBody),
            'timeout' => 30,
            'ignore_errors' => true,
        ]
    ]);

    $responseBody = file_get_contents($endpoint, false, $context);
    if ($responseBody === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Erreur reseau IA']);
        exit;
    }

    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
}

$data = json_decode((string)$responseBody, true);

if ($httpCode < 200 || $httpCode >= 300) {
    $message = $data['error']['message'] ?? 'Erreur API IA';
    http_response_code(502);
    echo json_encode(['error' => $message]);
    exit;
}

$rewrittenText = trim((string)($data['choices'][0]['message']['content'] ?? ''));
if ($rewrittenText === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Reponse IA invalide']);
    exit;
}

echo json_encode([
    'success' => true,
    'rewritten_text' => $rewrittenText
]);
