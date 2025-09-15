<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EventController;
use Illuminate\Http\Request;

$controller = new EventController();
$request = new Request();
$response = $controller->index($request);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content:\n";
$content = $response->getContent();
echo $content . "\n";

// Parse JSON to check structure
$data = json_decode($content, true);
if ($data && is_array($data) && count($data) > 0) {
    $event = $data[0]; // Check first event
    echo "\nFirst Event Analysis:\n";
    echo "Event ID: " . ($event['id'] ?? 'N/A') . "\n";
    echo "Title: " . ($event['title'] ?? 'N/A') . "\n";
    echo "IsConference: " . ($event['isconference'] ?? 'N/A') . "\n";
    echo "Conference Locations: " . (isset($event['conference_locations']) ? json_encode($event['conference_locations']) : 'NOT SET') . "\n";
    echo "Location Data Count: " . (isset($event['location_data']) ? count($event['location_data']) : 'N/A') . "\n";
}