<?php
require_once __DIR__ . '/notice_facts.php';
// Local AI only. There is no database access and no remote-provider fallback here.
function shmsAIConfig() {
    $config = [
        'provider' => getenv('SHMS_AI_PROVIDER') ?: 'ollama',
        'endpoint' => getenv('SHMS_AI_ENDPOINT') ?: 'http://127.0.0.1:11434/api/chat',
        'model' => getenv('SHMS_AI_MODEL') ?: 'qwen3:4b',
        'api_key' => getenv('SHMS_AI_API_KEY') ?: '',
        'timeout' => 180,
    ];
    $local = __DIR__ . '/../config/ai.local.php';
    if (is_file($local)) {
        $overrides = require $local;
        if (!is_array($overrides)) { throw new RuntimeException('ai.local.php must return a configuration array.'); }
        $config = array_merge($config, $overrides);
    }
    return $config;
}

function shmsOllamaEndpoint($config) {
    $endpoint = $config['endpoint'] ?? '';
    // Use a literal loopback address: no DNS, redirects, proxy or LAN exposure.
    if (!is_string($endpoint) || !preg_match('~^http://127\.0\.0\.1:([0-9]{1,5})/api/chat$~D', $endpoint, $match)
        || (int)$match[1] < 1 || (int)$match[1] > 65535) {
        throw new RuntimeException('Local AI endpoint must be http://127.0.0.1:11434/api/chat (or another local port).');
    }
    $model = $config['model'] ?? '';
    if (!is_string($model) || !preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._:/-]{0,120}$~D', $model)
        || preg_match('/(?:^|[-:])cloud(?:$|[-:])/i', $model)) {
        throw new RuntimeException('Choose a downloaded local model such as qwen3:4b, not a cloud model.');
    }
    return $endpoint;
}

function shmsOllamaPayload($raw, $config) {
    if (!is_string($raw) || trim($raw) === '' || strlen($raw) > 3000) {
        throw new RuntimeException('Enter short information of no more than 3000 bytes.');
    }
    return [
        'model' => $config['model'], 'stream' => false, 'think' => false,
        'keep_alive' => '1m',
        'options' => ['temperature' => 0, 'num_ctx' => 2048, 'num_predict' => 320],
        'format' => ['type' => 'object', 'properties' => [
            'title' => ['type' => 'string'], 'content' => ['type' => 'string'],
        ], 'required' => ['title', 'content'], 'additionalProperties' => false],
        'messages' => [
            ['role' => 'system', 'content' => shmsNoticePrompt()],
            ['role' => 'user', 'content' => $raw],
        ],
    ];
}

function shmsLocalHttp($url, $payload, $timeout) {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is disabled. Enable extension=curl in XAMPP php.ini, then restart Apache.');
    }
    $ch = curl_init($url);
    $body = '';
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
        CURLOPT_WRITEFUNCTION => function ($ch, $part) use (&$body) {
            if (strlen($body) + strlen($part) > 1000000) { return 0; }
            $body .= $part; return strlen($part);
        },
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($ok === false) {
        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new RuntimeException('Local AI timed out. Close heavy apps, warm up the model in Ollama or choose a smaller model.');
        }
        throw new RuntimeException('Cannot reach local Ollama. Open Ollama on the same PC as XAMPP and try again.');
    }
    return ['status' => $status, 'body' => $body];
}

function shmsOllamaDraftResponse($response) {
    if ($response['status'] === 404) { throw new RuntimeException('Model not found. Download the configured model using ollama pull, then try again.'); }
    if ($response['status'] !== 200) { throw new RuntimeException('Ollama could not generate a notice. Check its logs and available RAM.'); }
    $data = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
    if (($data['done'] ?? false) !== true || ($data['done_reason'] ?? '') === 'length') {
        throw new RuntimeException('AI output was incomplete. Shorten the information and try again.');
    }
    $draft = json_decode($data['message']['content'] ?? '', true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($draft) || !is_string($draft['title'] ?? null) || !is_string($draft['content'] ?? null)
        || trim($draft['title']) === '' || trim($draft['content']) === ''
        || strlen($draft['title']) > 200 || strlen($draft['content']) > 12000) {
        throw new RuntimeException('AI returned an invalid notice. Try a shorter, clearer instruction.');
    }
    return ['title' => trim($draft['title']), 'content' => trim($draft['content']), 'source' => 'ollama'];
}

function shmsGenerateOllama($raw, $config, $transport = null) {
    if (($question = shmsNoticeClarification($raw)) !== null) { throw new InvalidArgumentException($question); }
    $url = shmsOllamaEndpoint($config);
    $payload = shmsOllamaPayload($raw, $config);
    $timeout = max(15, min(300, (int)($config['timeout'] ?? 180)));
    // PHP's usual 30-second request limit is too short for an 8GB CPU laptop.
    if (function_exists('set_time_limit')) { @set_time_limit($timeout + 15); }
    $transport = $transport ?? 'shmsLocalHttp';
    $deadline = microtime(true) + $timeout;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $remaining = max(1, (int)ceil($deadline - microtime(true)));
        // Service/network errors are not retried. Retry invalid model output once,
        // within the same total time budget, using only the original facts.
        $response = $transport($url, $payload, $remaining);
        try {
            $draft = shmsValidateNoticeEnglish(shmsOllamaDraftResponse($response));
            return shmsFormatNotice($raw, shmsValidateNoticeFacts($raw, $draft));
        } catch (Throwable $e) {
            if ($attempt !== 0 || $response['status'] !== 200 || $deadline - microtime(true) < 5) { throw $e; }
            $payload['messages'][0]['content'] .= ' Your previous attempt was rejected: ' . $e->getMessage() . ' Translate every clause into English and preserve all supplied dates/times. Return a fresh complete JSON notice from the original input.';
        }
    }
}

function shmsCheckOllama($config, $transport = null) {
    $url = shmsOllamaEndpoint($config);
    $transport = $transport ?? 'shmsLocalHttp';
    $result = $transport(substr($url, 0, -4) . 'tags', null, 5);
    if ($result['status'] !== 200) { throw new RuntimeException('Ollama did not return its model list. Check the local service.'); }
    $data = json_decode($result['body'], true, 32, JSON_THROW_ON_ERROR);
    $wanted = $config['model'];
    if (strpos($wanted, ':') === false) { $wanted .= ':latest'; }
    foreach ($data['models'] ?? [] as $model) {
        if (($model['name'] ?? '') === $wanted || ($model['model'] ?? '') === $wanted) {
            return 'Ollama is reachable and ' . $wanted . ' is downloaded. Generate a notice to test inference.';
        }
    }
    throw new RuntimeException('Ollama is running, but ' . $wanted . ' is not downloaded. Run: ollama pull ' . $wanted);
}
