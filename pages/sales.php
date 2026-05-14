<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

// Refund
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='refund') {
    $id = intval($_POST['id']);
    $sale = $conn->query("SELECT * FROM sales WHERE id=$id AND status='completed'")->fetch_assoc();
    if($sale) {
        $conn->query("UPDATE sales SET status='refunded' WHERE id=$id");
        $items = $conn->query("SELECT * FROM sale_items WHERE sale_id=$id")->fetch_all(MYSQLI_ASSOC);
        foreach($items as $item) {
            $conn->query("UPDATE products SET stock=stock+{$item['qty']} WHERE id={$item['product_id']}");
        }
        header('Location: ?msg=Sale+refunded'); exit;
    }
}

$pageTitle = 'Sales';
$activePage = 'sales';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$q = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$where = "WHERE DATE(s.created_at) BETWEEN '$from' AND '$to'";
if($q) $where .= " AND (s.invoice_no LIKE '%$q%' OR c.name LIKE '%$q%')";
$sales = $conn->query("SELECT s.*,c.name cname FROM sales s LEFT JOIN customers c ON s.customer_id=c.id $where ORDER BY s.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$totals = $conn->query("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM sales s $where AND s.status='completed'")->fetch_assoc();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div><div class="page-title">Sales History</div></div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
  <div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?= $totals['cnt'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Revenue</div><div class="stat-value"><?= money($totals['rev']) ?></div></div>
  <div class="stat-card"><div class="stat-label">Period</div><div class="stat-value" style="font-size:14px"><?= $from ?> — <?= $to ?></div></div>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="text" name="q" class="search-box" placeholder="Invoice or customer..." value="<?= e($_GET['q']??'') ?>" style="width:180px">
      <input type="date" name="from" value="<?= $from ?>" class="form-control" style="width:140px;padding:7px 10px">
      <input type="date" name="to" value="<?= $to ?>" class="form-control" style="width:140px;padding:7px 10px">
      <button class="btn btn-secondary btn-sm">Filter</button>
    </form>
    <span class="text-muted" style="font-size:12px"><?= count($sales) ?> results</span>
  </div>
  <table>
    <thead><tr><th>Invoice</th><th>Customer</th><th>Subtotal</th><th>Discount</th><th>Total</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if($sales): foreach($sales as $s): ?>
      <tr>
        <td class="text-mono text-accent" style="font-size:12px"><?= e($s['invoice_no']) ?></td>
        <td><?= e($s['cname'] ?: 'Walk-in') ?></td>
        <td class="text-mono"><?= money($s['subtotal']) ?></td>
        <td class="text-mono"><?= $s['discount']>0?'-'.money($s['discount']):'—' ?></td>
        <td class="text-mono"><strong><?= money($s['total']) ?></strong></td>
        <td><?= ucfirst($s['payment_method']) ?></td>
        <td><span class="badge <?= $s['status']==='completed'?'badge-green':'badge-red' ?>"><?= $s['status'] ?></span></td>
        <td class="text-muted" style="font-size:12px"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick="viewSale(<?= $s['id'] ?>)">View</button>
          <a class="btn btn-secondary btn-sm" href="/invoice/<?= urlencode($s['invoice_no']) ?>" target="_blank" title="Open public invoice">&#128279;</a>
          <?php if($s['status']==='completed' && isAdmin()): ?>
          <form method="POST" style="display:inline" onsubmit="return confirmDelete('Refund this sale?')">
            <input type="hidden" name="action" value="refund"><input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Refund</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="9" class="empty-state">No sales found</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Sale Detail Modal -->
<div class="modal-overlay" id="saleModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header"><span class="modal-title">Sale Details</span><span class="modal-close" onclick="closeModal('saleModal')">&times;</span></div>
    <div class="modal-body" id="saleDetail"><div class="empty-state">Loading...</div></div>
    <div class="modal-footer"><button onclick="window.print()" class="btn btn-secondary btn-sm">Print</button><button onclick="closeModal('saleModal')" class="btn btn-primary btn-sm">Close</button></div>
  </div>
</div>

<script>
function viewSale(id) {
  document.getElementById('saleDetail').innerHTML = '<div class="empty-state">Loading...</div>';
  openModal('saleModal');
  fetch('/pages/sale_detail.php?id=' + id)
    .then(r => r.text()).then(h => document.getElementById('saleDetail').innerHTML = h);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
