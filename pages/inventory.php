<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

// ── Stock-in for existing product ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stock_in') {
    $pid = intval($_POST['product_id']);
    $qty = intval($_POST['qty_added']);
    if ($pid && $qty > 0) {
        $conn->query("UPDATE products SET stock=stock+$qty WHERE id=$pid");
        $conn->query("INSERT INTO inventory_log (product_id,qty_added,note) VALUES ($pid,$qty,'Stock in'");
        header('Location: ?msg=Stock+updated'); exit;
    }
    header('Location: ?error=Invalid+data'); exit;
}

// ── Create new product ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_product') {
    $name    = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $barcode = $conn->real_escape_string(trim($_POST['barcode'] ?? ''));
    $cat     = intval($_POST['category_id'] ?? 0) ?: 'NULL';
    $price   = floatval($_POST['price'] ?? 0);
    $cost    = floatval($_POST['cost'] ?? 0);
    $stock   = intval($_POST['stock'] ?? 0);
    $alert   = intval($_POST['low_stock_alert'] ?? 5);
    if (!$name) { header('Location: ?error=Name+required'); exit; }
    $conn->query("INSERT INTO products (category_id,name,barcode,price,cost,stock,low_stock_alert)
                  VALUES ($cat,'$name','$barcode',$price,$cost,$stock,$alert)");
    $newId = $conn->insert_id;
    if ($stock > 0) {
        $conn->query("INSERT INTO inventory_log (product_id,qty_added,note) VALUES ($newId,$stock,'Initial stock')");
    }
    header('Location: ?msg=Product+created'); exit;
}

$pageTitle  = 'Inventory';
$activePage = 'inventory';

$search   = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$where    = $search ? "WHERE p.name LIKE '%$search%' OR p.barcode LIKE '%$search%'" : '';
$products   = $conn->query("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$today     = date('Y-m-d');

include __DIR__ . '/../includes/header.php';
?>


<!-- ── BARCODE ENTRY MODAL ── -->
<div class="modal-overlay" id="scanModal">
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title">Add / Restock</span>
      <span class="modal-close" onclick="closeScan()">&times;</span>
    </div>
    <div class="modal-body" style="padding:16px">
      <div style="font-size:13px;color:var(--text2);margin-bottom:12px">Enter a barcode to restock an existing product or add a new one.</div>
      <div style="display:flex;gap:6px">
        <input type="text" id="invManualBarcode" class="form-control" placeholder="Type barcode then Enter" style="font-size:13px" autofocus>
        <button class="btn btn-primary btn-sm" onclick="submitManual()">Go</button>
      </div>
    </div>
    <div class="modal-footer"><button onclick="closeScan()" class="btn btn-secondary">Cancel</button></div>
  </div>
</div>

<!-- ── NEW PRODUCT MODAL ── -->
<div class="modal-overlay" id="newProductModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title">Add New Product</span>
      <span class="modal-close" onclick="closeModal('newProductModal')">&times;</span>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="new_product">
        <input type="hidden" name="barcode" id="npBarcode">
        <div id="npBarcodeInfo" style="display:none;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:8px 12px;margin-bottom:14px;font-size:12px;color:var(--text2)">
          Barcode: <strong id="npBarcodeShow" class="text-accent text-mono"></strong>
        </div>
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label>Product Name *</label>
            <input name="name" class="form-control" required placeholder="Enter product name">
          </div>
          <div class="form-group">
            <label>Barcode</label>
            <input name="barcode" id="npBarcodeManual" class="form-control" placeholder="Optional">
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control">
              <option value="">— Select —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Selling Price</label>
            <input name="price" type="number" step="0.01" min="0" value="0" class="form-control">
          </div>
          <div class="form-group">
            <label>Cost Price</label>
            <input name="cost" type="number" step="0.01" min="0" value="0" class="form-control">
          </div>
          <div class="form-group">
            <label>Initial Stock</label>
            <input name="stock" type="number" min="0" value="0" class="form-control">
          </div>
          <div class="form-group">
            <label>Low Stock Alert</label>
            <input name="low_stock_alert" type="number" min="0" value="5" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ── STOCK PICKER MODAL (multiple matches) ── -->
<div class="modal-overlay" id="stockPickerModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title">Select Product to Restock</span>
      <span class="modal-close" onclick="closeModal('stockPickerModal')">&times;</span>
    </div>
    <div class="modal-body" style="padding:14px">
      <div style="font-size:12px;color:var(--text2);margin-bottom:10px">
        Barcode <strong id="spBarcode" class="text-accent text-mono"></strong> matches multiple products:
      </div>
      <div id="spList"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('stockPickerModal')" class="btn btn-secondary btn-sm">Cancel</button>
    </div>
  </div>
</div>

<!-- ── STOCK-IN MODAL ── -->
<div class="modal-overlay" id="stockInModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title">Add Stock</span>
      <span class="modal-close" onclick="closeModal('stockInModal')">&times;</span>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="stock_in">
        <input type="hidden" name="product_id" id="siProductId">
        <div style="background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:10px 12px;margin-bottom:14px">
          <div style="font-size:14px;font-weight:700" id="siProductName"></div>
          <div style="font-size:11px;color:var(--text2);margin-top:3px">
            Current stock: <span id="siCurrentStock" class="text-accent text-mono"></span>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label>Quantity to Add *</label>
            <input name="qty_added" id="siQty" type="number" min="1" value="1" class="form-control" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('stockInModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<!-- ── PRODUCT DETAIL MODAL ── -->
<div class="modal-overlay" id="productDetailModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">Product Details</span>
      <span class="modal-close" onclick="closeModal('productDetailModal')">&times;</span>
    </div>
    <div class="modal-body" id="productDetailBody" style="padding:20px"></div>
    <div class="modal-footer">
      <button onclick="closeModal('productDetailModal')" class="btn btn-secondary">Close</button>
    </div>
  </div>
</div>

<!-- ── PAGE HEADER ── -->
<div class="page-header">
  <div>
    <div class="page-title">Inventory</div>
    <div class="page-subtitle"><?= count($products) ?> products</div>
  </div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-primary" onclick="openInvScanner()">Add / Restock</button>
  </div>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:14px;display:flex;gap:8px">
  <input type="text" name="q" class="search-box" placeholder="Search name or barcode..." value="<?= e($_GET['q'] ?? '') ?>" style="width:240px">
  <button class="btn btn-secondary btn-sm" type="submit">Search</button>
  <?php if ($search): ?><a href="?" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
</form>

<!-- Table -->
<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Cost</th>
        <th>Price</th>
        <th>Stock</th>

      </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr style="cursor:pointer" onclick="showProductDetail(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" title="Click for details">
        <td>
          <strong><?= e($p['name']) ?></strong>
          <?php if ($p['stock'] <= 0): ?>
            <span class="badge badge-red" style="font-size:9px;margin-left:4px">OUT</span>
          <?php elseif ($p['stock'] <= $p['low_stock_alert']): ?>
            <span class="badge badge-orange" style="font-size:9px;margin-left:4px">LOW</span>
          <?php endif; ?>
        </td>
        <td class="text-mono text-muted"><?= money($p['cost']) ?></td>
        <td class="text-mono text-accent"><?= money($p['price']) ?></td>
        <td class="text-mono <?= $p['stock'] <= 0 ? 'stock-out' : ($p['stock'] <= $p['low_stock_alert'] ? 'stock-low' : '') ?>">
          <?= $p['stock'] ?>
        </td>

      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <tr><td colspan="5" class="empty-state">No products found</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
var INV_PRODUCTS   = <?= json_encode(array_values($products)) ?>;
var INV_CATEGORIES = <?= json_encode(array_values($categories)) ?>;
var CURRENCY       = '<?= addslashes(CURRENCY) ?>';



/* ── Inventory barcode entry wiring ── */
function openInvScanner() {
  document.getElementById('invManualBarcode').value = '';
  openModal('scanModal');
  setTimeout(function() { document.getElementById('invManualBarcode').focus(); }, 100);
}

function closeScan() {
  closeModal('scanModal');
}

function submitManual() {
  var code = document.getElementById('invManualBarcode').value.trim();
  if (!code) { alert('Please enter a barcode'); return; }
  closeModal('scanModal');
  processInvBarcode(code);
}

document.getElementById('invManualBarcode').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') submitManual();
});

/* ── Core barcode processing ── */

function processInvBarcode(code) {
  code = (code || '').trim();
  if (!code) return;

  var matches = INV_PRODUCTS.filter(function(p) {
    return p.barcode && p.barcode.trim().toLowerCase() === code.toLowerCase();
  });

  if (matches.length === 0) {
    // New product
    document.getElementById('npBarcode').value              = code;
    document.getElementById('npBarcodeManual').value        = code;
    document.getElementById('npBarcodeShow').textContent    = code;
    document.getElementById('npBarcodeInfo').style.display  = 'block';
    openModal('newProductModal');

  } else if (matches.length === 1) {
    openStockIn(matches[0]);

  } else {
    // Multiple matches — show picker
    document.getElementById('spBarcode').textContent = code;
    var html = '';
    matches.forEach(function(p, i) {
      html += '<div onclick="pickProduct(' + i + ')" ' +
        'style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;' +
        'border:1px solid var(--border2);border-radius:4px;margin-bottom:8px;' +
        'background:var(--bg3);cursor:pointer">' +
        '<div>' +
          '<div style="font-weight:600;font-size:13px">' + p.name + '</div>' +
          '<div style="font-size:11px;color:var(--text2);margin-top:2px">Stock: ' + p.stock + '</div>' +
        '</div>' +
        '<div style="font-size:14px;font-weight:700;font-family:var(--mono);color:var(--accent)">' +
          CURRENCY + parseFloat(p.price).toFixed(2) +
        '</div>' +
      '</div>';
    });
    document.getElementById('spList').innerHTML = html;
    window._pickerMatches = matches;
    openModal('stockPickerModal');
  }
}

function pickProduct(i) {
  closeModal('stockPickerModal');
  openStockIn(window._pickerMatches[i]);
}

function openStockIn(product) {
  if (!product) return;
  document.getElementById('siProductId').value          = product.id;
  document.getElementById('siProductName').textContent  = product.name;
  document.getElementById('siCurrentStock').textContent = product.stock;
  document.getElementById('siQty').value                = 1;
  openModal('stockInModal');
}

/* ── Product detail popup ── */
function showProductDetail(p) {
  var stockBadge = p.stock <= 0
    ? ' <span class="badge badge-red" style="font-size:9px">OUT</span>'
    : (parseInt(p.stock) <= parseInt(p.low_stock_alert)
        ? ' <span class="badge badge-orange" style="font-size:9px">LOW</span>' : '');

  function row(l, v) {
    return '<div style="display:flex;justify-content:space-between;padding:8px 0;' +
      'border-bottom:1px solid var(--border);font-size:13px">' +
      '<span style="color:var(--text2)">' + l + '</span>' +
      '<span style="font-weight:600">' + v + '</span></div>';
  }

  document.getElementById('productDetailBody').innerHTML =
    '<div style="margin-bottom:14px">' +
      '<div style="font-size:18px;font-weight:700">' + (p.name || '') + '</div>' +
      '<div style="font-size:12px;color:var(--text2);margin-top:3px">' + (p.cat_name || 'No category') + '</div>' +
    '</div>' +
    row('Barcode',         p.barcode || '—') +
    row('Selling Price',   CURRENCY + parseFloat(p.price || 0).toFixed(2)) +
    row('Cost Price',      CURRENCY + parseFloat(p.cost  || 0).toFixed(2)) +
    row('Stock',           p.stock + stockBadge) +
    row('Low Stock Alert', p.low_stock_alert) +
    row('Low Stock Alert', p.low_stock_alert);

  openModal('productDetailModal');
}


</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
