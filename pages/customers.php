<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $addr  = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    if(($act==='add'||$act==='edit') && $name) {
        if($act==='add') { $conn->query("INSERT INTO customers (name,phone,email,address) VALUES ('$name','$phone','$email','$addr')"); header('Location: ?msg=Customer+added'); exit; }
        else { $id=intval($_POST['id']); $conn->query("UPDATE customers SET name='$name',phone='$phone',email='$email',address='$addr' WHERE id=$id"); header('Location: ?msg=Updated'); exit; }
    }
    if($act==='delete') { $id=intval($_POST['id']); $conn->query("DELETE FROM customers WHERE id=$id"); header('Location: ?msg=Deleted'); exit; }
}
$pageTitle='Customers'; $activePage='customers';
$customers = $conn->query("SELECT c.*,COUNT(s.id) sales, COALESCE(SUM(s.total),0) spent FROM customers c LEFT JOIN sales s ON c.id=s.customer_id GROUP BY c.id ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title">Customers</div></div>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Customer</button>
</div>
<div class="table-card">
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Purchases</th><th>Total Spent</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($customers as $i=>$c): ?>
      <tr>
        <td class="text-muted"><?= $i+1 ?></td>
        <td><strong><?= e($c['name']) ?></strong></td>
        <td><?= e($c['phone']) ?: '—' ?></td>
        <td><?= e($c['email']) ?: '—' ?></td>
        <td><?= $c['sales'] ?></td>
        <td class="text-accent text-mono"><?= money($c['spent']) ?></td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick='openEdit(<?= json_encode($c) ?>)'>Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirmDelete()">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Del</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php foreach([['addModal','Add Customer','add'],['editModal','Edit Customer','edit']] as [$mid,$mtitle,$mact]): ?>
<div class="modal-overlay" id="<?= $mid ?>">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><span class="modal-title"><?= $mtitle ?></span><span class="modal-close" onclick="closeModal('<?= $mid ?>')">&times;</span></div>
    <form method="POST"><div class="modal-body">
      <input type="hidden" name="action" value="<?= $mact ?>">
      <?php if($mact==='edit'): ?><input type="hidden" name="id" id="<?= $mid ?>Id"><?php endif; ?>
      <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1"><label>Name *</label><input name="name" id="<?= $mid ?>Name" class="form-control" required></div>
        <div class="form-group"><label>Phone</label><input name="phone" id="<?= $mid ?>Phone" class="form-control"></div>
        <div class="form-group"><label>Email</label><input name="email" id="<?= $mid ?>Email" type="email" class="form-control"></div>
        <div class="form-group" style="grid-column:1/-1"><label>Address</label><textarea name="address" id="<?= $mid ?>Addr" class="form-control" rows="2"></textarea></div>
      </div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('<?= $mid ?>')">Cancel</button><button type="submit" class="btn btn-primary"><?= $mact==='add'?'Add':'Update' ?></button></div></form>
  </div>
</div>
<?php endforeach; ?>

<script>
function openEdit(c) {
  ['Id','Name','Phone','Email','Addr'].forEach(f => {
    const map = {Id:'id',Name:'name',Phone:'phone',Email:'email',Addr:'address'};
    const el = document.getElementById('editModal'+f);
    if(el) el.value = c[map[f]] || '';
  });
  openModal('editModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
