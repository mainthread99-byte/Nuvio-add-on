<?php
// Simple scraper for Pelispedia-like sites that attempts to find a playable .m3u8 URL

function _curl_get($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; NuvioAddon/1.0)");
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $ret = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    // Lightweight debug logging when requested via query param
    if (!empty($_GET['__debug'])) {
        $log = [];
        $log[] = "URL: " . $url;
        $log[] = "HTTP_CODE: " . ($info['http_code'] ?? '');
        $log[] = "TOTAL_TIME: " . ($info['total_time'] ?? '');
        $log[] = "LENGTH: " . strlen($ret);
        $log[] = "SNIPPET: " . substr($ret, 0, 800);
        $path = sys_get_temp_dir() . '/nuvio_scrape_debug.log';
        file_put_contents($path, implode("\n", $log) . "\n---\n", FILE_APPEND | LOCK_EX);
    }
    return $ret;
}

function scrape_pelispedia($id) {
    // If caller passed a full URL as the id, try it directly
    $url = null;
    if (is_string($id) && (stripos($id, 'http://') === 0 || stripos($id, 'https://') === 0)) {
        $url = $id;
    }

    // If we don't have a direct URL, try a simple site search pattern (best-effort)
    if (!$url) {
        $q = urlencode($id);
        $searchUrls = [
            "https://pelispedia.film/buscar/?q={$q}",
            "https://pelispedia.tv/buscar/?q={$q}",
            "https://pelispedia.com/buscar/?q={$q}"
        ];
        foreach ($searchUrls as $s) {
            $html = _curl_get($s);
            if (!$html) continue;
            // try to find first result link
            if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
                $candidate = $m[1];
                if (strpos($candidate, '/') === 0) {
                    $candidate = (parse_url($s, PHP_URL_SCHEME) ?: 'https') . '://' . parse_url($s, PHP_URL_HOST) . $candidate;
                }
                $url = $candidate;
                break;
            }
        }
    }

    if (!$url) return null;

    $html = _curl_get($url);
    if (!$html) return null;

    // 1) Direct .m3u8 reference
    if (preg_match('/https?:\\/\\/[^"'"'\\s]+\\.m3u8[^"'"'\\s]*/i', $html, $m)) {
        return html_entity_decode($m[0]);
    }

    // 2) Look for iframe embeds and follow
    if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $embed = $m[1];
        if (strpos($embed, '/') === 0) {
            $embed = (parse_url($url, PHP_URL_SCHEME) ?: 'https') . '://' . parse_url($url, PHP_URL_HOST) . $embed;
        }
        $embedHtml = _curl_get($embed);
        if ($embedHtml && preg_match('/https?:\\/\\/[^"'"'\\s]+\\.m3u8[^"'"'\\s]*/i', $embedHtml, $mm)) {
            return html_entity_decode($mm[0]);
        }
        if ($embedHtml && preg_match('/source\s*[:=]\s*["\'](https?:\\/\\/[^"\']+\\.m3u8[^"\']*)["\']/i', $embedHtml, $mm)) {
            return html_entity_decode($mm[1]);
        }

        // Special-case: embed69-style players store encrypted links in a JS variable `dataLink`.
        if ($embedHtml && stripos($embedHtml, 'dataLink') !== false) {
            // extract JSON-like array assigned to dataLink
            if (preg_match('/let\s+dataLink\s*=\s*(\[[\s\S]*?\]);/i', $embedHtml, $dm)) {
                $jsonText = $dm[1];
                // try to decode JSON
                $data = json_decode($jsonText, true);
                if (is_array($data) && count($data) > 0) {
                    // find POW params
                    $challenge = null; $salt = null; $difficulty = 0;
                    if (preg_match('/const\s+POW_CHALLENGE\s*=\s*["\']([^"\']+)["\']/i', $embedHtml, $c)) $challenge = $c[1];
                    if (preg_match('/const\s+POW_SALT\s*=\s*["\']([^"\']+)["\']/i', $embedHtml, $s)) $salt = $s[1];
                    if (preg_match('/const\s+POW_DIFFICULTY\s*=\s*(\d+)/i', $embedHtml, $d)) $difficulty = intval($d[1]);

                    if ($challenge !== null && $salt !== null) {
                        // solve PoW to derive AES key (SHA-256 of challenge+nonce+salt where hex digest starts with N zeros)
                        $prefix = str_repeat('0', max(0, $difficulty));
                        $nonce = null;
                        for ($i = 0; $i < 20000000; $i++) {
                            $h = hash('sha256', $challenge . (string)$i . $salt);
                            if (strpos($h, $prefix) === 0) { $nonce = $i; $key = hex2bin($h); break; }
                        }

                        if ($nonce !== null && isset($key)) {
                            // decrypt embeds
                            foreach ($data as $file) {
                                $embeds = [];
                                if (!empty($file['sortedEmbeds']) && is_array($file['sortedEmbeds'])) $embeds = array_merge($embeds, $file['sortedEmbeds']);
                                if (!empty($file['downloadEmbeds']) && is_array($file['downloadEmbeds'])) $embeds = array_merge($embeds, $file['downloadEmbeds']);
                                foreach ($embeds as $embedEntry) {
                                    if (empty($embedEntry['link']) || !is_string($embedEntry['link'])) continue;
                                    $enc = $embedEntry['link'];
                                    $decoded = base64_decode($enc);
                                    if ($decoded === false || strlen($decoded) <= 16) continue;
                                    $iv = substr($decoded, 0, 16);
                                    $cipher = substr($decoded, 16);
                                    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                                    if ($plain && preg_match('/https?:\\/\\/[^"\'\s]+\\.m3u8[^"\'\s]*/i', $plain, $pm)) {
                                        return html_entity_decode($pm[0]);
                                    }
                                    // also try to find a player url inside the decrypted payload
                                    if ($plain && preg_match('/https?:\\/\\/[^"\'\s]+/i', $plain, $pu)) {
                                        $candidate = $pu[0];
                                        $follow = _curl_get($candidate);
                                        if ($follow && preg_match('/https?:\\/\\/[^"\'\s]+\\.m3u8[^"\'\s]*/i', $follow, $fm)) {
                                            return html_entity_decode($fm[0]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // 3) Try to find JSON/JS player sources
    if (preg_match('/["\'](https?:\\/\\/[^"\']+\\.m3u8[^"\']*)["\']/i', $html, $m2)) {
        return html_entity_decode($m2[1]);
    }

    return null;
}
