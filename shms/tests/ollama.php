<?php
// Contract tests: no network, model, API key or database required.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/notice_generator.php';
$checks = 0;
function expect($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
}
function rejects($call, $label) {
    $rejected = false;
    try { $call(); } catch (Throwable $e) { $rejected = true; }
    expect($rejected, $label);
}
$config = ['provider'=>'ollama', 'endpoint'=>'http://127.0.0.1:11434/api/chat', 'model'=>'qwen3:4b', 'timeout'=>180];
$raw = 'Water supply raat 9 baje aaye gi';
$ok = ['status'=>200, 'body'=>json_encode(['done'=>true, 'done_reason'=>'stop', 'message'=>['content'=>json_encode(['title'=>'Water Supply Update', 'content'=>'Water supply will be available at 9:00 PM.'])]])];
$draft = shmsGenerateOllama($raw, $config, function ($url, $payload, $timeout) use ($ok, $raw) {
    expect($url === 'http://127.0.0.1:11434/api/chat', 'Native local chat URL');
    expect($timeout === 180, 'CPU generation timeout');
    expect($payload['model'] === 'qwen3:4b', 'Configured model');
    expect($payload['stream'] === false && $payload['think'] === false, 'Non-streaming, thinking disabled');
    expect($payload['messages'][1]['content'] === $raw, 'Input reaches model verbatim');
    expect($payload['format']['required'] === ['title','content'], 'Structured notice contract');
    expect($payload['options']['num_ctx'] === 2048, 'Bounded context');
    return $ok;
});
expect($draft['source'] === 'ollama' && $draft['title'] === 'Water Supply Update', 'Ollama result decoded');
foreach (['https://example.com/api/chat','http://localhost:11434/api/chat','http://127.0.0.1:11434/api/chat?x=1','http://user@127.0.0.1:11434/api/chat','file:///tmp/test','http://127.0.0.1:0/api/chat','http://127.0.0.1:65536/api/chat'] as $url) {
    rejects(function () use ($config, $url) { shmsOllamaEndpoint(array_merge($config, ['endpoint'=>$url])); }, 'Reject nonlocal or malformed URL');
}
foreach (['qwen3:cloud','qwen3:4b-cloud',"qwen3:4b\n",''] as $model) {
    rejects(function () use ($config, $model) { shmsOllamaEndpoint(array_merge($config, ['model'=>$model])); }, 'Reject invalid/cloud model');
}
foreach (['', '   ', str_repeat('x',3001)] as $input) {
    rejects(function () use ($input, $config) { shmsOllamaPayload($input,$config); }, 'Reject empty/oversized input');
}
foreach ([['status'=>404,'body'=>'{}'], ['status'=>500,'body'=>'{}'], ['status'=>200,'body'=>'bad json'], ['status'=>200,'body'=>'{"done":false}'], ['status'=>200,'body'=>'{"done":true,"done_reason":"length"}'], ['status'=>200,'body'=>'{"done":true,"message":{"content":"{}"}}']] as $response) {
    rejects(function () use ($response) { shmsOllamaDraftResponse($response); }, 'Reject failed or incomplete result');
}
$message = shmsCheckOllama($config, function ($url,$payload,$timeout) {
    expect($url === 'http://127.0.0.1:11434/api/tags' && $payload === null && $timeout === 5, 'Model check uses local GET');
    return ['status'=>200,'body'=>'{"models":[{"name":"qwen3:4b"}]}'];
});
expect(strpos($message,'downloaded') !== false, 'Installed model recognized');
rejects(function () use ($config) { shmsCheckOllama($config, function () { return ['status'=>200,'body'=>'{"models":[]}']; }); }, 'Missing model rejected');
// Ensure a failed local config returns an honest template without trying a remote URL.
if (!is_file(__DIR__.'/../config/ai.local.php')) {
    $previous = getenv('SHMS_AI_ENDPOINT');
    $previousProvider = getenv('SHMS_AI_PROVIDER');
    putenv('SHMS_AI_PROVIDER=ollama');
    putenv('SHMS_AI_ENDPOINT=https://invalid.example/api/chat');
    $fallback = shmsGenerateNotice($raw);
    expect($fallback['source'] === 'template' && !empty($fallback['reason']), 'Failure has editable template and reason');
    putenv($previous === false ? 'SHMS_AI_ENDPOINT' : 'SHMS_AI_ENDPOINT='.$previous);
    putenv($previousProvider === false ? 'SHMS_AI_PROVIDER' : 'SHMS_AI_PROVIDER='.$previousProvider);
}
echo "PASS: $checks local Ollama contract checks (mocked transport, no actual inference).\n";
