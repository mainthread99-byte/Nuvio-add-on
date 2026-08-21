<?php
/**
 * Pelispedia Scraper
 * Extracts HLS streams from Pelispedia pages
 * Note: Direct .m3u8 extraction only (encrypted embed69 links require updated decryption logic)
 */

function scrape_pelispedia($imdbId, $config) {
    // Accept full URL or IMDB ID
    $url = null;
    
    if (filter_var($imdbId, FILTER_VALIDATE_URL)) {
        $url = $imdbId;
    } else {
        $url = "https://pelispedia.mov/pelicula/{$imdbId}";
    }
    
    if (!$url) {
        return null;
    }
    
    // Fetch main page
    $html = _curl_get($url, $config);
    if (!$html) {
        return null;
    }
    
    // Try direct .m3u8 link
    if (preg_match('/(https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*)/i', $html, $m)) {
        return html_entity_decode($m[1]);
    }
    
    // Try to find and fetch iframe
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
    }
    
    return null;
}

/**
 * Fetch URL with proxy support
 */
function _curl_get($url, $config) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    // Apply proxy from environment variables (preferred)
    $proxyUrl = getenv('NUVIO_PROXY_URL') ?: getenv('PROXY_URL');
    if ($proxyUrl) {
        curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
        $proxyUser = getenv('NUVIO_PROXY_USER');
        if ($proxyUser) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, 
                $proxyUser . ':' . (getenv('NUVIO_PROXY_PASS') ?: ''));
        }
    } 
    // Fall back to config.json proxy
    elseif (!empty($config['proxy']['enabled']) && !empty($config['proxy']['url'])) {
        curl_setopt($ch, CURLOPT_PROXY, $config['proxy']['url']);
        if (!empty($config['proxy']['user'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, 
                $config['proxy']['user'] . ':' . ($config['proxy']['pass'] ?? ''));
        }
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response ?: null;
}
