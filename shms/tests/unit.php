<?php
// No database or API key required. Run: php tests/unit.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/enhancements.php';
require_once __DIR__ . '/../includes/notice_generator.php';
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$week=shmsPeriod(['period'=>'weekly','week_date'=>'2026-01-01']);
check($week['start']==='2025-12-29' && $week['end']==='2026-01-05','ISO week crosses year correctly');
$month=shmsPeriod(['period'=>'monthly','month'=>'2024-02']);
check($month['last']==='2024-02-29' && $month['end']==='2024-03-01','Leap year boundary');
foreach (['2026-02-30','2026-13-01','2026-01-01 OR 1=1','2026-2-1','0000-01-01'] as $date) {
    $rejected=false;
    try { shmsDate($date); } catch (InvalidArgumentException $e) { $rejected=true; }
    check($rejected,'Reject malformed date '.$date);
}
check(h('<script>"&')==='&lt;script&gt;&quot;&amp;','Escape untrusted notice/report text');
$draft=shmsFallbackNotice('Water supply raat 9 baje aaye gi');
check(strpos($draft['content'],'9:00 PM')!==false,'Translate supported example without inventing a date');
check($draft['source']==='template','Template is not misrepresented as AI');
$raw='Maintenance at 10; date not confirmed. Do not invent details.';
check(strpos(shmsFallbackNotice($raw)['content'],$raw)!==false,'Unknown instructions stay verbatim in fallback');
require_once __DIR__ . '/../includes/functions.php';
check(formatStayDuration('2026-01-01','2026-03-15')==='2 mos, 14 days','Stay duration format with months and days');
check(formatStayDuration('2026-05-01','2026-05-02')==='1 day','Single day stay duration format');
check(strpos(formatStayDuration('2026-01-01',null),'(Current)')!==false,'Ongoing stay duration format');
echo "PASS: period boundaries, invalid dates, output escaping, stay duration, and honest fallback drafts.\n";
