<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_RESIDENT);

$conn      = getDBConnection();
$pageTitle = 'My Bills';
$role      = ROLE_RESIDENT;
$rid       = (int)$_SESSION['user_id'];

$bills = $conn->query(
    "SELECT * FROM bills WHERE resident_id=$rid ORDER BY year DESC, month DESC"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_resident.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-file-invoice mr-2"></i>My Bills</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">My Bills</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body p-0">
      <table class="table table-hover data-table">
        <thead><tr><th>#</th><th>Month/Year</th><th>Room Rent</th><th>Previous Dues</th><th>Misc Charges</th><th>Mess Charges</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php $i=1; while ($b=$bills->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= monthName($b['month']).' '.$b['year'] ?></td>
            <td><?= formatCurrency($b['room_rent']) ?></td>
            <td><?= formatCurrency($b['previous_dues'] ?? 0) ?></td>
            <td><?= formatCurrency($b['misc_charges']) ?></td>
            <td><?= formatCurrency($b['mess_charges']) ?></td>
            <td><strong><?= formatCurrency($b['total_amount']) ?></strong></td>
            <td><?= statusBadge($b['payment_status']) ?></td>
            <td>
              <a href="view_bill.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-info">
                <i class="fas fa-eye"></i> View/Print
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div></div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
