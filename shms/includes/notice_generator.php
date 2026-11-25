<?php
require_once __DIR__ . '/ollama.php';
// This service returns a draft only; it has no database write/publish capability.
function shmsFallbackNotice($raw) {
    $translated = false;
    // Only translate this exact supported phrase. Never infer time, cause or date.
    $content = "Dear Residents,\n\nPlease note the following information:\n" . $raw . "\n\nHostel Administration";
    $title = 'Hostel Announcement';
    if (preg_match('/^water supply\s+r(?:aa|a)t\s+\d{1,2}(?::[0-5]\d)?\s+baj[eay]+\s+(?:aaye|aye|aay|aa)\s+gi[.!]?$/i', trim($raw))
        && ($time = shmsNoticeNightTime($raw)) !== null) {
        $title = 'Water Supply Update';
        $translated = true;
        $content = "Dear Residents,\n\nPlease be informed that water supply will be available at $time.\n\nHostel Administration";
    }
    if (preg_match('/^(?:subha|subah|subh)\s+\d{1,2}(?::[0-5]\d)?\s+baj[eay]+\s+(?:light|bijli)\s+(?:ni|nhi|nahi|nahin)\s+hogi[.!]?$/i', trim($raw))
        && ($time = shmsNoticeMorningTime($raw)) !== null) {
        $title = 'Electricity Supply Notice';
        $translated = true;
        $content = "Please be informed that electricity will not be available at $time.";
    }
    return shmsFormatNotice($raw, ['title' => $title, 'content' => $content, 'source' => 'template', 'translated' => $translated]);
}

function shmsNoticeFailure($raw, $reason) {
    $fallback = shmsFallbackNotice($raw);
    if ($fallback['translated']) { $fallback['reason'] = $reason; return $fallback; }
    // Keep the raw text in Short information, never disguise it as a translation.
    return ['title'=>'', 'content'=>'', 'source'=>'error', 'reason'=>$reason];
}

function shmsGenerateNotice($raw, $transport = null) {
    if (($question = shmsNoticeClarification($raw)) !== null) {
        return ['title'=>'', 'content'=>'', 'source'=>'clarification', 'reason'=>$question];
    }
    try {
        $config = shmsAIConfig();
        if ($config['provider'] === 'ollama') { return shmsGenerateOllama($raw, $config, $transport); }
        if ($config['provider'] === 'remote') {
            $draft = shmsGenerateRemoteNotice($raw, $config);
            if ($draft['source'] === 'template' && !$draft['translated']) { return shmsNoticeFailure($raw, 'The remote provider did not return a valid English notice. Check its settings or retry.'); }
            return $draft;
        }
        throw new RuntimeException('Choose provider ollama or remote in the AI configuration.');
    } catch (Throwable $e) {
        return shmsNoticeFailure($raw, $e instanceof JsonException ? 'AI response was not valid notice JSON. Try again with shorter information.' : $e->getMessage());
    }
}

function shmsGenerateRemoteNotice($raw, $config) {
    $fallback = shmsFallbackNotice($raw);
    if (!$config['endpoint'] || !$config['api_key'] || !$config['model'] || !function_exists('curl_init')) {
        return $fallback;
    }
    $url = parse_url($config['endpoint']);
    if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
        return $fallback;
    }
    try {
        $payload = json_encode([
            'model' => $config['model'],
            'messages' => [
                ['role' => 'system', 'content' => shmsNoticePrompt()],
                ['role' => 'user', 'content' => $raw],
            ],
        ], JSON_THROW_ON_ERROR);
        $ch = curl_init($config['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $config['api_key']],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200 || !is_string($response) || strlen($response) > 100000) { return $fallback; }
        $data = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
        $draft = json_decode($data['choices'][0]['message']['content'] ?? '', true, 8, JSON_THROW_ON_ERROR);
        if (!is_string($draft['title'] ?? null) || !is_string($draft['content'] ?? null)
            || trim($draft['title']) === '' || trim($draft['content']) === ''
            || strlen($draft['title']) > 200 || strlen($draft['content']) > 12000) { return $fallback; }
        return shmsFormatNotice($raw, shmsValidateNoticeFacts($raw, shmsValidateNoticeEnglish(['title' => trim($draft['title']), 'content' => trim($draft['content']), 'source' => 'ai'])));
    } catch (Throwable $e) { return $fallback; }
}
