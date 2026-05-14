<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Point of Sale';
$activePage = 'pos';

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$products   = $conn->query("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$customers  = $conn->query("SELECT * FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<!-- INVOICE MODAL -->
<div class="modal-overlay" id="invoiceModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title">&#10003; Sale Complete &mdash; Invoice</span>
      <span class="modal-close" onclick="closeInvoice()">&times;</span>
    </div>
    <div class="modal-body" id="invoiceBody" style="padding:0;background:#fff"></div>
    <div class="modal-footer" style="gap:8px">
      <button class="btn btn-secondary" onclick="printInvoice()">Print</button>
      <button class="btn btn-primary"   onclick="downloadPDF()">Download PDF</button>
      <button class="btn btn-secondary" onclick="closeInvoice()">New Sale</button>
    </div>
  </div>
</div>


<!-- BARCODE PICKER MODAL (multiple products same barcode) -->
<div class="modal-overlay" id="barcodePicker">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title">Multiple products &mdash; pick one</span>
      <span class="modal-close" onclick="closePicker()">&times;</span>
    </div>
    <div class="modal-body" style="padding:14px">
      <div style="font-size:12px;color:var(--text2);margin-bottom:12px">
        Barcode <strong id="pickerBarcode" style="color:var(--accent);font-family:var(--mono)"></strong>
        matches several products. Tap the correct one:
      </div>
      <div id="pickerList"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closePicker()" class="btn btn-secondary btn-sm">Cancel</button>
    </div>
  </div>
</div>

<!-- POS LAYOUT -->
<div class="pos-layout">

  <!-- LEFT: Products -->
  <div class="pos-panel">
    <div class="pos-search-bar" style="display:flex;gap:8px;align-items:center">
      <input type="text" id="posSearch" class="pos-search" style="flex:1" placeholder="Search product or type barcode + Enter">
    </div>
    <div class="cat-tabs">
      <button class="cat-tab active" data-cat="all">All</button>
      <?php foreach ($categories as $cat): ?>
        <button class="cat-tab" data-cat="<?= $cat['id'] ?>"><?= e($cat['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="products-grid" id="productsGrid">
      <?php foreach ($products as $p): ?>
        <div class="product-card <?= $p['stock'] <= 0 ? 'out-of-stock' : '' ?>"
             data-id="<?= $p['id'] ?>"
             data-name="<?= e($p['name']) ?>"
             data-price="<?= $p['price'] ?>"
             data-stock="<?= $p['stock'] ?>"
             data-cat="<?= $p['category_id'] ?>"
             data-barcode="<?= e($p['barcode']) ?>"
             onclick="addToCart(this)">
          <div class="prod-name"><?= e($p['name']) ?></div>
          <div class="prod-price"><?= money($p['price']) ?></div>
          <div class="prod-stock <?= $p['stock'] <= 0 ? 'stock-out' : ($p['stock'] <= $p['low_stock_alert'] ? 'stock-low' : '') ?>">
            Stock: <?= $p['stock'] ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-panel">
    <div class="cart-header">
      <span>Cart</span>
      <span id="cartCount" class="badge badge-blue">0</span>
    </div>
    <div class="cart-items" id="cartItems">
      <div class="empty-state"><div class="empty-icon">&#9723;</div>Cart is empty</div>
    </div>
    <div class="cart-footer">
      <div class="cart-totals">
        <div class="total-row"><span>Subtotal</span><span id="cartSubtotal">$0.00</span></div>
        <div class="total-row">
          <span>Discount</span>
          <span><input type="number" id="discountInput" min="0" step="0.01" value="0"
            style="width:70px;background:var(--bg3);border:1px solid var(--border2);color:var(--text);padding:2px 6px;border-radius:2px;font-size:12px"
            oninput="updateTotals()"></span>
        </div>
        <?php if (TAX_RATE > 0): ?>
        <div class="total-row"><span>Tax (<?= TAX_RATE*100 ?>%)</span><span id="cartTax">$0.00</span></div>
        <?php endif; ?>
        <div class="total-row grand">
          <span>TOTAL</span>
          <span id="cartTotal" class="val">$0.00</span>
        </div>
      </div>
      <div style="margin-bottom:8px">
        <select id="customerSelect" class="form-control" style="font-size:12px;padding:6px">
          <option value="">Walk-in Customer</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:8px">
        <select id="paymentMethod" class="form-control" style="font-size:12px;padding:6px" onchange="toggleCash()">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="mobile">Mobile</option>
        </select>
      </div>
      <div id="cashSection" style="margin-bottom:10px;display:flex;gap:6px;align-items:center">
        <label style="font-size:11px;color:var(--text2);white-space:nowrap">Paid:</label>
        <input type="number" id="paidInput" min="0" step="0.01" value="0"
          class="form-control" style="font-size:12px;padding:6px" oninput="updateChange()">
        <span style="font-size:11px;color:var(--text2);white-space:nowrap">
          Change: <strong id="changeAmt" class="text-accent">$0.00</strong>
        </span>
      </div>
      <div class="cart-actions">
        <button class="btn-clear-cart" onclick="clearCart()" title="Clear cart">X</button>
        <button class="btn-checkout" id="checkoutBtn" onclick="submitSale()">Checkout</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
/* =============================================================
   POS JAVASCRIPT — single flat scope, no nested IIFEs
   ============================================================= */

const TAX_RATE  = <?= TAX_RATE ?>;
const CURRENCY  = '<?= CURRENCY ?>';
const SHOP_NAME = '<?= addslashes(SHOP_NAME) ?>';

let cart      = {};
let lastSale  = null;

/* ── helpers ── */
function fmt(n) { return CURRENCY + parseFloat(n).toFixed(2); }
function $id(id) { return document.getElementById(id); }

/* =============================================================
   TOAST
   ============================================================= */
function showScanToast(msg, ok) {
  let t = $id('scanToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'scanToast';
    t.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 22px;border-radius:4px;font-size:13px;font-weight:600;z-index:9999;transition:opacity .4s;pointer-events:none;opacity:0';
    document.body.appendChild(t);
  }
  t.textContent       = msg;
  t.style.background  = ok ? '#3aff8a' : '#ff4a4a';
  t.style.color       = ok ? '#000'    : '#fff';
  t.style.opacity     = '1';
  clearTimeout(t._h);
  t._h = setTimeout(() => { t.style.opacity = '0'; }, 2000);
}

/* =============================================================
   CART
   ============================================================= */
function addToCart(el) {
  if (el.classList.contains('out-of-stock')) return;
  const id = el.dataset.id, stock = parseInt(el.dataset.stock);
  if (cart[id]) {
    if (cart[id].qty >= stock) { showScanToast('Max stock reached!', false); return; }
    cart[id].qty++;
  } else {
    cart[id] = { id: parseInt(id), name: el.dataset.name, price: parseFloat(el.dataset.price), qty: 1, stock: stock };
  }
  renderCart();
}

function renderCart() {
  const keys = Object.keys(cart);
  $id('cartCount').textContent = keys.reduce((s, k) => s + cart[k].qty, 0);
  if (!keys.length) {
    $id('cartItems').innerHTML = '<div class="empty-state"><div class="empty-icon">&#9723;</div>Cart is empty</div>';
    updateTotals(); return;
  }
  $id('cartItems').innerHTML = keys.map(id => {
    const i = cart[id];
    return '<div class="cart-item">' +
      '<div style="flex:1"><div class="ci-name">' + i.name + '</div><div class="ci-price">' + fmt(i.price) + ' each</div></div>' +
      '<div class="ci-qty">' +
        '<button class="qty-btn" onclick="changeQty(' + id + ',-1)">-</button>' +
        '<span style="font-size:13px;min-width:20px;text-align:center">' + i.qty + '</span>' +
        '<button class="qty-btn" onclick="changeQty(' + id + ',1)">+</button>' +
      '</div>' +
      '<div class="ci-total">' + fmt(i.price * i.qty) + '</div>' +
      '<span class="ci-del" onclick="removeItem(' + id + ')">&times;</span>' +
    '</div>';
  }).join('');
  updateTotals();
}

function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) delete cart[id];
  else if (cart[id].qty > cart[id].stock) { cart[id].qty = cart[id].stock; showScanToast('Max stock!', false); }
  renderCart();
}
function removeItem(id) { delete cart[id]; renderCart(); }
function clearCart()    { cart = {}; renderCart(); }

function updateTotals() {
  const sub  = Object.values(cart).reduce((s, i) => s + i.price * i.qty, 0);
  const disc = parseFloat($id('discountInput').value) || 0;
  const tax  = sub * TAX_RATE;
  const tot  = Math.max(0, sub + tax - disc);
  $id('cartSubtotal').textContent = fmt(sub);
  var taxEl = $id('cartTax'); if (taxEl) taxEl.textContent = fmt(tax);
  $id('cartTotal').textContent = fmt(tot);
  updateChange();
}

function updateChange() {
  const total = parseFloat($id('cartTotal').textContent.replace(CURRENCY, '')) || 0;
  const paid  = parseFloat($id('paidInput').value) || 0;
  $id('changeAmt').textContent = fmt(Math.max(0, paid - total));
}

function toggleCash() {
  $id('cashSection').style.display = $id('paymentMethod').value === 'cash' ? 'flex' : 'none';
}

/* =============================================================
   SEARCH & CATEGORY FILTER
   ============================================================= */
$id('posSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.product-card').forEach(function(c) {
    c.style.display = (c.dataset.name.toLowerCase().includes(q) || c.dataset.barcode.toLowerCase().includes(q)) ? '' : 'none';
  });
});

document.querySelectorAll('.cat-tab').forEach(function(tab) {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.cat-tab').forEach(function(t) { t.classList.remove('active'); });
    tab.classList.add('active');
    const cat = tab.dataset.cat;
    document.querySelectorAll('.product-card').forEach(function(c) {
      c.style.display = (cat === 'all' || c.dataset.cat == cat) ? '' : 'none';
    });
  });
});

/* Keyboard scanner: fast keystrokes + Enter = barcode */
var _buf = '', _lastKey = 0;
document.addEventListener('keydown', function(e) {
  var now = Date.now(), gap = now - _lastKey; _lastKey = now;
  if (gap > 300) _buf = '';
  if (e.key === 'Enter') {
    var code = _buf.trim(); _buf = '';
    if (code.length >= 3) handleScannedCode(code);
  } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
    if (gap < 50 || _buf.length === 0) _buf += e.key; else _buf = e.key;
  }
});

$id('posSearch').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    var code = this.value.trim();
    if (code.length >= 3) { handleScannedCode(code); this.value = ''; e.preventDefault(); }
  }
});

/* =============================================================
   BARCODE MATCHING — handles 0, 1, or multiple matches
   ============================================================= */
function handleScannedCode(code) {
  code = code.trim();
  if (!code) return;
  var cards   = Array.from(document.querySelectorAll('.product-card'));
  var matches = cards.filter(function(c) {
    return c.dataset.barcode.trim().toLowerCase() === code.toLowerCase();
  });

  if (matches.length === 0) {
    showScanToast('Not found: ' + code, false);
    console.warn('[POS] Scanned:', code, '| Known:', cards.map(function(c){ return c.dataset.barcode + '->' + c.dataset.name; }).join(', '));
  } else if (matches.length === 1) {
    addMatchedProduct(matches[0]);
  } else {
    showBarcodePicker(matches, code);
  }
}

function addMatchedProduct(card) {
  if (card.classList.contains('out-of-stock')) {
    showScanToast('Out of stock: ' + card.dataset.name, false);
  } else {
    addToCart(card);
    showScanToast('Added: ' + card.dataset.name, true);
    card.style.transition  = 'transform .15s,border-color .2s';
    card.style.transform   = 'scale(1.06)';
    card.style.borderColor = '#3aff8a';
    setTimeout(function() {
      card.style.transform = ''; card.style.borderColor = '';
    }, 500);
  }
}

/* =============================================================
   BARCODE PICKER (multiple products share same barcode)
   ============================================================= */
var _pickerCards = [];

function showBarcodePicker(cards, code) {
  _pickerCards = cards;
  $id('pickerBarcode').textContent = code;
  $id('pickerList').innerHTML = cards.map(function(card, i) {
    var inStock = !card.classList.contains('out-of-stock');
    var price   = parseFloat(card.dataset.price).toFixed(2);
    var stock   = card.dataset.stock;
    return '<div onclick="' + (inStock ? 'pickProduct(' + i + ')' : '') + '" ' +
      'style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;' +
      'border:1px solid var(--border2);border-radius:4px;margin-bottom:8px;' +
      'background:var(--bg3);cursor:' + (inStock ? 'pointer' : 'default') + ';' +
      'opacity:' + (inStock ? '1' : '.4') + '">' +
      '<div>' +
        '<div style="font-weight:600;font-size:13px">' + card.dataset.name + '</div>' +
        '<div style="font-size:11px;color:var(--text2);margin-top:2px">Stock: ' + stock + ' &nbsp;&middot;&nbsp; ' + (inStock ? 'Available' : 'Out of stock') + '</div>' +
      '</div>' +
      '<div style="font-size:15px;font-weight:700;font-family:var(--mono);color:var(--accent)">' + CURRENCY + price + '</div>' +
    '</div>';
  }).join('');
  $id('barcodePicker').classList.add('open');
}

function pickProduct(index) {
  $id('barcodePicker').classList.remove('open');
  if (_pickerCards[index]) addMatchedProduct(_pickerCards[index]);
}

function closePicker() {
  $id('barcodePicker').classList.remove('open');
}



/* =============================================================
   AJAX CHECKOUT
   ============================================================= */
function submitSale() {
  if (!Object.keys(cart).length) { alert('Cart is empty!'); return; }
  var total = parseFloat($id('cartTotal').textContent.replace(CURRENCY, '')) || 0;
  var paid  = parseFloat($id('paidInput').value) || 0;
  var pm    = $id('paymentMethod').value;
  if (pm === 'cash' && paid < total) { alert('Insufficient payment!'); return; }

  var btn = $id('checkoutBtn');
  btn.textContent = 'Processing...'; btn.disabled = true;

  var fd = new FormData();
  fd.append('cart',            JSON.stringify(Object.values(cart)));
  fd.append('customer_id',    $id('customerSelect').value);
  fd.append('payment_method', pm);
  fd.append('discount',       $id('discountInput').value || '0');
  fd.append('paid',           pm === 'cash' ? paid : total);

  fetch('/pages/checkout.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.textContent = 'Checkout'; btn.disabled = false;
      if (data.success) { lastSale = data; clearCart(); showInvoice(data); }
      else alert('Error: ' + data.error);
    })
    .catch(function() { btn.textContent = 'Checkout'; btn.disabled = false; alert('Network error. Try again.'); });
}

/* =============================================================
   INVOICE
   ============================================================= */
function showInvoice(s) {
  var c    = s.currency;
  var rows = s.items.map(function(i) {
    return '<tr>' +
      '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' + i.name + '</td>' +
      '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:center">' + i.qty + '</td>' +
      '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">' + c + parseFloat(i.price).toFixed(2) + '</td>' +
      '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">' + c + parseFloat(i.total).toFixed(2) + '</td>' +
    '</tr>';
  }).join('');

  // QR code: server already computed the URL and image src
  var qrImgUrl  = s.qr_img_url  || '';
  var publicUrl = s.public_url  || '';

  $id('invoiceBody').innerHTML =
  '<div id="invPrint" style="font-family:\'IBM Plex Sans\',sans-serif;padding:24px;background:#fff;color:#111">' +
    '<div style="text-align:center;padding-bottom:14px;border-bottom:2px solid #111;margin-bottom:16px">' +
      '<div style="font-size:22px;font-weight:800">' + s.shop_name + '</div>' +
      '<div style="font-size:11px;color:#666;margin-top:3px;text-transform:uppercase;letter-spacing:.06em">Sales Invoice</div>' +
    '</div>' +
    '<div style="display:flex;justify-content:space-between;font-size:12px;color:#444;margin-bottom:14px">' +
      '<div style="line-height:1.8"><div><strong>Invoice #</strong> ' + s.invoice_no + '</div><div><strong>Date</strong> ' + s.date + '</div></div>' +
      '<div style="text-align:right;line-height:1.8"><div><strong>Customer</strong> ' + s.customer_name + '</div><div><strong>Payment</strong> ' + s.payment_method.charAt(0).toUpperCase() + s.payment_method.slice(1) + '</div></div>' +
    '</div>' +
    '<table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:14px">' +
      '<thead><tr style="background:#f3f4f6">' +
        '<th style="padding:7px 8px;text-align:left;border-bottom:2px solid #d1d5db;font-size:10px;text-transform:uppercase">Item</th>' +
        '<th style="padding:7px 8px;text-align:center;border-bottom:2px solid #d1d5db;font-size:10px;text-transform:uppercase">Qty</th>' +
        '<th style="padding:7px 8px;text-align:right;border-bottom:2px solid #d1d5db;font-size:10px;text-transform:uppercase">Price</th>' +
        '<th style="padding:7px 8px;text-align:right;border-bottom:2px solid #d1d5db;font-size:10px;text-transform:uppercase">Total</th>' +
      '</tr></thead><tbody>' + rows + '</tbody>' +
    '</table>' +
    '<div style="margin-left:auto;width:230px;font-size:13px">' +
      '<div style="display:flex;justify-content:space-between;padding:4px 0;color:#555"><span>Subtotal</span><span>' + c + parseFloat(s.subtotal).toFixed(2) + '</span></div>' +
      (s.discount > 0 ? '<div style="display:flex;justify-content:space-between;padding:4px 0;color:#c00"><span>Discount</span><span>-' + c + parseFloat(s.discount).toFixed(2) + '</span></div>' : '') +
      (s.tax > 0 ? '<div style="display:flex;justify-content:space-between;padding:4px 0;color:#555"><span>Tax</span><span>' + c + parseFloat(s.tax).toFixed(2) + '</span></div>' : '') +
      '<div style="display:flex;justify-content:space-between;padding:9px 0;margin-top:4px;border-top:2px solid #111;font-size:16px;font-weight:800"><span>TOTAL</span><span>' + c + parseFloat(s.total).toFixed(2) + '</span></div>' +
      (s.payment_method === 'cash' ?
        '<div style="display:flex;justify-content:space-between;padding:3px 0;color:#555;font-size:12px"><span>Paid</span><span>' + c + parseFloat(s.paid).toFixed(2) + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;padding:3px 0;color:#555;font-size:12px"><span>Change</span><span>' + c + parseFloat(s.change).toFixed(2) + '</span></div>' : '') +
    '</div>' +
    /* ── QR Code block ── */
    '<div style="display:flex;align-items:center;gap:18px;margin-top:20px;padding-top:14px;border-top:1px dashed #ccc">' +
      (qrImgUrl
        ? '<img id="invQrImg" src="' + qrImgUrl + '" width="110" height="110" alt="QR Code" crossorigin="anonymous" style="border:1px solid #e5e7eb;border-radius:8px;padding:4px;background:#fff;flex-shrink:0">'
        : '') +
      '<div style="flex:1">' +
        '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:4px">Scan to view invoice</div>' +
        (publicUrl
          ? '<a href="' + publicUrl + '" target="_blank" style="font-size:10px;color:#6c47ff;word-break:break-all;font-family:monospace;text-decoration:none">' + publicUrl + '</a>'
          : '') +
        '<div style="margin-top:8px;font-size:11px;color:#9ca3af">Thank you for your purchase! &nbsp;&middot;&nbsp; ' + s.shop_name + '</div>' +
      '</div>' +
    '</div>' +
  '</div>';

  $id('invoiceModal').classList.add('open');
}

function closeInvoice() { $id('invoiceModal').classList.remove('open'); }

function printInvoice() {
  var printDiv = $id('invPrint');
  if (!printDiv) return;
  // Grab outer HTML so QR <img> src is included
  var html = printDiv.outerHTML;
  var w = window.open('', '_blank', 'width=640,height=820');
  w.document.write(
    '<!DOCTYPE html><html><head><title>Invoice</title>' +
    '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">' +
    '<style>' +
      '*{box-sizing:border-box;margin:0;padding:0}' +
      'body{font-family:\'IBM Plex Sans\',sans-serif;padding:24px;color:#111}' +
      'img{max-width:100%}' +
      '@media print{body{padding:0}@page{margin:10mm}}' +
    '</style>' +
    '</head><body>' +
    html +
    '<script>window.onload=function(){window.print();window.close();};<\/script>' +
    '</body></html>'
  );
  w.document.close();
}

function downloadPDF() {
  if (!lastSale) return;
  var s = lastSale;

  // Load QR image as base64 first, then build PDF
  if (s.qr_img_url) {
    _fetchImageAsBase64(s.qr_img_url, function(qrData) {
      _buildPdf(s, qrData);
    });
  } else {
    _buildPdf(s, null);
  }
}

/* Fetch a cross-origin image and convert to base64 data URL */
function _fetchImageAsBase64(url, callback) {
  var img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = function() {
    try {
      var cv = document.createElement('canvas');
      cv.width = img.width; cv.height = img.height;
      cv.getContext('2d').drawImage(img, 0, 0);
      callback(cv.toDataURL('image/png'));
    } catch(e) { callback(null); }
  };
  img.onerror = function() { callback(null); };
  // Bust cache so CORS headers are returned
  img.src = url + '&cb=' + Date.now();
}

function _buildPdf(s, qrDataUrl) {
  var c   = s.currency;
  var doc = new window.jspdf.jsPDF({ unit:'mm', format:'a5' });
  var W   = doc.internal.pageSize.getWidth(), y = 16;

  doc.setFont('helvetica','bold'); doc.setFontSize(18); doc.setTextColor(0);
  doc.text(s.shop_name, W/2, y, {align:'center'}); y+=7;
  doc.setFont('helvetica','normal'); doc.setFontSize(9); doc.setTextColor(100);
  doc.text('SALES INVOICE', W/2, y, {align:'center'}); y+=8;
  doc.setDrawColor(0); doc.setLineWidth(0.6); doc.line(10,y,W-10,y); y+=6;

  doc.setTextColor(60); doc.setFontSize(9);
  doc.text('Invoice: ' + s.invoice_no, 10, y);
  doc.text('Customer: ' + s.customer_name, W-10, y, {align:'right'}); y+=5;
  doc.text('Date: ' + s.date, 10, y);
  doc.text('Payment: ' + s.payment_method, W-10, y, {align:'right'}); y+=8;

  doc.setFillColor(243,244,246); doc.rect(10,y-4,W-20,7,'F');
  doc.setFont('helvetica','bold'); doc.setFontSize(8); doc.setTextColor(80);
  doc.text('ITEM',12,y); doc.text('QTY',W-52,y,{align:'right'});
  doc.text('PRICE',W-32,y,{align:'right'}); doc.text('TOTAL',W-10,y,{align:'right'});
  y+=2; doc.setDrawColor(180); doc.setLineWidth(0.2); doc.line(10,y,W-10,y); y+=5;

  doc.setFont('helvetica','normal'); doc.setFontSize(9); doc.setTextColor(0);
  s.items.forEach(function(item) {
    doc.text(String(item.name).substring(0,34),12,y);
    doc.text(String(item.qty),W-52,y,{align:'right'});
    doc.text(c+parseFloat(item.price).toFixed(2),W-32,y,{align:'right'});
    doc.text(c+parseFloat(item.total).toFixed(2),W-10,y,{align:'right'});
    y+=6;
  });

  doc.setDrawColor(30); doc.setLineWidth(0.4); doc.line(10,y,W-10,y); y+=6;
  doc.setFontSize(9); doc.setTextColor(80);
  var tRow = function(l,v){ doc.text(l,W-42,y,{align:'right'}); doc.text(v,W-10,y,{align:'right'}); y+=5; };
  tRow('Subtotal:', c+parseFloat(s.subtotal).toFixed(2));
  if(s.discount>0) tRow('Discount:', '-'+c+parseFloat(s.discount).toFixed(2));
  if(s.tax>0)      tRow('Tax:', c+parseFloat(s.tax).toFixed(2));
  doc.setLineWidth(0.5); doc.line(W-60,y,W-10,y); y+=5;
  doc.setFont('helvetica','bold'); doc.setFontSize(12); doc.setTextColor(0);
  doc.text('TOTAL:', W-42,y,{align:'right'});
  doc.text(c+parseFloat(s.total).toFixed(2),W-10,y,{align:'right'}); y+=7;
  if(s.payment_method==='cash'){
    doc.setFont('helvetica','normal'); doc.setFontSize(9); doc.setTextColor(80);
    tRow('Paid:', c+parseFloat(s.paid).toFixed(2));
    tRow('Change:', c+parseFloat(s.change).toFixed(2));
  }

  // ── QR Code + footer ────────────────────────────────────────
  y+=4; doc.setDrawColor(180); doc.setLineWidth(0.2); doc.line(10,y,W-10,y); y+=6;

  if (qrDataUrl) {
    var qrSize = 30; // mm
    try {
      doc.addImage(qrDataUrl, 'PNG', 10, y, qrSize, qrSize);
    } catch(e) { /* skip QR if addImage fails */ }
    // Text beside QR
    doc.setFont('helvetica','normal'); doc.setFontSize(7); doc.setTextColor(120);
    doc.text('Scan to view invoice online:', 10 + qrSize + 4, y + 5);
    if (s.public_url) {
      doc.setFont('helvetica','bold'); doc.setFontSize(7); doc.setTextColor(80);
      // Wrap long URL
      var urlLines = doc.splitTextToSize(s.public_url, W - qrSize - 24);
      doc.text(urlLines, 10 + qrSize + 4, y + 11);
    }
    y += qrSize + 4;
  }

  doc.setFont('helvetica','italic'); doc.setFontSize(8); doc.setTextColor(140);
  doc.text('Thank you for your purchase! - '+s.shop_name, W/2, y, {align:'center'});
  doc.save(s.invoice_no+'.pdf');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
