<?php
// ============================================================
// Admin — Miscellaneous Charges
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Miscellaneous Charges';
$role      = ROLE_ADMIN;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = sanitize($_POST['action'] ?? '');
    if ($action === 'add' || $action === 'update') {
        $chargeId = (int)($_POST['id'] ?? 0);
        $resId  = (int)($_POST['resident_id'] ?? 0);
        $month  = (int)($_POST['month'] ?? date('n'));
        $year   = (int)($_POST['year']  ?? date('Y'));
        $desc   = sanitize($_POST['description'] ?? '');
        $amount = shmsMoney($_POST['amount'] ?? '', false);
        $adminId = (int)$_SESSION['user_id'];

        if (!$resId || !$desc || $amount <= 0 || !shmsValue('SELECT COUNT(*) FROM residents WHERE id=?', [$resId])) {
            $errors[] = 'All fields are required and amount must be > 0.';
        } elseif ($action === 'update' && (!$chargeId || !shmsValue('SELECT COUNT(*) FROM miscellaneous_charges WHERE id=?', [$chargeId]))) {
            $errors[] = 'Charge record was not found.';
        } else {
            if ($action === 'update') {
                $stmt = $conn->prepare(
                    "UPDATE miscellaneous_charges SET resident_id=?,month=?,year=?,description=?,amount=?,added_by=? WHERE id=?"
                );
                $stmt->bind_param('iiisdii',$resId,$month,$year,$desc,$amount,$adminId,$chargeId);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO miscellaneous_charges (resident_id,month,year,description,amount,added_by) VALUES (?,?,?,?,?,?)"
                );
                $stmt->bind_param('iiisdi',$resId,$month,$year,$desc,$amount,$adminId);
            }
            $stmt->execute(); $stmt->close();
            setFlash('success', $action === 'update' ? 'Miscellaneous charge updated.' : 'Miscellaneous charge added.');
            header('Location: misc_charges.php'); exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        shmsQuery('DELETE FROM miscellaneous_charges WHERE id=?', [$id])->close();
        setFlash('success','Charge deleted.');
        header('Location: misc_charges.php'); exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editCharge = null;
if ($editId) {
    $rows = shmsRows('SELECT * FROM miscellaneous_charges WHERE id=?', [$editId]);
    if (!$rows) { setFlash('error', 'Charge record not found.'); header('Location: misc_charges.php'); exit; }
    $editCharge = $rows[0];
}

$filterRes   = (int)($_GET['resident_id'] ?? 0);
$filterMonth = (int)($_GET['month'] ?? 0);
$filterYear  = (int)($_GET['year']  ?? date('Y'));
$where = '1=1';
if ($filterRes)   $where .= " AND mc.resident_id=$filterRes";
if ($filterMonth) $where .= " AND mc.month=$filterMonth";
if ($filterYear)  $where .= " AND mc.year=$filterYear";

$charges = $conn->query(
    "SELECT mc.*, r.name AS resident_name
     FROM miscellaneous_charges mc
     JOIN residents r ON mc.resident_id=r.id
     WHERE $where
     ORDER BY mc.created_at DESC"
);
$residents = $conn->query("SELECT id,name FROM residents WHERE status='active' ORDER BY name");
$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-receipt mr-2"></i>Miscellaneous Charges</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Misc Charges</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

    <!-- Add/Edit Charge Form -->
    <div class="card <?= $editCharge ? 'card-warning' : 'card-primary' ?>">
      <div class="card-header"><h3 class="card-title"><i class="fas <?= $editCharge ? 'fa-edit' : 'fa-plus' ?> mr-2"></i><?= $editCharge ? 'Edit Charge' : 'Add Charge' ?></h3></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="<?= $editCharge ? 'update' : 'add' ?>">
        <?php if ($editCharge): ?><input type="hidden" name="id" value="<?= (int)$editCharge['id'] ?>"><?php endif; ?>
        <div class="card-body">
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label>Resident</label>
                <select name="resident_id" class="form-control" required>
                  <option value="">-- Select --</option>
                  <?php $residents->data_seek(0); while ($r=$residents->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= (int)($editCharge['resident_id'] ?? 0)===(int)$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Month</label>
                <select name="month" class="form-control">
                  <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m===(int)($editCharge['month'] ?? date('n'))?'selected':'' ?>><?= monthName($m) ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Year</label>
                <input type="number" name="year" class="form-control" value="<?= (int)($editCharge['year'] ?? date('Y')) ?>" min="2020" max="2099">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control" maxlength="200" required placeholder="e.g. Electricity, Laundry" value="<?= htmlspecialchars($editCharge['description'] ?? '') ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Amount (Rs.)</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required value="<?= $editCharge ? number_format((float)$editCharge['amount'], 2, '.', '') : '' ?>">
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn <?= $editCharge ? 'btn-warning' : 'btn-primary' ?>"><i class="fas fa-save mr-1"></i><?= $editCharge ? 'Update Charge' : 'Add Charge' ?></button>
          <?php if ($editCharge): ?><a href="misc_charges.php" class="btn btn-secondary ml-2">Cancel Edit</a><?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Filter & List -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Charge Records</h3>
        <div class="card-tools">
          <form method="get" class="form-inline">
            <select name="resident_id" class="form-control form-control-sm mr-1">
              <option value="">All Residents</option>
              <?php $residents->data_seek(0); while ($r=$residents->fetch_assoc()): ?>
                <option value="<?= $r['id'] ?>" <?= $filterRes==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
              <?php endwhile; ?>
            </select>
            <select name="month" class="form-control form-control-sm mr-1">
              <option value="">All Months</option>
              <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $filterMonth==$m?'selected':'' ?>><?= monthName($m) ?></option>
              <?php endfor; ?>
            </select>
            <input type="number" name="year" class="form-control form-control-sm mr-1" value="<?= $filterYear ?>" style="width:80px;">
            <button class="btn btn-sm btn-primary">Filter</button>
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover data-table">
          <thead><tr><th>#</th><th>Resident</th><th>Month/Year</th><th>Description</th><th>Amount</th><th>Added On</th><th>Action</th></tr></thead>
          <tbody>
          <?php $i=1; while ($c=$charges->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($c['resident_name']) ?></td>
              <td><?= monthName($c['month']).' '.$c['year'] ?></td>
              <td><?= htmlspecialchars($c['description']) ?></td>
              <td><?= formatCurrency($c['amount']) ?></td>
              <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
              <td>
                <a href="misc_charges.php?edit=<?= (int)$c['id'] ?>" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="btn btn-xs btn-danger btn-delete"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
