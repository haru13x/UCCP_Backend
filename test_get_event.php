<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EventController;
use Illuminate\Http\Request;

$controller = new EventController();
$response = $controller->getEvent(1);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content:\n";
echo $response->getContent() . "\n";

// Parse JSON to check structure
$data = json_decode($response->getContent(), true);
if ($data) {
    echo "\nParsed Data Structure:\n";
    echo "Event ID: " . ($data['id'] ?? 'N/A') . "\n";
    echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "IsConference: " . ($data['isconference'] ?? 'N/A') . "\n";
    echo "Conference Locations Count: " . (isset($data['conference_locations']) ? count($data['conference_locations']) : 'N/A') . "\n";
    echo "Location Data Count: " . (isset($data['location_data']) ? count($data['location_data']) : 'N/A') . "\n";
    
    if (isset($data['conference_locations'])) {
        echo "Conference Locations: " . json_encode($data['conference_locations']) . "\n";
    }
    
    if (isset($data['location_data'])) {
        echo "Location Data: " . json_encode($data['location_data']) . "\n";
    }
}