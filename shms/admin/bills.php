<?php
// ============================================================
// Admin — View Bills & Payment Status
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Bills & Payments';
$role      = ROLE_ADMIN;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id     = (int)($_POST['id']     ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    if ($id && in_array($status,['paid','unpaid'])) {
        $payDate = $status === 'paid' ? date('Y-m-d') : 'NULL';
        if ($status === 'paid') {
            $conn->query("UPDATE bills SET payment_status='paid', payment_date=CURDATE() WHERE id=$id");
        } else {
            $conn->query("UPDATE bills SET payment_status='unpaid', payment_date=NULL WHERE id=$id");
        }
        setFlash('success','Payment status updated.');
    }
    header('Location: bills.php'); exit;
}

$filterMonth  = (int)($_GET['month']   ?? 0);
$filterYear   = (int)($_GET['year']    ?? date('Y'));
$filterStatus = sanitize($_GET['status'] ?? '');
$filterStatusSql = $conn->real_escape_string($filterStatus);
$where = '1=1';
if ($filterMonth)  $where .= " AND b.month=$filterMonth";
if ($filterYear)   $where .= " AND b.year=$filterYear";
if ($filterStatus) $where .= " AND b.payment_status='$filterStatusSql'";

$bills = $conn->query(
    "SELECT b.*, r.name AS resident_name
     FROM bills b JOIN residents r ON b.resident_id=r.id
     WHERE $where ORDER BY b.generated_at DESC"
);
$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-file-invoice-dollar mr-2"></i>Bills &amp; Payments</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Bills</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body">
      <div class="d-flex flex-wrap align-items-center">
        <form method="get" class="form-inline mr-auto">
          <select name="month" class="form-control form-control-sm mr-1">
            <option value="">All Months</option>
            <?php for ($m=1;$m<=12;$m++): ?>
              <option value="<?= $m ?>" <?= $filterMonth==$m?'selected':'' ?>><?= monthName($m) ?></option>
            <?php endfor; ?>
          </select>
          <input type="number" name="year" class="form-control form-control-sm mr-1" value="<?= $filterYear ?>" style="width:80px;">
          <select name="status" class="form-control form-control-sm mr-1">
            <option value="">All</option>
            <option value="paid"   <?= $filterStatus==='paid'   ?'selected':'' ?>>Paid</option>
            <option value="unpaid" <?= $filterStatus==='unpaid' ?'selected':'' ?>>Unpaid</option>
          </select>
          <button class="btn btn-sm btn-primary mr-2"><i class="fas fa-filter mr-1"></i>Filter</button>
        </form>
        <a href="generate_bill.php" class="btn btn-danger btn-sm"><i class="fas fa-bolt mr-1"></i>Generate Bills</a>
      </div>
    </div></div>

    <div class="card"><div class="card-body p-0">
      <table class="table table-hover data-table">
        <thead><tr><th>#</th><th>Resident</th><th>Month/Year</th><th>Room Rent</th><th>Previous Dues</th><th>Misc</th><th>Mess</th><th>Total</th><th>Status</th><th>Pay Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php $i=1; while ($b=$bills->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($b['resident_name']) ?></td>
            <td><?= monthName($b['month']).' '.$b['year'] ?></td>
            <td><?= formatCurrency($b['room_rent']) ?></td>
            <td><?= formatCurrency($b['previous_dues'] ?? 0) ?></td>
            <td><?= formatCurrency($b['misc_charges']) ?></td>
            <td><?= formatCurrency($b['mess_charges']) ?></td>
            <td><strong><?= formatCurrency($b['total_amount']) ?></strong></td>
            <td><?= statusBadge($b['payment_status']) ?></td>
            <td><?= $b['payment_date'] ? date('d M Y',strtotime($b['payment_date'])) : '—' ?></td>
            <td>
              <a href="view_bill.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <input type="hidden" name="status" value="<?= $b['payment_status']==='paid' ? 'unpaid' : 'paid' ?>">
                <button class="btn btn-xs <?= $b['payment_status']==='paid' ? 'btn-warning' : 'btn-success' ?>">
                  <?= $b['payment_status']==='paid' ? 'Mark Unpaid' : 'Mark Paid' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div></div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
