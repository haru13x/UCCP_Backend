<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EventController;
use Illuminate\Http\Request;

// Test the get-church-locations endpoint
use App\Http\Controllers\ChurchLocationController;

$controller = new ChurchLocationController();
$response = $controller->index();

echo "Church Locations Response:\n";
echo $response->getContent() . "\n\n";

// Test a specific event to see its structure
$eventController = new EventController();
$eventResponse = $eventController->getEvent(1);

echo "Event Response (ID 1):\n";
$eventContent = $eventResponse->getContent();
echo $eventContent . "\n\n";

// Parse and analyze the event structure
$eventData = json_decode($eventContent, true);
if ($eventData) {
    echo "Event Analysis:\n";
    echo "ID: " . ($eventData['id'] ?? 'N/A') . "\n";
    echo "Title: " . ($eventData['title'] ?? 'N/A') . "\n";
    echo "IsConference: " . ($eventData['isconference'] ?? 'N/A') . "\n";
    echo "Conference Locations: " . (isset($eventData['conference_locations']) ? json_encode($eventData['conference_locations']) : 'NOT SET') . "\n";
    echo "Location Data: " . (isset($eventData['location_data']) ? json_encode($eventData['location_data']) : 'NOT SET') . "\n";
    echo "Venue: " . ($eventData['venue'] ?? 'N/A') . "\n";
}