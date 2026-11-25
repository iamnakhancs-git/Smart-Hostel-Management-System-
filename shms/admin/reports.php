<?php
require_once __DIR__ . '/../includes/report_data.php';
requireLogin(ROLE_ADMIN);
$types=['billing'=>'Billing','attendance'=>'Resident Mess Attendance','occupancy'=>'Room Occupancy','expenses'=>'Hostel Expenses'];
$type=$_GET['type'] ?? 'billing';
$error=''; $report=null;
try {
    if (!is_string($type) || !isset($types[$type])) { throw new InvalidArgumentException('Choose a valid report.'); }
    $range=shmsPeriod($_GET);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        shmsCheckPost();
        if (shmsPost('action')!=='due_date_all' || $type!=='billing') { throw new InvalidArgumentException('Invalid action.'); }
        $due=shmsPost('due_date');
        if ($due!=='') { shmsDate($due); }
        $billCount=(int)shmsValue('SELECT COUNT(*) FROM bills WHERE generated_at>=? AND generated_at<?',[$range['start'],$range['end']]);
        if ($billCount<1) { throw new InvalidArgumentException('No bills were found in the selected period.'); }
        shmsQuery('UPDATE bills SET due_date=? WHERE generated_at>=? AND generated_at<?',[$due===''?null:$due,$range['start'],$range['end']])->close();
        setFlash('success',$due==='' ? "Due date removed from all $billCount bills in this report." : "The same due date was applied to all $billCount bills in this report.");
        header('Location: reports.php?' . http_build_query(['type'=>'billing','period'=>$range['period'],'week_date'=>$range['start'],'month'=>substr($range['start'],0,7)])); exit;
    }
    $report=shmsReport($type,$range);
} catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
catch (mysqli_sql_exception $e) { error_log('SHMS report: ' . $e->getMessage()); $error='Report could not be loaded. Check database configuration and recreate the database using sql/shms_database.sql.'; }
$range=$range ?? shmsPeriod([]);
$export=$_GET['export'] ?? '';
if ($report && $export==='csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shms-' . $type . '-' . $range['start'] . '.csv"');
    $out=fopen('php://output','w');
    fwrite($out,"\xEF\xBB\xBF");
    $write = function($values) use ($out) {
        // Prevent spreadsheet formula execution when opening names/notes as CSV.
        $values=array_map(function($v) { $s=(string)$v; return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u',$s) ? "'".$s : $s; },$values);
        fputcsv($out,$values,',','"','');
    };
    $write([$report['title'],$range['start'].' to '.$range['last']]);
    $write(['Generated',date('Y-m-d H:i:s')]);
    $write([$report['note']]);
    foreach ($report['summary'] as $key=>$value) { $write([$key,$value]); }
    $write([]); $write($report['headers']);
    foreach ($report['rows'] as $row) { $write($row); }
    if (isset($report['categories'])) {
        $write([]); $write(['Category','Records','Amount (Rs.)']);
        foreach ($report['categories'] as $row) { $write(array_values($row)); }
    }
    if (isset($report['allocations'])) {
        $write([]); $write(['Room','Resident','Allocated','Vacated']);
        foreach ($report['allocations'] as $row) { $write(array_values($row)); }
    }
    fclose($out); exit;
}
shmsPageStart('Reports',ROLE_ADMIN);
?>
<div class="card no-print"><div class="card-body">
<form method="get"><div class="form-row align-items-end">
  <div class="form-group col-md-3"><label for="type">Report</label><select class="form-control" id="type" name="type"><?php foreach ($types as $key=>$label): ?><option value="<?= h($key) ?>" <?= $type===$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
  <div class="form-group col-md-2"><label for="period">Period</label><select class="form-control" id="period" name="period"><option value="weekly" <?= $range['period']==='weekly'?'selected':'' ?>>Weekly</option><option value="monthly" <?= $range['period']==='monthly'?'selected':'' ?>>Monthly</option></select></div>
  <div class="form-group col-md-3" id="week-field"><label for="week_date">Any date in week (Mon–Sun)</label><input class="form-control" type="date" id="week_date" name="week_date" value="<?= h($range['start']) ?>"></div>
  <div class="form-group col-md-3" id="month-field"><label for="month">Month</label><input class="form-control" type="month" id="month" name="month" value="<?= h(substr($range['start'],0,7)) ?>"></div>
  <div class="form-group col-md-2"><button class="btn btn-primary">Generate Report</button></div>
</div></form>
</div></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($report): ?>
<div class="card report-sheet"><div class="card-header"><h2 class="card-title"><?= h($report['title']) ?></h2><div class="card-tools no-print"><button class="btn btn-sm btn-default" onclick="window.print()"><i class="fas fa-print mr-1"></i>Print / Save PDF</button> <a class="btn btn-sm btn-outline-primary" href="?<?= h(http_build_query(['type'=>$type,'period'=>$range['period'],'week_date'=>$range['start'],'month'=>substr($range['start'],0,7),'export'=>'csv'])) ?>">Export CSV</a></div></div>
<div class="card-body"><p><strong><?= h(APP_NAME) ?></strong><br><?= h(ucfirst($range['period'])) ?>: <?= h($range['start']) ?> to <?= h($range['last']) ?><br><small>Generated: <?= h(date('Y-m-d H:i:s')) ?></small></p>
<p class="text-muted"><?= h($report['note']) ?></p>
<div class="row"><?php foreach ($report['summary'] as $label=>$value): ?><div class="col-md-4 col-sm-6"><div class="info-box bg-light"><div class="info-box-content"><span class="info-box-text"><?= h($label) ?></span><span class="info-box-number"><?= h($value) ?></span></div></div></div><?php endforeach; ?></div>
<div class="table-responsive"><table class="table table-bordered table-striped report-table"><thead><tr><?php foreach ($report['headers'] as $label): ?><th><?= h($label) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($report['rows'] as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= h($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$report['rows']): ?><tr><td colspan="<?= count($report['headers']) ?>" class="text-center text-muted">No records for this period.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if (!empty($report['categories'])): ?><h3 class="h5 mt-4">Expense breakdown by category</h3><table class="table table-bordered"><thead><tr><th>Category</th><th>Records</th><th>Amount (Rs.)</th></tr></thead><tbody><?php foreach ($report['categories'] as $row): ?><tr><td><?= h($row['category']) ?></td><td><?= (int)$row['records'] ?></td><td><?= number_format((float)$row['amount'],2) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if (!empty($report['allocations'])): ?><h3 class="h5 mt-4">Allocations overlapping the period</h3><table class="table table-bordered"><thead><tr><th>Room</th><th>Resident</th><th>Allocation date</th><th>Vacate date</th></tr></thead><tbody><?php foreach ($report['allocations'] as $row): ?><tr><td><?= h($row['room_number']) ?></td><td><?= h($row['name']) ?></td><td><?= h($row['alloc_date']) ?></td><td><?= h($row['vacate_date'] ?: 'Still allocated') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</div></div>
<?php if ($type==='billing' && $report['raw']):
  $dueValues=array_values(array_unique(array_map(static function($bill){ return (string)($bill['due_date'] ?? ''); },$report['raw'])));
  $commonDueDate=count($dueValues)===1 ? $dueValues[0] : '';
?><div class="card no-print"><div class="card-header"><h2 class="card-title">Assign One Due Date to All Bills</h2></div><div class="card-body">
  <p>Select one date for all <?= count($report['raw']) ?> bills shown in this billing report. Blank removes the due date from all of them.</p>
  <?php if (count($dueValues)>1): ?><div class="alert alert-warning py-2">These bills currently have different due dates. Saving below will make them all the same.</div><?php endif; ?>
  <form method="post" class="form-inline" onsubmit="return confirm('Apply this due date to every bill shown in this report?')">
    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="action" value="due_date_all">
    <label class="mr-2" for="common-due-date"><strong>Common Due Date</strong></label>
    <input type="date" id="common-due-date" name="due_date" class="form-control mr-2" value="<?= h($commonDueDate) ?>">
    <button class="btn btn-primary"><i class="fas fa-calendar-check mr-1"></i>Apply to All Bills</button>
  </form>
</div></div><?php endif; ?>
<?php endif; ?>
<script>
function showPeriodField() {
  const weekly=document.getElementById('period').value==='weekly';
  document.getElementById('week-field').hidden=!weekly;
  document.getElementById('month-field').hidden=weekly;
  document.getElementById('week_date').disabled=!weekly;
  document.getElementById('month').disabled=weekly;
}
document.getElementById('period').addEventListener('change',showPeriodField);
showPeriodField();
</script>
<?php shmsPageEnd(); ?>
