<?php
// ─────────────────────────────────────────────────────────────
//  includes/config.php
//  Reads environment variables so the app works on Vercel
//  without any code changes between local and production.
// ─────────────────────────────────────────────────────────────

// ── App constants ─────────────────────────────────────────────
define('DB_HOST',   getenv('DB_HOST')   ?: 'localhost');
define('DB_USER',   getenv('DB_USER')   ?: 'root');
define('DB_PASS',   getenv('DB_PASS')   ?: '');
define('DB_NAME',   getenv('DB_NAME')   ?: 'pos_db');
define('TAX_RATE',  (float)(getenv('TAX_RATE') ?: 0.00));
define('CURRENCY',  getenv('CURRENCY')  ?: 'Rs-');
define('SHOP_NAME', getenv('SHOP_NAME') ?: 'AL Majeed Book Store');

// APP_URL is the public base URL used in QR codes.
// On Vercel set this to https://your-app.vercel.app (no trailing slash).
define('APP_URL', rtrim(getenv('APP_URL') ?: _detectBaseUrl(), '/'));

// ── Database connection ───────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(503);
    die("<div style='font-family:sans-serif;padding:40px;background:#fee;color:#c00;border:2px solid #c00;margin:40px;border-radius:8px'>
        <h2>&#9888; Database Connection Failed</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($conn->connect_error) . "</p>
        <p>Check your environment variables: <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code></p>
        </div>");
}
$conn->set_charset('utf8mb4');

// ── DB-based session handler (required for Vercel stateless functions) ──
require_once __DIR__ . '/session_handler.php';
_startDbSession($conn);

// ── Helpers ───────────────────────────────────────────────────
function e($str)   { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function money($n) { return CURRENCY . number_format((float)$n, 2); }

/** Generate a unique invoice number: INV-YYYYMMDD-XXXXX */
function generateInvoice() {
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/** Public URL for a given invoice: https://your-app.vercel.app/invoice/INV-... */
function invoicePublicUrl(string $invoice_no): string {
    return APP_URL . '/invoice/' . rawurlencode($invoice_no);
}

/** QR code image src (free, no API key needed) */
function invoiceQrSrc(string $invoice_no, int $size = 150): string {
    return 'https://api.qrserver.com/v1/create-qr-code/'
         . '?size=' . $size . 'x' . $size
         . '&data=' . rawurlencode(invoicePublicUrl($invoice_no))
         . '&format=png&margin=4&ecc=M';
}

function _detectBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
