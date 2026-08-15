<?php
require_once __DIR__ . '/scrapers/pelispedia.php';

$url = $argv[1] ?? 'https://pelispedia.mov/pelicula/jackass-la-ultima-y-nos-vamos-YOJCwS';
echo "Testing URL: $url\n";
$res = scrape_pelispedia($url);
if ($res) {
    echo "Found stream: $res\n";
} else {
    echo "No stream found.\n";
}
