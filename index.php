<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$uri = $_SERVER['REQUEST_URI'];

// 1. Respond to Nuvio's Manifest request
if (str_contains($uri, 'manifest.json')) {
    $manifest = [
        'id' => 'com.custom.nuvioaddon',
        'version' => '1.0.0',
        'name' => 'Custom Test Addon',
        'description' => 'Baseline test addon',
        'types' => ['movie'],
        'catalogs' => [],
        'resources' => ['stream'],
        'idPrefixes' => ['tt']
    ];
    echo json_encode($manifest);
    exit;
}

// 2. Respond to Big Buck Bunny stream request (IMDb ID: tt1254207)
if (str_contains($uri, 'tt1254207')) {
    $response = [
        'streams' => [
            [
                'title' => 'Big Buck Bunny (1080p Test)',
                'url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4'
            ]
        ]
    ];
    echo json_encode($response);
    exit;
}

// 3. Default fallback for any other requests
echo json_encode(['streams' => []]);
?>