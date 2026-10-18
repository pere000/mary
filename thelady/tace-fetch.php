<?php
/*
 * TACE local playlist proxy
 *
 * Used only by the browser when proseTest.html is served from
 * localhost / 127.0.0.1 / ::1.
 *
 * It retrieves public HTTP(S) CSV/M3U/M3U8 resources server-side so that
 * the browser's CORS policy does not prevent a local desktop installation
 * from loading a remote playlist.
 */

/*
 * Local use only: the page must be opened as localhost / 127.0.0.1 / ::1,
 * the client must be a loopback or private address (Docker port mapping
 * appears as a private gateway IP), and no forwarding headers may be present.
 */
$reqHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$client  = $_SERVER['REMOTE_ADDR'] ?? '';
$localHost = in_array($reqHost, ['localhost', '127.0.0.1', '[::1]'], true);
$localClient = filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
$forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_FORWARDED']) || isset($_SERVER['HTTP_X_REAL_IP']);

if (!$localHost || !$localClient || $forwarded) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("This proxy only works on localhost.");
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$url = $_GET['url'] ?? '';

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Invalid URL.");
}

$parts = parse_url($url);
$scheme = strtolower($parts['scheme'] ?? '');
$host   = strtolower($parts['host'] ?? '');

if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Only HTTP and HTTPS URLs are allowed.");
}

/*
 * This proxy is intended for playlist text, not arbitrary resources.
 */
$path = strtolower($parts['path'] ?? '');
if (!preg_match('/\.(csv|m3u|m3u8)$/i', $path)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Only CSV, M3U and M3U8 resources may be proxied.");
}

/*
 * Block obvious local/private targets to reduce SSRF risk.
 */
$blockedHosts = [
    'localhost',
    'localhost.localdomain',
];

if (in_array($host, $blockedHosts, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Local targets are not allowed.");
}

$ipList = @gethostbynamel($host);
if ($ipList !== false) {
    foreach ($ipList as $ip) {
        if (!filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Private or reserved network targets are not allowed.");
        }
    }
}

$maxBytes = 10 * 1024 * 1024; // 10 MB
$timeout  = 20;

if (function_exists('curl_init')) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'TACE playlist fetcher',
        CURLOPT_HTTPHEADER     => ['Accept: text/plain, text/csv, application/vnd.apple.mpegurl, */*'],
        CURLOPT_ENCODING       => '',
        CURLOPT_HEADER         => false,
    ]);

    $body = curl_exec($ch);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);

        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Remote playlist fetch failed: " . $error);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Remote server returned HTTP " . $status . ".");
    }

    if (strlen($body) > $maxBytes) {
        http_response_code(413);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Playlist is too large.");
    }

    if ($finalUrl !== false && $finalUrl !== '') {
        $finalParts = parse_url($finalUrl);
        $finalScheme = strtolower($finalParts['scheme'] ?? '');
        $finalHost = strtolower($finalParts['host'] ?? '');

        if (!in_array($finalScheme, ['http', 'https'], true) || $finalHost === '') {
            http_response_code(502);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Unsafe redirect target.");
        }
    }

    http_response_code(200);
    header('Content-Type: ' . ($contentType ?: 'text/plain') . '; charset=utf-8');
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

/*
 * Fallback for PHP installations without cURL.
 */
$context = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'timeout'       => $timeout,
        'follow_location' => 1,
        'max_redirects' => 5,
        'header'        => "User-Agent: TACE playlist fetcher\r\nAccept: text/plain, text/csv, application/vnd.apple.mpegurl, */*\r\n",
    ],
]);

$body = @file_get_contents($url, false, $context);

if ($body === false) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Remote playlist fetch failed.");
}

if (strlen($body) > $maxBytes) {
    http_response_code(413);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Playlist is too large.");
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo $body;
