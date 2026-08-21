<?php
/**
 * Nuvio Add-on: Latin Streaming Scraper
 * Entry point for Nuvio to fetch add-on manifest and stream URLs
 */

header('Content-Type: application/json');

// Load config
$configPath = __DIR__ . '/config.json';
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];

// Route based on request
$action = $_GET['action'] ?? null;

if ($action === 'manifest') {
    echo json_encode([
        'id' => 'nuvio-streaming-addon',
        'name' => 'Latin Streaming Scraper',
        'version' => '1.0.0',
        'types' => ['movie', 'series'],
        'catalogs' => [],
        'resources' => [
            [
                'name' => 'stream',
                'types' => ['movie', 'series'],
                'idPrefixes' => ['tt']
            ]
        ],
        'contactEmail' => 'support@example.com'
    ]);
} elseif ($action === 'stream') {
    $type = $_GET['type'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if (!$type || !$id) {
        echo json_encode(['streams' => []]);
        exit;
    }
    
    $streams = scrapeStream($id, $config);
    echo json_encode(['streams' => $streams]);
} else {
    echo json_encode(['error' => 'Invalid action']);
}

/**
 * Scrape a stream by IMDB ID
 */
function scrapeStream($imdbId, $config) {
    $streams = [];
    
    // Get enabled sites from config
    $enabledSites = array_filter($config['sites'] ?? [], fn($s) => $s['enabled'] ?? false);
    
    foreach ($enabledSites as $siteKey => $siteConfig) {
        $scraperPath = __DIR__ . "/scrapers/{$siteKey}.php";
        if (file_exists($scraperPath)) {
            require_once $scraperPath;
            $scraperFunc = "scrape_{$siteKey}";
            if (function_exists($scraperFunc)) {
                $result = $scraperFunc($imdbId, $config);
                if ($result) {
                    $streams[] = [
                        'url' => $result,
                        'title' => "{$siteConfig['name']} Stream",
                        'source' => $siteKey
                    ];
                }
            }
        }
    }
    
    return $streams;
}
