<?php
declare(strict_types=1);

const YT_CACHE_SECONDS = 3600;

function youtube_cache_file(): string
{
    return DATA_DIR . '/cache/youtube.json';
}

/** Latest videos from the channel's public RSS feed, cached for an hour. No API key. */
function youtube_feed(): array
{
    $cid = setting('youtube_channel_id');
    if ($cid === '') {
        return [];
    }
    $cache = youtube_cache_file();
    $cached = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
    if (is_array($cached) && ($cached['channel'] ?? '') === $cid && time() - filemtime($cache) < YT_CACHE_SECONDS) {
        return $cached['videos'];
    }
    $xml = http_get('https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($cid));
    $videos = $xml !== null ? parse_youtube_feed($xml) : null;
    if ($videos === null) {
        // YouTube down or blocked: keep showing the old list, try again in an hour.
        if (is_array($cached)) {
            touch($cache);
            return $cached['videos'];
        }
        return [];
    }
    file_put_contents($cache, json_encode(['channel' => $cid, 'videos' => $videos]));
    return $videos;
}

function parse_youtube_feed(string $xml): ?array
{
    $prev = libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    libxml_use_internal_errors($prev);
    if ($feed === false) {
        return null;
    }
    $out = [];
    foreach ($feed->entry as $entry) {
        $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
        $id = (string) $yt->videoId;
        if (!preg_match('/^[\w-]{11}$/', $id)) {
            continue;
        }
        $out[] = [
            'id' => $id,
            'title' => (string) $entry->title,
            'published' => (string) $entry->published,
            'short' => str_contains((string) $entry->link['href'], '/shorts/'),
        ];
    }
    return $out;
}

function video_pins(): array
{
    $v = json_decode(setting('video_pinned', '[]'), true);
    return is_array($v) ? $v : [];
}

function video_hidden(): array
{
    $v = json_decode(setting('video_hidden', '[]'), true);
    return is_array($v) ? $v : [];
}

/** Pinned first, then the newest from the feed, minus hidden ones. */
function videos_for_site(int $limit = 12): array
{
    $pins = video_pins();
    $skip = array_flip([...video_hidden(), ...array_column($pins, 'id')]);
    $out = array_map(fn ($p) => $p + ['pinned' => true, 'published' => ''], $pins);
    foreach (youtube_feed() as $v) {
        if (count($out) >= $limit) {
            break;
        }
        if (!isset($skip[$v['id']])) {
            $out[] = $v + ['pinned' => false];
        }
    }
    return $out;
}

function youtube_thumb(string $id): string
{
    return 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg';
}

function youtube_video_id(string $url): ?string
{
    $url = trim($url);
    if (preg_match('/^[\w-]{11}$/', $url)) {
        return $url;
    }
    if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/))([\w-]{11})#', $url, $m)) {
        return $m[1];
    }
    return null;
}

/** Accepts a channel ID, a channel URL, an @handle or a handle URL. */
function youtube_channel_id(string $input): ?string
{
    $input = trim($input);
    if (preg_match('/(UC[\w-]{22})/', $input, $m)) {
        return $m[1];
    }
    if (preg_match('#@([\w.-]+)#', $input, $m) || preg_match('/^([\w.-]+)$/', $input, $m)) {
        $html = http_get('https://www.youtube.com/@' . rawurlencode($m[1]));
        if ($html !== null && (preg_match('#<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[\w-]{22})#', $html, $c)
            || preg_match('/"(?:externalId|channelId)":"(UC[\w-]{22})"/', $html, $c))) {
            return $c[1];
        }
    }
    return null;
}

function youtube_title(string $id): ?string
{
    foreach (youtube_feed() as $v) {
        if ($v['id'] === $id) {
            return $v['title'];
        }
    }
    $json = http_get('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $id));
    $data = $json ? json_decode($json, true) : null;
    return is_array($data) && isset($data['title']) ? (string) $data['title'] : null;
}

function youtube_embed_url(string $url): ?string
{
    $id = youtube_video_id($url);
    return $id ? 'https://www.youtube-nocookie.com/embed/' . $id : null;
}

function http_get(string $url, int $timeout = 8): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SiteFeed/1.0)',
            CURLOPT_HTTPHEADER => ['Accept-Language: en-US,en;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'user_agent' => 'Mozilla/5.0 (compatible; SiteFeed/1.0)']]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

/** Follow redirects and return where a link ends up (for short links). */
function http_final_url(string $url, int $timeout = 8): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_NOBODY => false, CURLOPT_RANGE => '0-0', CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SiteFeed/1.0)',
    ]);
    curl_exec($ch);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $ok = curl_errno($ch) === 0;
    curl_close($ch);
    return $ok && $final ? (string) $final : null;
}
