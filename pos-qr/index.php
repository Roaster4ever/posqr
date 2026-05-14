<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Stats
$today = date('Y-m-d');
$r = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM sales WHERE DATE(created_at)='$today' AND status='completed'")->fetch_assoc();
$todaySales = $r['c']; $todayRevenue = $r['t'];

$r2 = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM sales WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status='completed'")->fetch_assoc();
$monthSales = $r2['c']; $monthRevenue = $r2['t'];

$totalProducts = $conn->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'];
$lowStock = $conn->query("SELECT COUNT(*) c FROM products WHERE stock <= low_stock_alert AND stock > 0")->fetch_assoc()['c'];
$outOfStock = $conn->query("SELECT COUNT(*) c FROM products WHERE stock = 0")->fetch_assoc()['c'];
$totalCustomers = $conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'];

// Last 7 days chart
$chartData = [];
for($i=6;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $r3 = $conn->query("SELECT COALESCE(SUM(total),0) t FROM sales WHERE DATE(created_at)='$d' AND status='completed'")->fetch_assoc();
    $chartData[] = ['date'=>date('D',strtotime($d)), 'total'=>(float)$r3['t']];
}
$maxVal = max(array_column($chartData,'total')) ?: 1;

// Recent sales
$recentSales = $conn->query("SELECT s.*,c.name cname FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Top products
$topProducts = $conn->query("SELECT p.name, SUM(si.qty) qty, SUM(si.total) rev FROM sale_items si JOIN products p ON si.product_id=p.id GROUP BY si.product_id ORDER BY qty DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/header.php';
?>
<head>
<link rel="icon" type="image/x-icon" href="favicon.ico?v=2">
</head>
<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle"><?= date('l, F j Y') ?></div>
  </div>
  <a href="/pages/pos.php" class="btn btn-primary">⊞ Open POS</a>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value"><?= money($todayRevenue) ?></div>
    <div class="stat-sub"><?= $todaySales ?> transactions</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Month Revenue</div>
    <div class="stat-value"><?= money($monthRevenue) ?></div>
    <div class="stat-sub"><?= $monthSales ?> transactions</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Products</div>
    <div class="stat-value"><?= $totalProducts ?></div>
    <div class="stat-sub <?= $lowStock>0?'stock-low':'' ?>"><?= $lowStock ?> low stock, <?= $outOfStock ?> out</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Customers</div>
    <div class="stat-value"><?= $totalCustomers ?></div>
    <div class="stat-sub">registered</div>
  </div>
</div>

<div class="two-col mt-16">
  <!-- Chart -->
  <div class="chart-card">
    <div class="chart-title">Sales — Last 7 Days</div>
    <div class="bar-chart">
      <?php foreach($chartData as $d): $h = max(4, round(($d['total']/$maxVal)*100)); ?>
      <div class="bar-wrap">
        <div class="bar-val"><?= $d['total']>0?money($d['total']):'' ?></div>
        <div class="bar" style="height:<?= $h ?>px"></div>
        <div class="bar-label"><?= $d['date'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top Products -->
  <div class="chart-card">
    <div class="chart-title">Top Products</div>
    <?php if($topProducts): ?>
    <table style="width:100%">
      <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach($topProducts as $p): ?>
        <tr>
          <td style="font-size:12px"><?= e($p['name']) ?></td>
          <td class="text-mono" style="font-size:12px"><?= $p['qty'] ?></td>
          <td class="text-accent text-mono" style="font-size:12px"><?= money($p['rev']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><div class="empty-state" style="padding:20px">No sales yet</div><?php endif; ?>
  </div>
</div>

<!-- Recent Sales -->
<div class="table-card mt-16">
  <div class="table-toolbar">
    <strong style="font-size:13px">Recent Sales</strong>
    <a href="/pages/sales.php" class="btn btn-secondary btn-sm">View All</a>
  </div>
  <table>
    <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Time</th></tr></thead>
    <tbody>
    <?php if($recentSales): foreach($recentSales as $s): ?>
      <tr>
        <td class="text-mono text-accent"><?= e($s['invoice_no']) ?></td>
        <td><?= e($s['cname'] ?: 'Walk-in') ?></td>
        <td class="text-mono"><?= money($s['total']) ?></td>
        <td><?= ucfirst($s['payment_method']) ?></td>
        <td><span class="badge <?= $s['status']==='completed'?'badge-green':'badge-red' ?>"><?= $s['status'] ?></span></td>
        <td class="text-muted"><?= date('H:i', strtotime($s['created_at'])) ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6" class="empty-state">No sales yet today</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
