<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    if($act==='add') {
        $title=$conn->real_escape_string(trim($_POST['title']??''));
        $amount=floatval($_POST['amount']??0);
        $note=$conn->real_escape_string(trim($_POST['note']??''));
        if($title&&$amount>0){ $conn->query("INSERT INTO expenses (title,amount,note) VALUES ('$title',$amount,'$note')"); header('Location: ?msg=Expense+added'); exit; }
    }
    if($act==='delete'&&isAdmin()){ $id=intval($_POST['id']); $conn->query("DELETE FROM expenses WHERE id=$id"); header('Location: ?msg=Deleted'); exit; }
}
$pageTitle='Expenses'; $activePage='expenses';
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d');
$expenses=$conn->query("SELECT * FROM expenses WHERE DATE(created_at) BETWEEN '$from' AND '$to' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$total=array_sum(array_column($expenses,'amount'));
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title">Expenses</div></div>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Expense</button>
</div>
<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
  <div class="stat-card"><div class="stat-label">Total Expenses</div><div class="stat-value text-red"><?= money($total) ?></div></div>
  <div class="stat-card"><div class="stat-label">Period</div><div class="stat-value" style="font-size:14px"><?= $from ?> — <?= $to ?></div></div>
</div>
<div class="table-card">
  <div class="table-toolbar">
    <form method="GET" style="display:flex;gap:8px">
      <input type="date" name="from" value="<?= $from ?>" class="form-control" style="width:140px;padding:7px">
      <input type="date" name="to" value="<?= $to ?>" class="form-control" style="width:140px;padding:7px">
      <button class="btn btn-secondary btn-sm">Filter</button>
    </form>
  </div>
  <table>
    <thead><tr><th>#</th><th>Title</th><th>Amount</th><th>Note</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if($expenses): foreach($expenses as $i=>$ex): ?>
      <tr>
        <td class="text-muted"><?= $i+1 ?></td>
        <td><strong><?= e($ex['title']) ?></strong></td>
        <td class="text-red text-mono"><?= money($ex['amount']) ?></td>
        <td class="text-muted"><?= e($ex['note']) ?: '—' ?></td>
        <td class="text-muted" style="font-size:12px"><?= date('d/m/Y H:i', strtotime($ex['created_at'])) ?></td>
        <td><?php if(isAdmin()): ?><form method="POST" style="display:inline" onsubmit="return confirmDelete()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $ex['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Del</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6" class="empty-state">No expenses found</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width:360px">
    <div class="modal-header"><span class="modal-title">Add Expense</span><span class="modal-close" onclick="closeModal('addModal')">&times;</span></div>
    <form method="POST"><div class="modal-body">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label>Title *</label><input name="title" class="form-control" required></div>
      <div class="form-group"><label>Amount *</label><input name="amount" type="number" step="0.01" min="0.01" class="form-control" required></div>
      <div class="form-group"><label>Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div></form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
