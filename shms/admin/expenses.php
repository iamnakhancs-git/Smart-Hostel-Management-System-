<?php
require_once __DIR__ . '/../includes/enhancements.php';
requireLogin(ROLE_ADMIN);
$ready = shmsReady(['hostel_expenses']);
$error = '';
$form = ['id'=>0, 'title'=>'', 'category'=>'', 'description'=>'', 'amount'=>'', 'expense_date'=>date('Y-m-d'), 'remarks'=>''];
if ($ready) {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            shmsCheckPost();
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id === false || $id < 0) { throw new InvalidArgumentException('Invalid expense.'); }
            if ($id && !shmsValue('SELECT COUNT(*) FROM hostel_expenses WHERE id=?', [$id])) { throw new InvalidArgumentException('Expense no longer exists.'); }
            $action = shmsPost('action');
            if ($action === 'delete' && $id) {
                shmsQuery('DELETE FROM hostel_expenses WHERE id=?', [$id])->close();
            } elseif ($action === 'save') {
                $form = ['id'=>$id, 'title'=>shmsText('title',200), 'category'=>shmsText('category',80),
                    'description'=>shmsText('description',5000,false), 'amount'=>shmsPost('amount'),
                    'expense_date'=>shmsPost('expense_date'), 'remarks'=>shmsText('remarks',500,false)];
                if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $form['amount']) || (float)$form['amount'] <= 0) {
                    throw new InvalidArgumentException('Enter a positive amount with at most two decimal places.');
                }
                shmsDate($form['expense_date']);
                $values = [$form['title'],$form['category'],$form['description'],$form['amount'],$form['expense_date'],$form['remarks']];
                if ($id) {
                    $values[]=$id;
                    shmsQuery('UPDATE hostel_expenses SET title=?, category=?, description=?, amount=?, expense_date=?, remarks=? WHERE id=?', $values)->close();
                } else {
                    $values[]=(int)$_SESSION['user_id'];
                    shmsQuery('INSERT INTO hostel_expenses (title,category,description,amount,expense_date,remarks,created_by) VALUES (?,?,?,?,?,?,?)', $values)->close();
                }
            } else { throw new InvalidArgumentException('Invalid action.'); }
            setFlash('success','Expense records updated.'); header('Location: expenses.php'); exit;
        } elseif (isset($_GET['edit'])) {
            $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
            if (!$id || $id < 1) { throw new InvalidArgumentException('Invalid expense.'); }
            $rows = shmsRows('SELECT * FROM hostel_expenses WHERE id=?', [$id]);
            if (!$rows) { throw new InvalidArgumentException('Expense not found.'); }
            $form = $rows[0];
        }
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
    catch (mysqli_sql_exception $e) { $error='Unable to save expense. Check database setup and try again.'; }
}
try { $range=shmsPeriod(['month'=>$_GET['month'] ?? date('Y-m')]); }
catch (InvalidArgumentException $e) { $error=$e->getMessage(); $range=shmsPeriod([]); }
shmsPageStart('Hostel Expenses', ROLE_ADMIN);
if (!$ready) { shmsSetupMessage(); shmsPageEnd(); exit; }
if ($error) { echo '<div class="alert alert-danger">'.h($error).'</div>'; }
$expenses=shmsRows('SELECT * FROM hostel_expenses WHERE expense_date>=? AND expense_date<? ORDER BY expense_date DESC,id DESC',[$range['start'],$range['end']]);
$total=shmsValue('SELECT COALESCE(SUM(amount),0) FROM hostel_expenses WHERE expense_date>=? AND expense_date<?',[$range['start'],$range['end']]);
?>
<div class="row"><div class="col-lg-4"><div class="card card-primary"><div class="card-header"><h2 class="card-title"><?= $form['id'] ? 'Edit Expense' : 'Add Expense' ?></h2></div><form method="post"><div class="card-body">
  <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
  <div class="form-group"><label for="title">Title</label><input id="title" name="title" class="form-control" maxlength="200" value="<?= h($form['title']) ?>" required></div>
  <div class="form-group"><label for="category">Category</label><input id="category" name="category" class="form-control" list="categories" maxlength="80" value="<?= h($form['category']) ?>" required><datalist id="categories"><option>Utilities</option><option>Maintenance</option><option>Staff</option><option>Mess supplies</option><option>Other</option></datalist></div>
  <div class="form-group"><label for="amount">Amount (Rs.)</label><input id="amount" name="amount" class="form-control" type="number" min="0.01" max="9999999999.99" step="0.01" value="<?= h($form['amount']) ?>" required></div>
  <div class="form-group"><label for="expense_date">Expense date</label><input id="expense_date" name="expense_date" class="form-control" type="date" value="<?= h($form['expense_date']) ?>" required></div>
  <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" class="form-control" maxlength="5000" rows="3"><?= h($form['description']) ?></textarea></div>
  <div class="form-group"><label for="remarks">Remarks (optional)</label><input id="remarks" name="remarks" class="form-control" maxlength="500" value="<?= h($form['remarks']) ?>"></div>
  <button name="action" value="save" class="btn btn-primary">Save Expense</button> <a href="expenses.php" class="btn btn-default">Clear / New</a>
</div></form></div></div><div class="col-lg-8"><div class="card">
<div class="card-header"><h2 class="card-title">Expense Records</h2></div><div class="card-body">
<form method="get" class="form-inline mb-3"><label for="month" class="mr-2">Month</label><input id="month" type="month" class="form-control mr-2" name="month" value="<?= h(substr($range['start'],0,7)) ?>" required><button class="btn btn-primary">View</button></form>
<p class="h4">Total: Rs. <?= number_format((float)$total,2) ?></p><a href="reports.php?type=expenses&amp;period=monthly&amp;month=<?= h(substr($range['start'],0,7)) ?>">Open printable expense report</a>
<div class="table-responsive mt-3"><table class="table table-striped"><thead><tr><th>Date</th><th>Title / Category</th><th>Amount (Rs.)</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($expenses as $expense): ?><tr><td><?= h($expense['expense_date']) ?></td><td><?= h($expense['title']) ?><small class="d-block text-muted"><?= h($expense['category']) ?></small></td><td><?= number_format((float)$expense['amount'],2) ?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="expenses.php?edit=<?= (int)$expense['id'] ?>">Edit</a> <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="id" value="<?= (int)$expense['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense?')">Delete</button></form></td></tr><?php endforeach; ?>
<?php if (!$expenses): ?><tr><td colspan="4" class="text-center text-muted">No expense records for this month.</td></tr><?php endif; ?>
</tbody></table></div></div></div></div></div>
<?php shmsPageEnd(); ?>
