<?php
// 1. Essential headers so Nuvio is allowed to read the response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 2. Grab the media type (movie/series) and IMDb ID that Nuvio sent
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

// 3. Prepare the stream array
$streams = [];

// 4. Return a test video if Nuvio asks for a movie or TV show
if ($type === 'movie' || $type === 'series') {
    $streams[] = [
        "name" => "My API",
        "title" => "Test Stream (1080p)\nRequested ID: " . $id,
        "url" => "http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4"
    ];
}

// 5. Output the exact JSON format Nuvio expects
echo json_encode(["streams" => $streams]);
?>

