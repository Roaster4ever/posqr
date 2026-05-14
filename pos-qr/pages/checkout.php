<?php
// pages/checkout.php — AJAX endpoint, returns JSON only
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$cart        = json_decode($_POST['cart'] ?? '[]', true);
$customer_id = intval($_POST['customer_id'] ?? 0) ?: null;
$payment     = in_array($_POST['payment_method'] ?? '', ['cash','card','mobile'])
               ? $_POST['payment_method'] : 'cash';
$discount    = max(0, floatval($_POST['discount'] ?? 0));
$paid        = floatval($_POST['paid'] ?? 0);

if (empty($cart)) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty']);
    exit;
}

$subtotal = 0;
foreach ($cart as $item) $subtotal += floatval($item['price']) * intval($item['qty']);
$tax     = round($subtotal * TAX_RATE, 2);
$total   = round($subtotal + $tax - $discount, 2);
$change  = round(max(0, $paid - $total), 2);
$invoice = generateInvoice();

$customer_name = 'Walk-in Customer';
if ($customer_id) {
    $r = $conn->query("SELECT name FROM customers WHERE id=" . intval($customer_id));
    if ($r && $row = $r->fetch_assoc()) $customer_name = $row['name'];
}

$conn->begin_transaction();
try {
    $cid = $customer_id ? intval($customer_id) : 'NULL';
    $inv = $conn->real_escape_string($invoice);
    $pay = $conn->real_escape_string($payment);

    $conn->query(
        "INSERT INTO sales
           (invoice_no, customer_id, subtotal, discount, tax, total, paid, change_amount, payment_method)
         VALUES
           ('$inv', $cid, $subtotal, $discount, $tax, $total, $paid, $change, '$pay')"
    );
    $sale_id   = $conn->insert_id;
    $items_out = [];

    foreach ($cart as $item) {
        $pid   = intval($item['id']);
        $name  = $conn->real_escape_string($item['name']);
        $qty   = intval($item['qty']);
        $price = floatval($item['price']);
        $itot  = round($price * $qty, 2);
        $conn->query(
            "INSERT INTO sale_items (sale_id, product_id, product_name, qty, price, total)
             VALUES ($sale_id, $pid, '$name', $qty, $price, $itot)"
        );
        $conn->query("UPDATE products SET stock=stock-$qty WHERE id=$pid");
        $items_out[] = ['name' => $item['name'], 'qty' => $qty, 'price' => $price, 'total' => $itot];
    }

    $conn->commit();

    // Build QR code URL — generated dynamically from the invoice number
    $public_url = invoicePublicUrl($invoice);
    $qr_img_url = invoiceQrSrc($invoice, 150);

    echo json_encode([
        'success'        => true,
        'sale_id'        => $sale_id,
        'invoice_no'     => $invoice,
        'customer_name'  => $customer_name,
        'payment_method' => $payment,
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'tax'            => $tax,
        'total'          => $total,
        'paid'           => $paid,
        'change'         => $change,
        'date'           => date('d/m/Y H:i'),
        'shop_name'      => SHOP_NAME,
        'currency'       => CURRENCY,
        'items'          => $items_out,
        'public_url'     => $public_url,   // ← customer-facing invoice link
        'qr_img_url'     => $qr_img_url,   // ← QR code image URL
    ]);
} catch (Exception $ex) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}
