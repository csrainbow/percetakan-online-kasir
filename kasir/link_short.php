<?php

define('CSLINK_API_KEY', '291eae854f11f5f3a53a5d39940dfe7944683ab70f23e438');

function csapi_shorten($longUrl) {
    DB::run("CREATE TABLE IF NOT EXISTS link_cache (long_url TEXT PRIMARY KEY, short_url TEXT, created_at TEXT)");

    $cache = DB::one('SELECT short_url FROM link_cache WHERE long_url = ?', [$longUrl]);
    if ($cache && !empty($cache['short_url'])) {
        return $cache['short_url'];
    }

    $short = '';
    for ($attempt = 0; $attempt < 3 && $short === ''; $attempt++) {
        $ch = curl_init('https://cslink.web.id/api/shorten');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Api-Key: ' . CSLINK_API_KEY],
            CURLOPT_POSTFIELDS     => json_encode(['url' => $longUrl]),
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http >= 200 && $http < 300 && $res) {
            $j = json_decode($res, true);
            if (is_array($j) && !empty($j['short_code'])) {
                $short = 'https://cslink.web.id/' . $j['short_code'];
            }
        }
        if ($short === '') {
            usleep(300000);
        }
    }
    if ($short === '') {
        return $longUrl;
    }

    DB::run('INSERT OR REPLACE INTO link_cache (long_url, short_url, created_at) VALUES (?, ?, ?)', [$longUrl, $short, date('Y-m-d H:i:s')]);
    return $short;
}

function nota_link($ref, $id, $t = 'a5') {
    return csapi_shorten(nota_publik_url($ref, $id, $t));
}