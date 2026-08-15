<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$uri = $_SERVER['REQUEST_URI'];

// Check if Nuvio is asking for the manifest
if (strpos($uri, 'manifest.json') !== false) {
    $manifest = [
        'id' => 'org.custom.nuvioaddon',
        'version' => '1.0.0',
        'name' => 'Custom Test Addon',
        'description' => 'A custom stream addon',
        'types' => ['movie'],
        'catalogs' => [],
        'resources' => ['stream']
    ];
    echo json_encode($manifest);
    exit;
}

// Check if it's asking for a stream (using Big Buck Bunny as a test ID)
if (strpos($uri, 'stream') !== false) {
    $response = [
        'streams' => [
            [
                'title' => 'Big Buck Bunny (1080p Test)',
                'url' => 'http://distribution.bbb3d.renderfarming.net/video/mp4/bbb_sunflower_1080p_30fps_normal.mp4'
            ]
        ]
    ];
    echo json_encode($response);
    exit;
}

echo json_encode(['streams' => []]);
?>