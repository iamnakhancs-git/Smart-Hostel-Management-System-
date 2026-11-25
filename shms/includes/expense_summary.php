<?php
// Included only by the authenticated admin dashboard.
require_once __DIR__ . '/enhancements.php';
$summaryReady=shmsReady(['hostel_expenses']);
?>
<div class="card card-outline card-primary">
  <div class="card-header"><h2 class="card-title">Current Month Expense Summary — <?= h(date('F Y')) ?></h2></div>
  <div class="card-body">
  <?php if (!$summaryReady): shmsSetupMessage(); else:
    $month=shmsPeriod([]);
    $occupied=shmsValue('SELECT COUNT(DISTINCT room_id) FROM room_allocations WHERE is_active=1');
    $bills=shmsValue('SELECT COUNT(*) FROM bills WHERE generated_at>=? AND generated_at<?',[$month['start'],$month['end']]);
    $expenses=shmsValue('SELECT COALESCE(SUM(amount),0) FROM hostel_expenses WHERE expense_date>=? AND expense_date<?',[$month['start'],$month['end']]);
  ?>
    <div class="row">
      <div class="col-md-4"><div class="small-box bg-info"><div class="inner"><h3><?= (int)$occupied ?></h3><p>Occupied Rooms Now</p></div><div class="icon"><i class="fas fa-door-open"></i></div><a class="small-box-footer" href="reports.php?type=occupancy">View occupancy <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-md-4"><div class="small-box bg-primary"><div class="inner"><h3><?= (int)$bills ?></h3><p>Bills Generated This Month</p></div><div class="icon"><i class="fas fa-file-invoice"></i></div><a class="small-box-footer" href="reports.php?type=billing">View billing report <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-md-4"><div class="small-box bg-success"><div class="inner"><h3>Rs. <?= number_format((float)$expenses,2) ?></h3><p>Hostel Expenses This Month</p></div><div class="icon"><i class="fas fa-receipt"></i></div><a class="small-box-footer" href="expenses.php">Manage expenses <i class="fas fa-arrow-circle-right"></i></a></div></div>
    </div>
    <p class="text-muted mb-0">Occupied rooms use active allocations today. Bills use generation date; expenses use expense date. Totals refresh whenever this page is loaded.</p>
  <?php endif; ?>
  </div>
</div>
