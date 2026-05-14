<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

// Handle actions
if($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    if($act === 'add' || $act === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $cat = intval($_POST['category_id'] ?? 0) ?: 'NULL';
        $price = floatval($_POST['price'] ?? 0);
        $cost = floatval($_POST['cost'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $alert = intval($_POST['low_stock_alert'] ?? 5);
        $barcode = $conn->real_escape_string(trim($_POST['barcode'] ?? ''));
        $name_e = $conn->real_escape_string($name);
        if(!$name) { header('Location: ?error=Name+required'); exit; }
        // Check for duplicate barcode — warn but allow (real-world barcodes can be shared)
        $dupMsg = '';
        if($barcode) {
            if($act === 'add') {
                $dup = $conn->query("SELECT name FROM products WHERE barcode='$barcode'")->fetch_assoc();
            } else {
                $id = intval($_POST['id']);
                $dup = $conn->query("SELECT name FROM products WHERE barcode='$barcode' AND id!=$id")->fetch_assoc();
            }
            if($dup) $dupMsg = '+Note:+Barcode+shared+with+'.urlencode($dup['name']);
        }
        if($act === 'add') {
            $conn->query("INSERT INTO products (category_id,name,barcode,price,cost,stock,low_stock_alert) VALUES ($cat,'$name_e','$barcode',$price,$cost,$stock,$alert)");
            header('Location: ?msg=Product+added'.$dupMsg); exit;
        } else {
            $id = intval($_POST['id']);
            $conn->query("UPDATE products SET category_id=$cat,name='$name_e',barcode='$barcode',price=$price,cost=$cost,stock=$stock,low_stock_alert=$alert WHERE id=$id");
            header('Location: ?msg=Product+updated'.$dupMsg); exit;
        }
    }
    if($act === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM products WHERE id=$id");
        header('Location: ?msg=Product+deleted'); exit;
    }
}

$pageTitle = 'Products';
$activePage = 'products';
$search = trim($_GET['q'] ?? '');
$where = $search ? "WHERE p.name LIKE '%".($conn->real_escape_string($search))."%' OR p.barcode LIKE '%$search%'" : '';
$products = $conn->query("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
// Find which barcodes are shared across multiple products
$dupBarcodes = [];
$dupResult = $conn->query("SELECT barcode FROM products WHERE barcode!='' GROUP BY barcode HAVING COUNT(*)>1");
while($row = $dupResult->fetch_assoc()) $dupBarcodes[] = $row['barcode'];
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div><div class="page-title">Products</div></div>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <form method="GET" style="display:flex;gap:8px">
      <input type="text" name="q" class="search-box" placeholder="Search name or barcode..." value="<?= e($search) ?>">
      <button class="btn btn-secondary btn-sm" type="submit">Search</button>
      <?php if($search): ?><a href="?" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
    </form>
    <span class="text-muted" style="font-size:12px"><?= count($products) ?> products</span>
  </div>
  <table>
    <thead><tr><th>Name</th><th>Cost Price</th><th>Sale Price</th><th>Actions</th></tr></thead>
    <tbody>
    <?php
    $today     = date('Y-m-d');
    if($products): foreach($products as $i=>$p):
    ?>
      <tr style="cursor:pointer" onclick='showDetail(<?= json_encode($p) ?>)' title="Click for full details">
        <td>
          <strong><?= e($p['name']) ?></strong>
          <?php if($p['barcode'] && in_array($p['barcode'], $dupBarcodes)): ?>
            <span class="badge badge-orange" style="font-size:9px;margin-left:4px">SHARED</span>
          <?php endif; ?>
        </td>
        <td class="text-mono text-muted"><?= money($p['cost']) ?></td>
        <td class="text-accent text-mono"><?= money($p['price']) ?></td>
        <td>
—
        </td>
        <td onclick="event.stopPropagation()">
          <?php if(isAdmin()): ?>
          <button class="btn btn-secondary btn-sm" onclick='openEdit(<?= json_encode($p) ?>)'>Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirmDelete()">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Del</button>
          </form>
          <?php else: ?><span class="text-muted" style="font-size:12px">View only</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="5" class="empty-state">No products found</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit Product</span><span class="modal-close" onclick="closeModal('editModal')">&times;</span></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1"><label>Product Name *</label><input name="name" id="editName" class="form-control" required></div>
          <div class="form-group"><label>Category</label>
            <select name="category_id" id="editCat" class="form-control">
              <option value="">— Select —</option>
              <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Barcode</label><input name="barcode" id="editBarcode" class="form-control"></div>
          <div class="form-group"><label>Cost Price</label><input name="price" id="editPrice" type="number" step="0.01" min="0" class="form-control"></div>
          <div class="form-group"><label>Sale Price</label><input name="cost" id="editCost" type="number" step="0.01" min="0" class="form-control" required></div>
          <div class="form-group"><label>Stock</label><input name="stock" id="editStock" type="number" min="0" class="form-control"></div>
          <div class="form-group"><label>Low Stock Alert</label><input name="low_stock_alert" id="editAlert" type="number" min="0" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
    </form>
  </div>
</div>

<script>
function openEdit(p) {
  document.getElementById('editId').value = p.id;
  document.getElementById('editName').value = p.name;
  document.getElementById('editCat').value = p.category_id || '';
  document.getElementById('editBarcode').value = p.barcode || '';
  document.getElementById('editPrice').value = p.price;
  document.getElementById('editCost').value = p.cost;
  document.getElementById('editStock').value = p.stock;
  document.getElementById('editAlert').value = p.low_stock_alert;
  openModal('editModal');
}
</script>


<!-- Product Detail Modal -->
<div class="modal-overlay" id="detailModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">Product Details</span>
      <span class="modal-close" onclick="document.getElementById('detailModal').classList.remove('open')">&times;</span>
    </div>
    <div class="modal-body" id="detailBody" style="padding:20px"></div>
    <div class="modal-footer"><button onclick="document.getElementById('detailModal').classList.remove('open')" class="btn btn-secondary">Close</button></div>
  </div>
</div>
<script>
function showDetail(p) {
  var stockBadge = p.stock<=0 ? ' <span class="badge badge-red" style="font-size:9px">OUT</span>'
    : (p.stock<=p.low_stock_alert ? ' <span class="badge badge-orange" style="font-size:9px">LOW</span>' : '');
  function row(l,v){ return '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px"><span style="color:var(--text2)">'+l+'</span><span style="font-weight:600">'+v+'</span></div>'; }
  document.getElementById('detailBody').innerHTML =
    '<div style="margin-bottom:14px"><div style="font-size:18px;font-weight:700">'+p.name+'</div>'+
    '<div style="font-size:12px;color:var(--text2);margin-top:3px">'+(p.cat_name||'No category')+'</div></div>'+
    row('Barcode',         p.barcode||'—')+
    row('Price',           '<?= CURRENCY ?>'+parseFloat(p.price).toFixed(2))+
    row('Cost',            '<?= CURRENCY ?>'+parseFloat(p.cost).toFixed(2))+
    row('Stock',           p.stock+stockBadge)+
    row('Low Stock Alert', p.low_stock_alert);

  document.getElementById('detailModal').classList.add('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
