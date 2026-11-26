<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function discordStatusResponse(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function discordStatusHttpJson(string $url): array
{
    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $responseBody = (string)curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($responseBody === '' && curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : 'Falha ao consultar o widget do Discord.');
        }

        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 12,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $responseBody = $responseBody === false ? '' : $responseBody;
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $match)) {
            $statusCode = (int)$match[1];
        }
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $decoded['_http_status'] = $statusCode;
    $decoded['_raw_body'] = $responseBody;

    return $decoded;
}

function normalizeDiscordLookup(string $value): string
{
    $value = trim($value);
    if ($value !== '' && $value[0] === '@') {
        $value = substr($value, 1);
    }

    $hashPos = strpos($value, '#');
    if ($hashPos !== false) {
        $value = substr($value, 0, $hashPos);
    }

    $value = strtolower(trim($value));
    return preg_replace('/[\s._-]+/', '', $value) ?: '';
}

function discordPresenceLabel(string $status): string
{
    switch ($status) {
        case 'online':
            return 'Online';
        case 'idle':
            return 'Ausente';
        case 'dnd':
            return 'Ocupado';
        case 'offline':
            return 'Offline';
        case 'unknown':
            return 'Incerto';
        default:
            return 'Indisponivel';
    }
}

function discordStatusRequestedLookups(): array
{
    $lookups = [];
    $rawQuery = (string)($_SERVER['QUERY_STRING'] ?? '');

    if ($rawQuery !== '') {
        foreach (explode('&', $rawQuery) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode(str_replace('+', ' ', $rawKey));
            if ($key !== 'lookup' && $key !== 'lookup[]') {
                continue;
            }

            $value = trim(urldecode(str_replace('+', ' ', $rawValue)));
            if ($value !== '') {
                $lookups[] = $value;
            }
        }
    }

    if ($lookups) {
        return $lookups;
    }

    $fallback = $_GET['lookup'] ?? [];
    if (is_string($fallback)) {
        return preg_split('/\s*,\s*/', $fallback, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    if (is_array($fallback)) {
        return array_values(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, $fallback), static function (string $value): bool {
            return $value !== '';
        }));
    }

    return [];
}

$lookups = discordStatusRequestedLookups();

$requestedLookups = [];
foreach ($lookups as $lookup) {
    $lookup = trim((string)$lookup);
    if ($lookup === '' || in_array($lookup, $requestedLookups, true)) {
        continue;
    }

    $requestedLookups[] = $lookup;
    if (count($requestedLookups) >= 25) {
        break;
    }
}

if (DISCORD_GUILD_ID === '') {
    discordStatusResponse([
        'success' => false,
        'message' => 'DISCORD_GUILD_ID nao configurado.',
        'source' => 'widget',
        'invite_url' => DISCORD_SERVER_INVITE_URL,
    ]);
}

try {
    $widget = discordStatusHttpJson('https://discord.com/api/guilds/' . rawurlencode(DISCORD_GUILD_ID) . '/widget.json');
    $statusCode = (int)($widget['_http_status'] ?? 0);

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Widget do Discord indisponivel.');
    }

    $members = is_array($widget['members'] ?? null) ? $widget['members'] : [];
    $presenceCount = max((int)($widget['presence_count'] ?? 0), count($members));
    $visibleMemberCount = count($members);
    $isTruncated = $presenceCount > $visibleMemberCount;

    $inviteUrl = trim((string)(DISCORD_SERVER_INVITE_URL !== '' ? DISCORD_SERVER_INVITE_URL : ($widget['instant_invite'] ?? '')));
    $response = [
        'success' => true,
        'source' => 'widget',
        'guild' => [
            'id' => (string)($widget['id'] ?? DISCORD_GUILD_ID),
            'name' => (string)($widget['name'] ?? ''),
        ],
        'invite_url' => $inviteUrl,
        'presence_count' => $presenceCount,
        'visible_members' => $visibleMemberCount,
        'truncated' => $isTruncated,
    ];

    if (!$requestedLookups) {
        $response['members'] = array_map(static function (array $member): array {
            $status = (string)($member['status'] ?? 'online');
            return [
                'username' => (string)($member['username'] ?? ''),
                'status' => $status,
                'status_label' => discordPresenceLabel($status),
                'avatar_url' => (string)($member['avatar_url'] ?? ''),
            ];
        }, $members);

        discordStatusResponse($response);
    }

    $indexedMembers = [];
    foreach ($members as $member) {
        $username = normalizeDiscordLookup((string)($member['username'] ?? ''));
        if ($username !== '' && !isset($indexedMembers[$username])) {
            $indexedMembers[$username] = $member;
        }
    }

    $statuses = [];
    foreach ($requestedLookups as $lookup) {
        $normalizedLookup = normalizeDiscordLookup($lookup);
        $member = $normalizedLookup !== '' ? ($indexedMembers[$normalizedLookup] ?? null) : null;

        if ($member) {
            $status = (string)($member['status'] ?? 'online');
            $statuses[$lookup] = [
                'lookup' => $lookup,
                'found' => true,
                'status' => $status,
                'status_label' => discordPresenceLabel($status),
                'username' => (string)($member['username'] ?? ''),
                'avatar_url' => (string)($member['avatar_url'] ?? ''),
            ];
            continue;
        }

        $fallbackStatus = $isTruncated ? 'unknown' : 'offline';
        $statuses[$lookup] = [
            'lookup' => $lookup,
            'found' => false,
            'status' => $fallbackStatus,
            'status_label' => discordPresenceLabel($fallbackStatus),
            'username' => $lookup,
            'avatar_url' => '',
        ];
    }

    $response['statuses'] = $statuses;
    discordStatusResponse($response);
} catch (Throwable $error) {
    error_log('Discord status error: ' . $error->getMessage());

    $statuses = [];
    foreach ($requestedLookups as $lookup) {
        $statuses[$lookup] = [
            'lookup' => $lookup,
            'found' => false,
            'status' => 'unavailable',
            'status_label' => discordPresenceLabel('unavailable'),
            'username' => $lookup,
            'avatar_url' => '',
        ];
    }

    discordStatusResponse([
        'success' => false,
        'message' => 'Nao foi possivel consultar o Discord agora.',
        'source' => 'widget',
        'invite_url' => DISCORD_SERVER_INVITE_URL,
        'statuses' => $statuses,
    ]);
}
