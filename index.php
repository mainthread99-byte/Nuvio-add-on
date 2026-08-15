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

// 2. Respond to stream request (IMDb ID: tt1254207)
if (str_contains($uri, 'tt1254207')) {
    $response = [
        'streams' => [
            [
                'name' => 'Test Addon',
                'title' => 'Big Buck Bunny (Sample)',
                'url' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                'behaviorHints' => [
                    'notWebReady' => false
                ]
            ]
        ]
    ];
    echo json_encode($response);
    exit;
}

// 3. Default fallback for any other requests
echo json_encode(['streams' => []]);
?>