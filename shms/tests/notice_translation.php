<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/notice_generator.php';
$checks = 0;
function translationCheck($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
}
function translationResponse($content) {
    return ['status'=>200, 'body'=>json_encode(['done'=>true,'message'=>['content'=>json_encode(['title'=>'Hostel Closure Notice','content'=>$content])]])];
}
$config = ['provider'=>'ollama','endpoint'=>'http://127.0.0.1:11434/api/chat','model'=>'qwen3:4b','timeout'=>180];
$ambiguous = 'kal se 3 tak hostel band hoga eid ki waja se or hostel 16 april ko khuley ga subha 6 bajey';
$calls = 0;
$clarification = shmsGenerateNotice($ambiguous,function () use (&$calls) { $calls++; throw new RuntimeException('Should not run'); });
translationCheck($calls === 0 && $clarification['source'] === 'clarification' && $clarification['content'] === '', 'Exact ambiguous screenshot asks for unit without inventing a draft');
foreach (['kal se 3 din ke liye hostel band hoga', 'hostel 3 April tak band hoga', 'hostel 3 PM tak band hoga'] as $clear) {
    translationCheck(shmsNoticeClarification($clear) === null, 'Explicit units do not trigger bare-number warning');
}
$raw = 'kal se hostel band hoga eid ki waja se or hostel 16 april ko khuley ga subha 6 bajey';
$english = 'Please be informed that the hostel will be closed from tomorrow due to Eid. The hostel will reopen on April 16 at 6 AM.';
$calls = 0;
$result = shmsGenerateOllama($raw,$config,function ($url,$payload,$timeout) use (&$calls,$raw,$english) {
    $calls++;
    translationCheck($payload['messages'][1]['content'] === $raw, 'Retry retains original input');
    translationCheck($timeout > 0 && $timeout <= 180, 'Request stays in shared timeout budget');
    return translationResponse($calls === 1 ? 'The hostel will be closed from kal se due to Eid. It will reopen on 16 April at 6 AM.' : $english);
});
translationCheck($calls === 2 && $result['source'] === 'ollama', 'Untranslated first reply is corrected once');
translationCheck(strpos($result['content'],'April 16 at 6 AM') !== false && strpos($result['content'],'We apologize') !== false, 'Date, morning time and closure apology survive');
foreach (['April 17 at 6 AM', 'April 16 at 6 PM'] as $wrong) {
    $calls = 0;
    $rejected = false;
    try { shmsGenerateOllama($raw,$config,function () use (&$calls,$wrong) { $calls++; return translationResponse('The hostel will reopen on '.$wrong.'.'); }); }
    catch (RuntimeException $e) { $rejected = true; }
    translationCheck($rejected && $calls === 2, 'Changed date/time rejected after bounded retry');
}
$calls = 0;
try { shmsGenerateOllama($raw,$config,function () use (&$calls) { $calls++; return ['status'=>404,'body'=>'{}']; }); }
catch (RuntimeException $e) { /* expected */ }
translationCheck($calls === 1, 'Missing service/model is not retried');
if (!is_file(__DIR__.'/../config/ai.local.php') && shmsAIConfig()['provider'] === 'ollama') {
    $failed = shmsGenerateNotice($raw,function () { return translationResponse('Hostel band hoga kal se.'); });
    translationCheck($failed['source'] === 'error' && $failed['content'] === '' && !empty($failed['reason']), 'Failed translation never returns raw Roman Urdu as new draft');
    $offline = shmsGenerateNotice($raw,function () { throw new RuntimeException('Cannot reach local Ollama.'); });
    translationCheck($offline['source'] === 'error' && strpos($offline['reason'],'Cannot reach') !== false, 'Connection reason remains visible');
}
echo "PASS: $checks translation/clarification checks (mocked transport).\n";
