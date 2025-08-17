<?php
header('Content-Type: text/html; charset=utf-8');

$configPath = __DIR__ . '/config/config.php';
if (!file_exists($configPath)) {
    exit('Конфиг не найден: config/config.php');
}

$config = require $configPath;

if (!isset($_GET['code'])) {
    exit('Ошибка: параметр `code` отсутствует в URL');
}

$code = $_GET['code'];

$data = [
    'client_id'     => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $config['redirect_uri'],
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $config['base_domain'] . '/oauth2/access_token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($http_code !== 200) {
    exit("Ошибка при получении токена (HTTP $http_code):<br><pre>$response</pre>");
}

$tokens = json_decode($response, true);

$tokenPath = __DIR__ . '/config/token.json';
$writeSuccess = @file_put_contents($tokenPath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if (!$writeSuccess) {
    echo "Токен получен, но не удалось сохранить в <code>config/token.json</code><br><pre>";
    print_r($tokens);
    echo "</pre>";
} else {
    echo "Токен сохранён в <code>config/token.json</code>";
}
