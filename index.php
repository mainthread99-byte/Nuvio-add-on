<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

$uri = $_SERVER['REQUEST_URI'];

// 1. Respond to Nuvio's Manifest request
if (str_contains($uri, 'manifest.json')) {
    $manifest = [
        'id' => 'com.custom.nuvioaddon',
        'version' => '1.0.0',
        'name' => 'Custom Dynamic Addon',
        'description' => 'HLS Test and Scraper Add-on',
        'types' => ['movie'],
        'catalogs' => [],
        'resources' => ['stream'],
        'idPrefixes' => ['tt']
    ];
    echo json_encode($manifest);
    exit;
}

// 2. Handle Stream Requests
if (str_contains($uri, 'stream/movie/')) {
    preg_match('/tt\d+/', $uri, $matches);
    $imdbId = $matches[0] ?? '';

    $streams = [];

    if ($imdbId === 'tt1254207') {
        // Test an official public HLS (.m3u8) stream to verify Nuvio HLS playback
        $streams[] = [
            'name' => 'HLS Test Source',
            'title' => 'Apple BipBop HLS Test (.m3u8)',
            'url' => 'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_adv/bipbop_adv.m3u8',
            'behaviorHints' => ['notWebReady' => false]
        ];
    } else {
        // Scraper execution hook for your target site
        $scrapedUrl = scrapeTargetSite($imdbId);
        if ($scrapedUrl) {
            $streams[] = [
                'name' => 'Pelispedia',
                'title' => 'Scraped Stream (HD)',
                'url' => $scrapedUrl,
                'behaviorHints' => ['notWebReady' => false]
            ];
        }
    }

    echo json_encode(['streams' => $streams]);
    exit;
}

// 3. Default fallback
// Temporary: allow direct scraping via query param `scrape_url` for testing
if (!empty($_GET['scrape_url'])) {
    $testUrl = $_GET['scrape_url'];
    $found = scrapeTargetSite($testUrl);
    echo json_encode(['streams' => $found ? [['name'=>'Scraped','title'=>'Scraped Stream','url'=>$found,'behaviorHints'=>['notWebReady'=>false]]] : []]);
    exit;
}

echo json_encode(['streams' => []]);


// --- SCRAPER FUNCTIONS ---
function loadAddonConfig() {
    $path = __DIR__ . '/config.json';
    if (!file_exists($path)) return ['sites' => []];
    $json = file_get_contents($path);
    $cfg = json_decode($json, true);
    return $cfg ?? ['sites' => []];
}

function scrapeTargetSite($imdbId) {
    $cfg = loadAddonConfig();
    if (empty($cfg['sites'])) return null;

    foreach ($cfg['sites'] as $key => $meta) {
        if (empty($meta['enabled'])) continue;
        $scraperFile = __DIR__ . '/scrapers/' . $key . '.php';
        if (!file_exists($scraperFile)) continue;
        include_once $scraperFile;
        $func = 'scrape_' . $key;
        if (function_exists($func)) {
            $result = $func($imdbId);
            if ($result) return $result;
        }
    }

    return null;
}

?>