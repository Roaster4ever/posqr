<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/config.php';

$pageTitle='Reports'; $activePage='reports';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$from_e = $conn->real_escape_string($from);
$to_e   = $conn->real_escape_string($to);

$revenue  = (float)$conn->query("SELECT COALESCE(SUM(total),0) v FROM sales WHERE DATE(created_at) BETWEEN '$from_e' AND '$to_e' AND status='completed'")->fetch_assoc()['v'];
$costVal  = (float)$conn->query("SELECT COALESCE(SUM(p.cost*si.qty),0) v FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN '$from_e' AND '$to_e' AND s.status='completed'")->fetch_assoc()['v'];
$expenses = (float)$conn->query("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE DATE(created_at) BETWEEN '$from_e' AND '$to_e'")->fetch_assoc()['v'];
$refunds  = (int)$conn->query("SELECT COUNT(*) v FROM sales WHERE DATE(created_at) BETWEEN '$from_e' AND '$to_e' AND status='refunded'")->fetch_assoc()['v'];
$txCount  = (int)$conn->query("SELECT COUNT(*) v FROM sales WHERE DATE(created_at) BETWEEN '$from_e' AND '$to_e' AND status='completed'")->fetch_assoc()['v'];
$profit   = $revenue - $costVal - $expenses;

$daily    = $conn->query("SELECT DATE(created_at) d, COUNT(*) cnt, SUM(total) rev FROM sales WHERE DATE(created_at) BETWEEN '$from_e' AND '$to_e' AND status='completed' GROUP BY DATE(created_at) ORDER BY d DESC")->fetch_all(MYSQLI_ASSOC);
$topProds = $conn->query("SELECT p.name,SUM(si.qty) qty,SUM(si.total) rev,SUM(p.cost*si.qty) cst FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN '$from_e' AND '$to_e' AND s.status='completed' GROUP BY si.product_id ORDER BY rev DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$pmethods = $conn->query("SELECT payment_method,COUNT(*) cnt,SUM(total) rev FROM sales WHERE DATE(created_at) BETWEEN '$from_e' AND '$to_e' AND status='completed' GROUP BY payment_method")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div><div class="page-title">Reports</div></div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="padding:7px;width:140px">
      <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="padding:7px;width:140px">
      <button type="submit" class="btn btn-primary btn-sm">Generate</button>
    </form>
    <button onclick="printReport()"   class="btn btn-secondary btn-sm">Print</button>
    <button onclick="downloadReport()" class="btn btn-secondary btn-sm">Download PDF</button>
  </div>
</div>

<div id="reportContent">

<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card"><div class="stat-label">Revenue</div><div class="stat-value"><?= money($revenue) ?></div><div class="stat-sub"><?= $txCount ?> transactions</div></div>
  <div class="stat-card"><div class="stat-label">Cost of Goods</div><div class="stat-value" style="color:var(--red)"><?= money($costVal) ?></div></div>
  <div class="stat-card"><div class="stat-label">Expenses</div><div class="stat-value" style="color:var(--orange)"><?= money($expenses) ?></div></div>
  <div class="stat-card"><div class="stat-label">Net Profit</div><div class="stat-value" style="color:<?= $profit>=0?'var(--green)':'var(--red)' ?>"><?= money($profit) ?></div><div class="stat-sub"><?= $refunds ?> refunds</div></div>
</div>

<div class="two-col mt-16">
  <div class="table-card">
    <div class="table-toolbar"><strong style="font-size:13px">By Payment Method</strong></div>
    <table>
      <thead><tr><th>Method</th><th>Transactions</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php if($pmethods): foreach($pmethods as $pm): ?>
        <tr><td><?= ucfirst($pm['payment_method']) ?></td><td><?= $pm['cnt'] ?></td><td class="text-accent text-mono"><?= money($pm['rev']) ?></td></tr>
      <?php endforeach; else: ?><tr><td colspan="3" class="empty-state">No data</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="table-card">
    <div class="table-toolbar"><strong style="font-size:13px">Top Products</strong></div>
    <table>
      <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th><th>Profit</th></tr></thead>
      <tbody>
      <?php if($topProds): foreach($topProds as $p): $gp=$p['rev']-$p['cst']; ?>
        <tr>
          <td style="font-size:12px"><?= e($p['name']) ?></td>
          <td><?= $p['qty'] ?></td>
          <td class="text-mono text-accent"><?= money($p['rev']) ?></td>
          <td class="text-mono <?= $gp>=0?'text-green':'text-red' ?>"><?= money($gp) ?></td>
        </tr>
      <?php endforeach; else: ?><tr><td colspan="4" class="empty-state">No data</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="table-card mt-16">
  <div class="table-toolbar"><strong style="font-size:13px">Daily Sales — <?= e($from) ?> to <?= e($to) ?></strong></div>
  <table>
    <thead><tr><th>Date</th><th>Transactions</th><th>Revenue</th></tr></thead>
    <tbody>
    <?php if($daily): foreach($daily as $d): ?>
      <tr>
        <td><?= date('D, d M Y', strtotime($d['d'])) ?></td>
        <td><?= $d['cnt'] ?></td>
        <td class="text-accent text-mono"><?= money($d['rev']) ?></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="3" class="empty-state">No sales in this period</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

</div><!-- #reportContent -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
var REPORT_FROM   = '<?= e($from) ?>';
var REPORT_TO     = '<?= e($to) ?>';
var SHOP_NAME     = '<?= addslashes(SHOP_NAME) ?>';
var CURRENCY      = '<?= CURRENCY ?>';
var RPT_REVENUE   = <?= $revenue ?>;
var RPT_COST      = <?= $costVal ?>;
var RPT_EXPENSES  = <?= $expenses ?>;
var RPT_PROFIT    = <?= $profit ?>;
var RPT_TX        = <?= $txCount ?>;
var RPT_REFUNDS   = <?= $refunds ?>;

var RPT_PMETHODS  = <?= json_encode($pmethods) ?>;
var RPT_PRODUCTS  = <?= json_encode($topProds) ?>;
var RPT_DAILY     = <?= json_encode($daily) ?>;

function fmt(n){ return CURRENCY + parseFloat(n).toFixed(2); }

/* ── Print ── */
function printReport() {
  var html = document.getElementById('reportContent').innerHTML;
  var w = window.open('','_blank','width=900,height=700');
  w.document.write('<!DOCTYPE html><html><head><title>Report '+REPORT_FROM+' to '+REPORT_TO+'</title>'+
    '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700&display=swap" rel="stylesheet">'+
    '<style>'+
      '*{box-sizing:border-box;margin:0;padding:0}'+
      'body{font-family:"IBM Plex Sans",sans-serif;padding:24px;font-size:13px;color:#111;background:#fff}'+
      'h2{margin-bottom:16px;font-size:18px}'+
      '.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}'+
      '.stat-card{border:1px solid #ddd;border-radius:4px;padding:12px}'+
      '.stat-label{font-size:10px;text-transform:uppercase;color:#666;margin-bottom:6px}'+
      '.stat-value{font-size:20px;font-weight:700}'+
      '.stat-sub{font-size:11px;color:#888;margin-top:3px}'+
      'table{width:100%;border-collapse:collapse;margin-bottom:20px}'+
      'th{background:#f3f4f6;padding:7px 8px;text-align:left;font-size:11px;text-transform:uppercase;border-bottom:2px solid #ddd}'+
      'td{padding:7px 8px;border-bottom:1px solid #eee;font-size:12px}'+
      '.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}'+
      '.table-card{border:1px solid #ddd;border-radius:4px;overflow:hidden;margin-bottom:16px}'+
      '.table-toolbar{padding:10px 12px;border-bottom:1px solid #ddd;background:#fafafa}'+
      '.mt-16{margin-top:16px}'+
      '@media print{body{padding:0}}'+
    '</style>'+
    '</head><body>'+
    '<h2>'+SHOP_NAME+' — Report: '+REPORT_FROM+' to '+REPORT_TO+'</h2>'+
    html+
    '<script>window.onload=function(){window.print();window.close();};<\/script>'+
    '</body></html>');
  w.document.close();
}

/* ── PDF Download ── */
function downloadReport() {
  var doc  = new window.jspdf.jsPDF({ unit:'mm', format:'a4' });
  var W    = doc.internal.pageSize.getWidth();
  var y    = 16;

  // Header
  doc.setFont('helvetica','bold'); doc.setFontSize(16); doc.setTextColor(0);
  doc.text(SHOP_NAME, W/2, y, {align:'center'}); y+=8;
  doc.setFont('helvetica','normal'); doc.setFontSize(10); doc.setTextColor(80);
  doc.text('Report Period: '+REPORT_FROM+' to '+REPORT_TO, W/2, y, {align:'center'}); y+=6;
  doc.setDrawColor(0); doc.setLineWidth(0.5); doc.line(10,y,W-10,y); y+=8;

  // Summary
  doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(0);
  doc.text('Summary', 10, y); y+=6;
  doc.setFont('helvetica','normal'); doc.setFontSize(10); doc.setTextColor(60);
  var summaryRows = [
    ['Revenue', fmt(RPT_REVENUE), 'Transactions', String(RPT_TX)],
    ['Cost of Goods', fmt(RPT_COST), 'Refunds', String(RPT_REFUNDS)],
    ['Expenses', fmt(RPT_EXPENSES), '', ''],
    ['Net Profit', fmt(RPT_PROFIT), '', '']
  ];
  doc.autoTable({
    startY: y,
    head: [['Metric','Amount','Metric','Value']],
    body: summaryRows,
    theme: 'grid',
    headStyles: { fillColor:[243,244,246], textColor:[80,80,80], fontSize:9 },
    bodyStyles: { fontSize:9 },
    margin: { left:10, right:10 }
  });
  y = doc.lastAutoTable.finalY + 10;

  // Payment methods
  if (RPT_PMETHODS.length > 0) {
    doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(0);
    doc.text('Payment Methods', 10, y); y+=4;
    doc.autoTable({
      startY: y,
      head: [['Method','Transactions','Revenue']],
      body: RPT_PMETHODS.map(function(p){ return [p.payment_method.charAt(0).toUpperCase()+p.payment_method.slice(1), p.cnt, fmt(p.rev)]; }),
      theme: 'grid',
      headStyles: { fillColor:[243,244,246], textColor:[80,80,80], fontSize:9 },
      bodyStyles: { fontSize:9 },
      margin: { left:10, right:10 }
    });
    y = doc.lastAutoTable.finalY + 10;
  }

  // Top products
  if (RPT_PRODUCTS.length > 0) {
    doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(0);
    doc.text('Top Products', 10, y); y+=4;
    doc.autoTable({
      startY: y,
      head: [['Product','Qty Sold','Revenue','Profit']],
      body: RPT_PRODUCTS.map(function(p){ return [p.name, p.qty, fmt(p.rev), fmt(p.rev-p.cst)]; }),
      theme: 'grid',
      headStyles: { fillColor:[243,244,246], textColor:[80,80,80], fontSize:9 },
      bodyStyles: { fontSize:9 },
      margin: { left:10, right:10 }
    });
    y = doc.lastAutoTable.finalY + 10;
  }

  // Daily breakdown (new page if needed)
  if (RPT_DAILY.length > 0) {
    if (y > 220) { doc.addPage(); y = 16; }
    doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(0);
    doc.text('Daily Sales Breakdown', 10, y); y+=4;
    doc.autoTable({
      startY: y,
      head: [['Date','Transactions','Revenue']],
      body: RPT_DAILY.map(function(d){
        var dt = new Date(d.d); var opts={weekday:'short',day:'2-digit',month:'short',year:'numeric'};
        return [dt.toLocaleDateString('en-GB',opts), d.cnt, fmt(d.rev)];
      }),
      theme: 'grid',
      headStyles: { fillColor:[243,244,246], textColor:[80,80,80], fontSize:9 },
      bodyStyles: { fontSize:9 },
      margin: { left:10, right:10 }
    });
  }

  doc.save('report-'+REPORT_FROM+'-to-'+REPORT_TO+'.pdf');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
