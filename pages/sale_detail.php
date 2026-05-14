<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$id   = intval($_GET['id'] ?? 0);
$sale = $conn->query(
    "SELECT s.*, c.name cname
     FROM   sales s
     LEFT JOIN customers c ON s.customer_id = c.id
     WHERE  s.id = $id LIMIT 1"
)->fetch_assoc();

if (!$sale) { echo '<div class="empty-state">Sale not found</div>'; exit; }
$items      = $conn->query("SELECT * FROM sale_items WHERE sale_id=$id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$public_url = invoicePublicUrl($sale['invoice_no']);
$qr_src     = invoiceQrSrc($sale['invoice_no'], 120);
?>

<div class="receipt">
  <div class="receipt-title"><?= e(SHOP_NAME) ?></div>
  <div class="receipt-sub">Official Receipt</div>
  <hr class="receipt-divider">
  <div class="receipt-row"><span>Invoice:</span><span class="text-mono" style="font-size:12px"><?= e($sale['invoice_no']) ?></span></div>
  <div class="receipt-row"><span>Date:</span><span><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span></div>
  <div class="receipt-row"><span>Customer:</span><span><?= e($sale['cname'] ?: 'Walk-in') ?></span></div>
  <div class="receipt-row"><span>Payment:</span><span><?= ucfirst(e($sale['payment_method'])) ?></span></div>
  <hr class="receipt-divider">
  <?php foreach ($items as $it): ?>
    <div class="receipt-row">
      <span><?= e($it['product_name']) ?> &times;<?= $it['qty'] ?></span>
      <span><?= money($it['total']) ?></span>
    </div>
  <?php endforeach; ?>
  <hr class="receipt-divider">
  <div class="receipt-row"><span>Subtotal:</span><span><?= money($sale['subtotal']) ?></span></div>
  <?php if ($sale['discount'] > 0): ?>
  <div class="receipt-row"><span>Discount:</span><span style="color:#dc2626">-<?= money($sale['discount']) ?></span></div>
  <?php endif; ?>
  <?php if ($sale['tax'] > 0): ?>
  <div class="receipt-row"><span>Tax:</span><span><?= money($sale['tax']) ?></span></div>
  <?php endif; ?>
  <div class="receipt-row receipt-total"><span>TOTAL:</span><span><?= money($sale['total']) ?></span></div>
  <div class="receipt-row"><span>Paid:</span><span><?= money($sale['paid']) ?></span></div>
  <div class="receipt-row"><span>Change:</span><span><?= money($sale['change_amount']) ?></span></div>
  <hr class="receipt-divider">
  <div class="receipt-footer">Status: <?= strtoupper(e($sale['status'])) ?><br>Thank you!</div>

  <!-- QR Code block -->
  <div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px dashed #e5e7eb">
    <img src="<?= e($qr_src) ?>"
         alt="QR Code"
         width="110" height="110"
         style="border:1px solid #e5e7eb;border-radius:8px;padding:4px;background:#fff">
    <div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;font-weight:600">Scan to view invoice</div>
    <a href="<?= e($public_url) ?>" target="_blank"
       style="font-size:11px;color:#6c47ff;word-break:break-all;text-align:center;text-decoration:none;font-family:'IBM Plex Mono',monospace">
      <?= e($public_url) ?>
    </a>
  </div>
</div>
