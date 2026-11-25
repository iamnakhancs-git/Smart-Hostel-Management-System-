<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/notice_generator.php';
$checks = 0;
function factCheck($condition, $message) {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function factReject($input, $output) {
    $rejected = false;
    try { shmsValidateNoticeFacts($input, ['title'=>'Water Supply Update','content'=>$output]); }
    catch (RuntimeException $e) { $rejected = true; }
    factCheck($rejected, 'Expected rejection: '.$output);
}
$raw = 'water supply rat 9 bajey aye gi';
$good = 'Dear Residents, water supply will be available at 9 PM. Hostel Administration.';
$bad = 'Dear Residents, water supply will resume at 9 AM tomorrow. Please note that the exact time may vary slightly. Hostel Administration';
factReject($raw, $bad);
factReject($raw, 'Water supply at 9 PM tomorrow.');
factReject($raw, 'Water supply at 9 AM.');
factReject($raw, 'Water supply at 10 PM.');
factReject($raw, 'Water supply at 9 PM. The exact time may vary slightly.');
factReject($raw, 'Water supply at 9 PM today.');
factReject($raw, 'Water supply at 9 PM tonight.');
factReject($raw, 'Water supply at 9 PM on Monday.');
factReject($raw, 'Water supply at 9 PM on 2026-09-13.');
factReject($raw, 'Water supply will be available.');
factReject('Water supply at 9:30 PM.', 'Water supply at 9 PM.');
$draft = ['title'=>'Water Supply Update','content'=>$good];
factCheck(shmsValidateNoticeFacts($raw,$draft) === $draft, 'Accept accurate notice');
factCheck(shmsValidateNoticeFacts('Water supply at 9 PM tomorrow.', ['title'=>'Notice','content'=>'Water supply at 9:00 p.m. tomorrow.'])['title'] === 'Notice', 'Preserve supplied tomorrow and equivalent time');
factCheck(shmsValidateNoticeFacts('Water supply may vary.', ['title'=>'Notice','content'=>'Water supply may vary.'])['title'] === 'Notice', 'Modal may is not a calendar date');
foreach (['rat','raat'] as $night) {
    $fallback = shmsFallbackNotice('water supply '.$night.' 9 bajey aye gi');
    factCheck(strpos($fallback['content'],'9:00 PM') !== false && strpos($fallback['content'],'tomorrow') === false, 'Recognize both night spellings');
}
foreach (['12','1'] as $hour) {
    $ambiguous = 'water supply raat '.$hour.' bajey aye gi';
    factCheck(strpos(shmsFallbackNotice($ambiguous)['content'],$ambiguous) !== false, 'Do not resolve ambiguous late-night hours');
}
// Exercise the real public generation path with a fake HTTP response only.
// Bundled defaults are used; do not replace local deployment settings in tests.
if (!is_file(__DIR__.'/../config/ai.local.php') && shmsAIConfig()['provider'] === 'ollama') {
    $transport = function () use ($bad) {
        return ['status'=>200,'body'=>json_encode(['done'=>true,'message'=>['content'=>json_encode(['title'=>'Water Supply Update','content'=>$bad])]])];
    };
    $fallback = shmsGenerateNotice($raw,$transport);
    factCheck($fallback['source'] === 'template' && isset($fallback['reason']), 'Reject hallucinated draft through real generator');
    factCheck(strpos($fallback['content'],'9:00 PM') !== false && strpos($fallback['content'],'tomorrow') === false, 'Generator returns accurate labelled fallback');
    $success = shmsGenerateNotice($raw,function () use ($good) {
        return ['status'=>200,'body'=>json_encode(['done'=>true,'message'=>['content'=>json_encode(['title'=>'Water Supply Update','content'=>$good])]])];
    });
    factCheck($success['source'] === 'ollama' && strpos($success['content'],'water supply will be available at 9 PM.') !== false, 'Accurate AI facts remain in formatted AI output');
}
$outage = 'subha 8 bajey light ni hogi';
$oneLine = ['title'=>'Electricity Supply Notice','content'=>'Dear Residents, electricity will not be available at 8 AM. Hostel Administration.','source'=>'ollama'];
$formatted = shmsFormatNotice($outage, shmsValidateNoticeFacts($outage,$oneLine));
factCheck(strpos($formatted['content'],"Dear Residents,\n\n") === 0, 'Greeting gets separate paragraph');
factCheck(strpos($formatted['content'],"\n\nWe apologize for any inconvenience this may cause.\n\n") !== false, 'Outage apology automatically gets own paragraph');
factCheck(strpos($formatted['content'],"\n\nThank you for your understanding and cooperation.\n\nHostel Administration") !== false, 'Cooperation and sign-off get separate paragraphs');
factCheck(strpos($formatted['content'],'at 8 AM.') !== false && stripos($formatted['content'],'tomorrow') === false && stripos($formatted['content'],'maintenance') === false, 'Politeness adds no date or cause');
factCheck(shmsFormatNotice($outage,$formatted) === $formatted, 'Formatting is idempotent and does not duplicate courtesy');
$routine = shmsFormatNotice('Meeting at 8 AM.', ['title'=>'Meeting','content'=>'Meeting at 8 AM.']);
factCheck(stripos($routine['content'],'apolog') === false, 'Routine meeting gets thanks without unnecessary apology');
$template = shmsFallbackNotice($outage);
factCheck($template['source'] === 'template' && strpos($template['content'],'8:00 AM') !== false && strpos($template['content'],'We apologize') !== false, 'Exact screenshot fallback has correct time and apology');
factReject($outage,'Electricity will not be available at 8 PM.');
factReject($outage,'Electricity will not be available at 8 AM tomorrow. We apologize for any inconvenience.');
if (!is_file(__DIR__.'/../config/ai.local.php') && shmsAIConfig()['provider'] === 'ollama') {
    $outageDraft = shmsGenerateNotice($outage,function () {
        return ['status'=>200,'body'=>json_encode(['done'=>true,'message'=>['content'=>json_encode(['title'=>'Electricity Supply Notice','content'=>'Please be informed that electricity will not be available at 8 AM.'])]])];
    });
    factCheck($outageDraft['source'] === 'ollama' && strpos($outageDraft['content'],'We apologize') !== false, 'Real generator adds omitted apology to valid AI body');
    $invalidOutage = shmsGenerateNotice($outage,function () {
        return ['status'=>200,'body'=>json_encode(['done'=>true,'message'=>['content'=>json_encode(['title'=>'Electricity Supply Notice','content'=>'Electricity will not be available at 8 PM tomorrow.'])]])];
    });
    factCheck($invalidOutage['source'] === 'template' && strpos($invalidOutage['content'],'We apologize') !== false && strpos($invalidOutage['content'],'8:00 AM') !== false, 'Fact failure still returns polite correctly timed template');
    echo "Sample formatted notice:\n" . $outageDraft['title'] . "\n\n" . $outageDraft['content'] . "\n\n";
}
echo "PASS: $checks notice fact/style regression checks (mocked inference).\n";
