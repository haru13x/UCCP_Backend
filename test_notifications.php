<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EventController;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

echo "=== Testing Notifications/New API Endpoint ===\n\n";

// Get a test user (assuming user ID 1 exists)
$user = User::find(1);
if (!$user) {
    echo "No user found with ID 1. Creating test notifications for any user...\n";
    $user = User::first();
}

if (!$user) {
    echo "No users found in database. Cannot test notifications.\n";
    exit;
}

echo "Testing with User ID: {$user->id}\n";
echo "User Name: {$user->name}\n\n";

// Check existing notifications for this user
$existingNotifications = Notification::where('user_id', $user->id)->get();
echo "Existing notifications count: " . $existingNotifications->count() . "\n\n";

if ($existingNotifications->count() > 0) {
    echo "Sample existing notification structure:\n";
    $sample = $existingNotifications->first();
    echo "ID: " . ($sample->id ?? 'N/A') . "\n";
    echo "Title: " . ($sample->title ?? 'N/A') . "\n";
    echo "Body: " . ($sample->body ?? 'N/A') . "\n";
    echo "Type: " . ($sample->type ?? 'N/A') . "\n";
    echo "Event ID: " . ($sample->event_id ?? 'N/A') . "\n";
    echo "Is Read: " . ($sample->is_read ?? 'N/A') . "\n";
    echo "Is Notify: " . ($sample->is_notify ?? 'N/A') . "\n";
    echo "Created At: " . ($sample->created_at ?? 'N/A') . "\n";
    echo "Updated At: " . ($sample->updated_at ?? 'N/A') . "\n\n";
}

// Create a test notification if none exist
if ($existingNotifications->count() == 0) {
    echo "Creating test notification...\n";
    $testNotification = Notification::create([
        'user_id' => $user->id,
        'title' => '📅 Test Event Notification',
        'body' => '📍 Test Venue | 🕒 10:00 AM 2025-01-10',
        'event_id' => 1, // Assuming event ID 1 exists
        'type' => 'created',
        'is_read' => 0,
        'is_notify' => 0,
    ]);
    echo "Test notification created with ID: {$testNotification->id}\n\n";
}

// Now test the API endpoint
echo "=== Testing getNewNotifications API ===\n";

$controller = new EventController();
$request = new Request();

// Mock the authenticated user
$request->setUserResolver(function () use ($user) {
    return $user;
});

try {
    $response = $controller->getNewNotifications($request);
    $statusCode = $response->getStatusCode();
    $content = $response->getContent();
    
    echo "Response Status Code: {$statusCode}\n";
    echo "Response Content:\n{$content}\n\n";
    
    // Parse the JSON response
    $data = json_decode($content, true);
    
    if ($data) {
        echo "=== Parsed Response Analysis ===\n";
        echo "Status: " . ($data['status'] ? 'true' : 'false') . "\n";
        echo "Data Type: " . gettype($data['data']) . "\n";
        
        if (isset($data['data']) && is_array($data['data'])) {
            echo "Notifications Count: " . count($data['data']) . "\n\n";
            
            if (count($data['data']) > 0) {
                echo "=== First Notification Structure ===\n";
                $firstNotification = $data['data'][0];
                
                foreach ($firstNotification as $key => $value) {
                    echo "{$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
                
                echo "\n=== All Notification Fields Available ===\n";
                $allFields = [];
                foreach ($data['data'] as $notification) {
                    $allFields = array_merge($allFields, array_keys($notification));
                }
                $uniqueFields = array_unique($allFields);
                sort($uniqueFields);
                
                foreach ($uniqueFields as $field) {
                    echo "- {$field}\n";
                }
            }
        }
    } else {
        echo "Failed to parse JSON response\n";
    }
    
} catch (Exception $e) {
    echo "Error testing API: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";