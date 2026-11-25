<?php $messDetails = $messDetails ?? shmsBillMessDetails($bill['id']); ?>
<div class="card bill-card" style="max-width:760px;margin:auto;">
  <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center no-print">
    <h3 class="card-title mb-0"><i class="fas fa-file-invoice mr-2"></i>Monthly Bill</h3>
    <div><a href="?id=<?= (int)$bill['id'] ?>&amp;download=pdf" class="btn btn-sm btn-success mr-1"><i class="fas fa-file-pdf mr-1"></i>Download PDF</a><button id="printBill" type="button" class="btn btn-sm btn-light mr-1"><i class="fas fa-print mr-1"></i>Print</button><a href="<?= h($billBackUrl) ?>" class="btn btn-sm btn-secondary">Back</a></div>
  </div>
  <div class="card-body" id="bill-document">
    <div class="text-center mb-4"><h3><?= h(APP_NAME) ?></h3><h5>Monthly Bill — <?= h(monthName($bill['month']).' '.$bill['year']) ?></h5><hr></div>
    <div class="row mb-3"><div class="col-sm-6 bill-contact"><strong>Resident:</strong> <?= h($bill['resident_name']) ?><br><strong>Email:</strong> <?= h($bill['email']) ?><br><strong>Phone:</strong> <?= h($bill['phone']?:'Not set') ?></div>
    <div class="col-sm-6 text-sm-right bill-contact"><strong>Room:</strong> <?= h($bill['room_number']?:'Not allocated') ?><br><strong>Type:</strong> <?= h($bill['type_name']?'Type '.$bill['type_name']:'Not allocated') ?><br><strong>Bill #:</strong> <?= h(shmsBillNumber($bill['id'])) ?></div></div>
    <table class="table table-bordered"><thead><tr><th>Description</th><th class="text-right">Amount (PKR)</th></tr></thead><tbody>
      <tr><td>Room Rent (<?= h(monthName($bill['month'])) ?>)</td><td class="text-right"><?= h(number_format((float)$bill['room_rent'],2)) ?></td></tr>
      <tr><td>Previous Unpaid Dues</td><td class="text-right"><?= h(number_format((float)($bill['previous_dues'] ?? 0),2)) ?></td></tr>
      <tr><td>Miscellaneous Charges</td><td class="text-right"><?= h(number_format((float)$bill['misc_charges'],2)) ?></td></tr>
      <tr><td>Mess Charges</td><td class="text-right"><?= h(number_format((float)$bill['mess_charges'],2)) ?></td></tr>
    </tbody><tfoot><tr class="table-secondary"><th>Total Amount</th><th class="text-right">PKR <?= h(number_format((float)$bill['total_amount'],2)) ?></th></tr></tfoot></table>
    <?php if ($messDetails): ?>
    <h5 class="mt-4">Mess Charge Details</h5>
    <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Date</th><th>Meal</th><th class="text-right">Charged Rate (PKR)</th></tr></thead><tbody>
      <?php foreach ($messDetails as $detail): ?><tr><td><?= h(shmsBillDate($detail['attendance_date'])) ?></td><td><?= h(ucfirst($detail['meal_type'])) ?></td><td class="text-right"><?= h(number_format((float)$detail['charged_rate'],2)) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot><tr><th colspan="2" class="text-right">Mess Total</th><th class="text-right"><?= h(number_format(array_sum(array_map(static function($d){ return (float)$d['charged_rate']; }, $messDetails)),2)) ?></th></tr></tfoot></table></div>
    <?php elseif ((float)$bill['mess_charges'] > 0): ?><p class="text-muted small">Detailed mess line-items are unavailable because this bill was generated before the billing-integrity update.</p>
    <?php endif; ?>
    <div class="row mt-3 bill-meta"><div class="col-sm-6"><strong>Payment Status:</strong> <?= statusBadge($bill['payment_status']) ?></div><div class="col-sm-6 text-sm-right"><strong>Due Date:</strong> <?= h(shmsBillDate($bill['due_date'])) ?></div><div class="col-sm-6"><strong>Generated:</strong> <?= h(shmsBillDate($bill['generated_at'])) ?></div><?php if($bill['payment_date']): ?><div class="col-sm-6 text-sm-right"><strong>Paid On:</strong> <?= h(shmsBillDate($bill['payment_date'])) ?></div><?php endif; ?></div>
    <p class="text-center text-muted small mt-4 mb-0">Computer-generated bill issued by <?= h(APP_NAME) ?>.</p>
  </div>
</div>
