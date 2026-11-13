<?php
/**
 * VINYL UKRAINE — серверний кеш таблиці.
 *
 * Раз на 10 хвилин завантажує опубліковану Google-таблицю (CSV),
 * зберігає повну копію у файл на хостингу і віддає її всім відвідувачам.
 * Посилання на Google у коді сторінки не потрібне.
 */

// Джерело: опублікована CSV-таблиця (змінна середовища потрібна лише для тестів).
$SOURCE_URL = getenv('VINYL_SOURCE_URL') ?: 'https://raw.githubusercontent.com/2232344-ctrl/didvinil/main/vinyl-ukraine/data.csv';
$TTL          = 600;   // як довго копія вважається свіжою, секунд
$RETRY_AFTER  = 60;    // пауза після невдалої спроби, секунд
$MAX_BYTES    = 5 * 1024 * 1024;
// Необов'язково: секрет для примусового оновлення (data.php?refresh=СЕКРЕТ). Порожньо = вимкнено.
$REFRESH_TOKEN = '';

$dir   = __DIR__ . '/.vinyl-cache';
$cache = $dir . '/data.csv';
$lock  = $dir . '/lock';
$fail  = $dir . '/last-fail';

if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
// Закриваємо прямий доступ до папки кешу (Apache). Для nginx — див. README.
if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
    @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

function looks_like_csv($body, $max) {
    if (!is_string($body) || $body === '' || strlen($body) > $max) return false;
    if (stripos($body, '<html') !== false) return false;       // сторінка помилки Google
    $nl = strpos($body, "\n");
    if ($nl === false) return false;
    $header = mb_strtolower(substr($body, 0, $nl), 'UTF-8');
    return strpos($header, 'назва') !== false && strpos($header, 'website') !== false;
}

function fetch_source($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'VinylUkraineCache/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? $body : false;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'VinylUkraineCache/1.0']]);
    return @file_get_contents($url, false, $ctx);
}

function refresh_cache($url, $cache, $lock, $fail, $max, $retry) {
    // Не частіше за $retry секунд після невдачі.
    if (is_file($fail) && (time() - filemtime($fail)) < $retry) return false;
    $fp = @fopen($lock, 'c');
    if (!$fp) return false;
    // Лише один процес оновлює кеш; решта віддають поточну копію.
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return false; }
    $ok = false;
    $body = fetch_source($url);
    if (looks_like_csv($body, $max)) {
        $tmp = $cache . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $body) !== false && @rename($tmp, $cache)) {
            @touch($cache);
            @unlink($fail);
            $ok = true;
        } else {
            @unlink($tmp);
        }
    }
    if (!$ok) @touch($fail);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

$have   = is_file($cache);
$age    = $have ? time() - filemtime($cache) : PHP_INT_MAX;
$forced = $REFRESH_TOKEN !== '' && isset($_GET['refresh']) && hash_equals($REFRESH_TOKEN, (string)$_GET['refresh']);
$state  = 'HIT';

if (!$have || $age >= $TTL || $forced) {
    if ($forced && is_file($fail)) @unlink($fail);
    if (refresh_cache($SOURCE_URL, $cache, $lock, $fail, $MAX_BYTES, $RETRY_AFTER)) {
        $state = 'MISS';
    } else {
        $state = $have ? 'STALE' : 'FAIL';
    }
    clearstatcache();
}

if (!is_file($cache)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 60');
    header('Cache-Control: no-store');
    echo 'Дані тимчасово недоступні';
    exit;
}

$mtime = filemtime($cache);
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: public, max-age=120');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('X-Vinyl-Cache: ' . $state);
header('X-Content-Type-Options: nosniff');

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . filesize($cache));
readfile($cache);
