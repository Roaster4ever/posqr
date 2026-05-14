<?php
// ─────────────────────────────────────────────────────────────
//  invoice.php  —  PUBLIC invoice viewer
//  Route: /invoice/{invoice_no}  →  rewritten to /invoice.php?no={invoice_no}
//
//  No authentication required — anyone with the link can view.
//  Sensitive data (customer phone/email) is intentionally omitted.
// ─────────────────────────────────────────────────────────────

// Load config (DB + helpers) but NOT auth.php
require_once __DIR__ . '/includes/config.php';

// ── Resolve invoice number ────────────────────────────────────
// The Vercel rewrite sends /invoice/INV-20240501-AB3F2 → ?no=INV-20240501-AB3F2
$raw = trim($_GET['no'] ?? '');
// Whitelist: letters, digits, hyphens only
$no  = preg_replace('/[^A-Za-z0-9\-]/', '', $raw);

if ($no === '') {
    http_response_code(400);
    $error = 'No invoice number provided.';
} else {
    $esc  = $conn->real_escape_string($no);
    $sale = $conn->query(
        "SELECT s.*, c.name AS cname
         FROM   sales s
         LEFT JOIN customers c ON s.customer_id = c.id
         WHERE  s.invoice_no = '$esc'
         LIMIT  1"
    )->fetch_assoc();

    if (!$sale) {
        http_response_code(404);
        $error = "Invoice <strong>" . e($no) . "</strong> was not found.";
    } else {
        $items  = $conn->query(
            "SELECT * FROM sale_items WHERE sale_id = {$sale['id']} ORDER BY id"
        )->fetch_all(MYSQLI_ASSOC);
        $qr_src = invoiceQrSrc($sale['invoice_no'], 120);
        $pub_url = invoicePublicUrl($sale['invoice_no']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= isset($sale) ? e($sale['invoice_no']) : 'Not Found' ?> — <?= e(SHOP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --accent: #6c47ff;
    --bg:     #f4f4f8;
    --card:   #ffffff;
    --border: #e5e7eb;
    --text1:  #111827;
    --text2:  #6b7280;
    --green:  #16a34a;
    --red:    #dc2626;
    --mono:   'IBM Plex Mono', monospace;
  }

  body {
    font-family: 'IBM Plex Sans', sans-serif;
    background: var(--bg);
    color: var(--text1);
    min-height: 100vh;
    padding: 24px 16px 48px;
  }

  /* ── Top bar ── */
  .top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 680px;
    margin: 0 auto 24px;
    gap: 12px;
    flex-wrap: wrap;
  }
  .brand {
    font-size: 18px;
    font-weight: 800;
    color: var(--text1);
    letter-spacing: -.02em;
  }
  .brand span { color: var(--accent); }
  .print-btn {
    padding: 8px 18px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: .02em;
  }
  .print-btn:hover { opacity: .88; }

  /* ── Invoice card ── */
  .invoice-card {
    max-width: 680px;
    margin: 0 auto;
    background: var(--card);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    overflow: hidden;
  }

  /* ── Header stripe ── */
  .inv-header {
    background: var(--text1);
    color: #fff;
    padding: 28px 32px 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
  }
  .inv-header-left .shop-name {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.02em;
  }
  .inv-header-left .shop-sub {
    font-size: 11px;
    letter-spacing: .12em;
    text-transform: uppercase;
    opacity: .55;
    margin-top: 4px;
  }
  .inv-header-right { text-align: right; }
  .inv-no {
    font-family: var(--mono);
    font-size: 13px;
    font-weight: 600;
    background: rgba(255,255,255,.12);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
    margin-bottom: 6px;
  }
  .inv-date { font-size: 12px; opacity: .6; }

  /* ── Status badge ── */
  .status-row {
    padding: 10px 32px;
    background: #f9fafb;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
  }
  .badge-green { background: #dcfce7; color: var(--green); }
  .badge-red   { background: #fee2e2; color: var(--red);   }

  /* ── Meta row ── */
  .inv-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border-bottom: 1px solid var(--border);
  }
  .meta-cell {
    padding: 16px 32px;
    border-right: 1px solid var(--border);
  }
  .meta-cell:last-child { border-right: none; }
  .meta-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text2);
    margin-bottom: 4px;
  }
  .meta-value { font-size: 14px; font-weight: 600; }

  /* ── Items table ── */
  .inv-items { padding: 0 32px; }
  .inv-items table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin: 20px 0;
  }
  .inv-items th {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text2);
    padding: 8px;
    border-bottom: 2px solid var(--border);
    text-align: left;
  }
  .inv-items th:not(:first-child) { text-align: right; }
  .inv-items td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
  }
  .inv-items td:not(:first-child) { text-align: right; font-family: var(--mono); font-size: 12px; }
  .item-name { font-weight: 500; }
  .item-qty  { color: var(--text2); font-size: 11px; margin-top: 2px; }

  /* ── Totals ── */
  .inv-totals {
    padding: 0 32px 24px;
    display: flex;
    justify-content: flex-end;
  }
  .totals-box { width: 260px; font-size: 13px; }
  .total-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    color: var(--text2);
  }
  .total-row.grand {
    border-top: 2px solid var(--text1);
    margin-top: 6px;
    padding-top: 10px;
    font-size: 17px;
    font-weight: 800;
    color: var(--text1);
    font-family: var(--mono);
  }

  /* ── QR + footer ── */
  .inv-footer {
    border-top: 1px dashed var(--border);
    padding: 24px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
  }
  .qr-block { text-align: center; }
  .qr-block img {
    display: block;
    width: 110px;
    height: 110px;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 4px;
    background: #fff;
  }
  .qr-label {
    font-size: 10px;
    color: var(--text2);
    margin-top: 6px;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 600;
  }
  .footer-text {
    flex: 1;
    font-size: 12px;
    color: var(--text2);
    line-height: 1.7;
  }
  .footer-text strong { color: var(--text1); }
  .footer-text .thanks {
    font-size: 14px;
    font-weight: 700;
    color: var(--text1);
    margin-bottom: 6px;
  }

  /* ── Error state ── */
  .error-card {
    max-width: 480px;
    margin: 60px auto;
    background: var(--card);
    border-radius: 16px;
    padding: 48px 40px;
    text-align: center;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
  }
  .error-card .icon { font-size: 48px; margin-bottom: 16px; }
  .error-card h2 { font-size: 20px; margin-bottom: 10px; }
  .error-card p  { color: var(--text2); font-size: 14px; line-height: 1.6; }

  /* ── Print styles ── */
  @media print {
    body { background: #fff; padding: 0; }
    .top-bar, .print-btn { display: none !important; }
    .invoice-card { box-shadow: none; border-radius: 0; }
    .inv-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }

  @media (max-width: 540px) {
    .inv-header { padding: 20px; }
    .inv-meta   { grid-template-columns: 1fr; }
    .meta-cell  { border-right: none; border-bottom: 1px solid var(--border); }
    .inv-items, .inv-totals, .inv-footer { padding-left: 16px; padding-right: 16px; }
    .status-row { padding: 10px 16px; }
    .inv-footer { flex-direction: column; align-items: center; text-align: center; }
  }
</style>
</head>
<body>

<?php if (isset($error)): ?>
<!-- ── Error ──────────────────────────────────────────────── -->
<div class="error-card">
  <div class="icon">🔍</div>
  <h2>Invoice Not Found</h2>
  <p><?= $error ?></p>
  <p style="margin-top:14px;font-size:12px;">
    Check the link or contact <?= e(SHOP_NAME) ?> for assistance.
  </p>
</div>

<?php else: ?>
<!-- ── Top bar ─────────────────────────────────────────────── -->
<div class="top-bar">
  <div class="brand"><?= e(SHOP_NAME) ?> <span>◈</span></div>
  <button class="print-btn" onclick="window.print()">🖨 Print Invoice</button>
</div>

<!-- ── Invoice card ────────────────────────────────────────── -->
<div class="invoice-card" id="invoicePrint">

  <!-- Header -->
  <div class="inv-header">
    <div class="inv-header-left">
      <div class="shop-name"><?= e(SHOP_NAME) ?></div>
      <div class="shop-sub">Sales Invoice</div>
    </div>
    <div class="inv-header-right">
      <div class="inv-no"><?= e($sale['invoice_no']) ?></div>
      <div class="inv-date"><?= date('d M Y, H:i', strtotime($sale['created_at'])) ?></div>
    </div>
  </div>

  <!-- Status -->
  <div class="status-row">
    <span class="badge <?= $sale['status'] === 'completed' ? 'badge-green' : 'badge-red' ?>">
      <?= ucfirst(e($sale['status'])) ?>
    </span>
    <span style="font-size:12px;color:var(--text2)">
      Payment: <?= ucfirst(e($sale['payment_method'])) ?>
    </span>
  </div>

  <!-- Meta -->
  <div class="inv-meta">
    <div class="meta-cell">
      <div class="meta-label">Customer</div>
      <div class="meta-value"><?= e($sale['cname'] ?: 'Walk-in Customer') ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Invoice Date</div>
      <div class="meta-value"><?= date('d M Y', strtotime($sale['created_at'])) ?></div>
    </div>
  </div>

  <!-- Items -->
  <div class="inv-items">
    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty</th>
          <th>Unit Price</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td>
            <div class="item-name"><?= e($item['product_name']) ?></div>
          </td>
          <td><?= (int)$item['qty'] ?></td>
          <td><?= money($item['price']) ?></td>
          <td><strong><?= money($item['total']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="inv-totals">
    <div class="totals-box">
      <div class="total-row">
        <span>Subtotal</span><span><?= money($sale['subtotal']) ?></span>
      </div>
      <?php if ((float)$sale['discount'] > 0): ?>
      <div class="total-row" style="color:#dc2626">
        <span>Discount</span><span>-<?= money($sale['discount']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ((float)$sale['tax'] > 0): ?>
      <div class="total-row">
        <span>Tax</span><span><?= money($sale['tax']) ?></span>
      </div>
      <?php endif; ?>
      <div class="total-row grand">
        <span>TOTAL</span><span><?= money($sale['total']) ?></span>
      </div>
      <?php if ($sale['payment_method'] === 'cash'): ?>
      <div class="total-row" style="margin-top:8px">
        <span>Amount Paid</span><span><?= money($sale['paid']) ?></span>
      </div>
      <div class="total-row">
        <span>Change</span><span><?= money($sale['change_amount']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- QR + Footer -->
  <div class="inv-footer">
    <div class="qr-block">
      <img src="<?= e($qr_src) ?>"
           alt="QR Code for Invoice <?= e($sale['invoice_no']) ?>"
           loading="lazy">
      <div class="qr-label">Scan to verify</div>
    </div>
    <div class="footer-text">
      <div class="thanks">Thank you for your purchase!</div>
      <div><?= e(SHOP_NAME) ?></div>
      <div style="margin-top:8px;font-family:'IBM Plex Mono',monospace;font-size:10px;word-break:break-all;color:#9ca3af;">
        <?= e($pub_url) ?>
      </div>
    </div>
  </div>

</div><!-- /.invoice-card -->
<?php endif; ?>

</body>
</html>
