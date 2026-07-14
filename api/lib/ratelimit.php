<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

/**
 * Sliding-window rate limit backed by the rate_limits table.
 * Records one attempt in $bucket and rejects (429) if more than $max attempts
 * occurred in the last $windowSeconds. $bucket should include the client IP and
 * the action (e.g. "login:203.0.113.5").
 */
function vg_rate_limit(string $bucket, int $max, int $windowSeconds): void
{
    $db = vg_db();
    $bucket = mb_substr($bucket, 0, 160);

    // Best-effort cleanup of old rows for this bucket.
    if (vg_db_is_sqlite()) {
        $del = $db->prepare("DELETE FROM rate_limits WHERE bucket = ? AND attempt_at < datetime('now', ?)");
        $del->execute([$bucket, '-' . ($windowSeconds * 4) . ' seconds']);
        $sel = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND attempt_at >= datetime('now', ?)");
        $sel->execute([$bucket, '-' . $windowSeconds . ' seconds']);
    } else {
        $del = $db->prepare('DELETE FROM rate_limits WHERE bucket = ? AND attempt_at < (NOW() - INTERVAL ? SECOND)');
        $del->execute([$bucket, $windowSeconds * 4]);
        $sel = $db->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND attempt_at >= (NOW() - INTERVAL ? SECOND)');
        $sel->execute([$bucket, $windowSeconds]);
    }

    $count = (int) $sel->fetchColumn();
    if ($count >= $max) {
        vg_error('Too many attempts. Please wait a moment and try again.', 429, 'rate_limited');
    }

    $ins = $db->prepare('INSERT INTO rate_limits (bucket) VALUES (?)');
    $ins->execute([$bucket]);
}

/** Best-effort client IP for rate-limit bucketing. */
function vg_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
