<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;

$events = Event::with('locations')->where('isconference', 1)->get();

echo "Conference Events:\n";
foreach($events as $event) {
    echo "Event ID: {$event->id}, Title: {$event->title}, IsConference: {$event->isconference}, Locations: {$event->locations->count()}\n";
    if ($event->locations->count() > 0) {
        foreach($event->locations as $location) {
            echo "  - Location ID: {$location->id}, Name: {$location->name}\n";
        }
    }
}

echo "\nAll Events:\n";
$allEvents = Event::with('locations')->get();
foreach($allEvents as $event) {
    echo "Event ID: {$event->id}, Title: {$event->title}, IsConference: {$event->isconference}, Locations: {$event->locations->count()}\n";
}