<?php
/**
 * Pelispedia Scraper
 * Extracts HLS streams from Pelispedia and similar embed69-based sites
 */

function scrape_pelispedia($imdbId, $config) {
    // For test, accept a full URL or search Pelispedia
    $url = null;
    
    if (filter_var($imdbId, FILTER_VALIDATE_URL)) {
        $url = $imdbId;
    } else {
        // Try default Pelispedia URL pattern (if provided as search term)
        $url = "https://pelispedia.mov/pelicula/{$imdbId}";
    }
    
    if (!$url) {
        return null;
    }
    
    // Fetch the page
    $html = _curl_get($url, $config);
    if (!$html) {
        return null;
    }
    
    // Try direct .m3u8 match
    if (preg_match('/(https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*)/i', $html, $m)) {
        return html_entity_decode($m[1]);
    }
    
    // Try to find iframe and fetch it
    if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $iframeUrl = $m[1];
        if (strpos($iframeUrl, '/') === 0) {
            $base = parse_url($url);
            $iframeUrl = $base['scheme'] . '://' . $base['host'] . $iframeUrl;
        }
        
        $iframeHtml = _curl_get($iframeUrl, $config);
        
        // Look for .m3u8 in iframe
        if (preg_match('/(https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*)/i', $iframeHtml, $m)) {
            return html_entity_decode($m[1]);
        }
        
        // Look for embed69-style dataLink
        if (stripos($iframeHtml, 'dataLink') !== false) {
            return _handleEmbed69($iframeHtml, $config);
        }
    }
    
    return null;
}

/**
 * Handle embed69-style encrypted HLS links
 */
function _handleEmbed69($html, $config) {
    // Extract dataLink JSON
    if (!preg_match('/let\s+dataLink\s*=\s*(\[[\s\S]*?\]);/i', $html, $m)) {
        return null;
    }
    
    $dataLink = json_decode($m[1], true);
    if (!is_array($dataLink)) {
        return null;
    }
    
    // Extract PoW challenge parameters
    $challenge = null;
    $salt = null;
    $difficulty = 1;
    
    if (preg_match('/POW_CHALLENGE\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
        $challenge = $m[1];
    }
    if (preg_match('/POW_SALT\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
        $salt = $m[1];
    }
    if (preg_match('/POW_DIFFICULTY\s*=\s*(\d+)/i', $html, $m)) {
        $difficulty = (int)$m[1];
    }
    
    if (!$challenge || !$salt) {
        return null;
    }
    
    // Solve PoW
    $key = _solvePow($challenge, $salt, $difficulty);
    if (!$key) {
        return null;
    }
    
    // Decrypt embeds
    foreach ($dataLink as $file) {
        $embeds = array_merge(
            $file['sortedEmbeds'] ?? [],
            $file['downloadEmbeds'] ?? []
        );
        
        foreach ($embeds as $embed) {
            $m3u8 = _decryptEmbed($embed['link'] ?? '', $key);
            if ($m3u8) {
                return $m3u8;
            }
        }
    }
    
    return null;
}

/**
 * Solve Proof of Work for embed69
 */
function _solvePow($challenge, $salt, $difficulty) {
    $prefix = str_repeat('0', max(1, $difficulty));
    
    for ($i = 0; $i < 20000000; $i++) {
        $hash = hash('sha256', $challenge . $i . $salt);
        if (strpos($hash, $prefix) === 0) {
            return hex2bin($hash);
        }
    }
    
    return null;
}

/**
 * Decrypt embed69 AES-256-CBC encrypted link
 */
function _decryptEmbed($encrypted, $key) {
    $decoded = base64_decode($encrypted);
    if (!$decoded || strlen($decoded) <= 16) {
        return null;
    }
    
    $iv = substr($decoded, 0, 16);
    $cipher = substr($decoded, 16);
    
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($plain && preg_match('/(https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*)/i', $plain, $m)) {
        return html_entity_decode($m[1]);
    }
    
    return null;
}

/**
 * Fetch URL with optional proxy support
 */
function _curl_get($url, $config) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    // Apply proxy if enabled
    if (!empty($config['proxy']['enabled']) && !empty($config['proxy']['url'])) {
        curl_setopt($ch, CURLOPT_PROXY, $config['proxy']['url']);
        if (!empty($config['proxy']['user'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, 
                $config['proxy']['user'] . ':' . ($config['proxy']['pass'] ?? ''));
        }
    }
    
    // Check for environment variable proxy override
    $envProxy = getenv('NUVIO_PROXY_URL') ?: getenv('PROXY_URL');
    if ($envProxy) {
        curl_setopt($ch, CURLOPT_PROXY, $envProxy);
        $envUser = getenv('NUVIO_PROXY_USER') ?: getenv('PROXY_USER');
        if ($envUser) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, 
                $envUser . ':' . (getenv('NUVIO_PROXY_PASS') ?: ''));
        }
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response ?: null;
}
