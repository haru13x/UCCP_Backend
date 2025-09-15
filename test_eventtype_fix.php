<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

// Test the eventMode and eventType relationships
$events = Event::with(['eventMode.eventType'])->take(2)->get();

echo "Found " . $events->count() . " events\n";

foreach ($events as $event) {
    echo "\nEvent ID: " . $event->id . "\n";
    echo "Event Mode present: " . ($event->eventMode ? 'Yes' : 'No') . "\n";
    
    if ($event->eventMode) {
        echo "Event Mode ID: " . $event->eventMode->id . "\n";
        echo "Event Type present: " . ($event->eventMode->eventType ? 'Yes' : 'No') . "\n";
        
        if ($event->eventMode->eventType) {
            echo "Event Type Name: " . $event->eventMode->eventType->name . "\n";
        } else {
            echo "Event Type Name: N/A\n";
        }
    } else {
        echo "Event Mode: N/A\n";
        echo "Event Type: N/A\n";
    }
    
    // Test the template syntax
    $templateOutput = $event->eventMode?->eventType?->name ?? 'N/A';
    echo "Template output: " . $templateOutput . "\n";
}

echo "\nTest completed successfully!\n";