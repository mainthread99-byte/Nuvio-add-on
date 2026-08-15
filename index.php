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
echo json_encode(['streams' => []]);


// --- SCRAPER SKELETON FUNCTION ---
function scrapeTargetSite($imdbId) {
    // This is where we will route the search and regex extraction 
    // once you're ready to hook up the live site parsing.
    return null;
}
?>