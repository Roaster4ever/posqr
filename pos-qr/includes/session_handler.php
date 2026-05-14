<?php
// ─────────────────────────────────────────────────────────────
//  includes/session_handler.php
//
//  Replaces PHP's default file-based sessions with a MySQL-
//  backed handler. This is REQUIRED for Vercel deployment
//  because each serverless function invocation may run on a
//  different container that has no access to the previous
//  one's filesystem.
//
//  The `sessions` table is created by database.sql.
// ─────────────────────────────────────────────────────────────

class DbSessionHandler implements SessionHandlerInterface
{
    private mysqli $db;
    private int $lifetime;

    public function __construct(mysqli $db, int $lifetime = 7200)
    {
        $this->db       = $db;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool                          { return true; }

    public function read(string $id): string|false
    {
        $id = $this->db->real_escape_string($id);
        $r  = $this->db->query(
            "SELECT data FROM sessions WHERE id='$id' AND expires_at > NOW() LIMIT 1"
        );
        if ($r && $row = $r->fetch_assoc()) {
            return $row['data'];
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        $id      = $this->db->real_escape_string($id);
        $data    = $this->db->real_escape_string($data);
        $expires = date('Y-m-d H:i:s', time() + $this->lifetime);
        $this->db->query(
            "INSERT INTO sessions (id, data, expires_at)
             VALUES ('$id', '$data', '$expires')
             ON DUPLICATE KEY UPDATE data='$data', expires_at='$expires'"
        );
        return !$this->db->errno;
    }

    public function destroy(string $id): bool
    {
        $id = $this->db->real_escape_string($id);
        $this->db->query("DELETE FROM sessions WHERE id='$id'");
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $this->db->query("DELETE FROM sessions WHERE expires_at < NOW()");
        return max(0, (int)$this->db->affected_rows);
    }
}

/**
 * Boot the DB session handler and start the session.
 * Called once from config.php.
 */
function _startDbSession(mysqli $conn): void
{
    // Detect HTTPS so the cookie is flagged Secure on Vercel
    $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $handler = new DbSessionHandler($conn, 7200);
    session_set_save_handler($handler, true);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
