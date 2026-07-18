<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

/** Fetch a required, trimmed string field with length bounds, or 400. */
function vg_req_string(array $data, string $key, int $min = 1, int $max = 255): string
{
    $val = isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : '';
    $len = mb_strlen($val);
    if ($len < $min || $len > $max) {
        vg_error("Field '$key' must be between $min and $max characters.", 422);
    }
    return $val;
}

/** Fetch an optional, trimmed string (empty → null), capped at $max. */
function vg_opt_string(array $data, string $key, int $max = 255): ?string
{
    if (!isset($data[$key]) || !is_string($data[$key])) {
        return null;
    }
    $val = trim($data[$key]);
    if ($val === '') {
        return null;
    }
    if (mb_strlen($val) > $max) {
        vg_error("Field '$key' is too long (max $max).", 422);
    }
    return $val;
}

/** Validate + normalise an e-mail address, or 422. */
function vg_req_email(array $data, string $key = 'email'): string
{
    $val = isset($data[$key]) && is_string($data[$key]) ? trim(strtolower($data[$key])) : '';
    $email = filter_var($val, FILTER_VALIDATE_EMAIL);
    if ($email === false || mb_strlen($val) > 255) {
        vg_error('A valid e-mail address is required.', 422);
    }
    return $val;
}

/** Fetch an integer within [$min,$max], or 422. */
function vg_req_int(array $data, string $key, int $min, int $max): int
{
    if (!isset($data[$key]) || !is_numeric($data[$key])) {
        vg_error("Field '$key' must be a number.", 422);
    }
    $n = (int) $data[$key];
    if ($n < $min || $n > $max) {
        vg_error("Field '$key' must be between $min and $max.", 422);
    }
    return $n;
}

/** Fetch a float within [$min,$max], or 422. */
function vg_req_float(array $data, string $key, float $min, float $max): float
{
    if (!isset($data[$key]) || !is_numeric($data[$key])) {
        vg_error("Field '$key' must be a number.", 422);
    }
    $f = (float) $data[$key];
    if ($f < $min || $f > $max) {
        vg_error("Field '$key' must be between $min and $max.", 422);
    }
    return $f;
}

/** Fetch an optional http(s) URL (empty → null), capped at $max, or 422. */
function vg_opt_url(array $data, string $key, int $max = 500): ?string
{
    $val = vg_opt_string($data, $key, $max);
    if ($val === null) {
        return null;
    }
    if (!preg_match('#^https?://#i', $val) || filter_var($val, FILTER_VALIDATE_URL) === false) {
        vg_error("Field '$key' must be a valid http(s) URL.", 422);
    }
    return $val;
}

/** Enforce a minimum password policy. */
function vg_req_password(array $data, string $key = 'password'): string
{
    $val = isset($data[$key]) && is_string($data[$key]) ? $data[$key] : '';
    $len = mb_strlen($val);
    if ($len < 10 || $len > 200) {
        vg_error('Password must be between 10 and 200 characters.', 422);
    }
    return $val;
}
