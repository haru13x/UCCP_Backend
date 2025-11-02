<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EventController;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

echo "=== Creating Test Notifications for API Testing ===\n\n";

// Get a test user
$user = User::find(1);
if (!$user) {
    $user = User::first();
}

if (!$user) {
    echo "No users found in database. Cannot test notifications.\n";
    exit;
}

echo "Testing with User ID: {$user->id}\n";
echo "User Name: {$user->name}\n\n";

// Create multiple test notifications with different types and data
$testNotifications = [
    [
        'user_id' => $user->id,
        'title' => '📅 New Event: Annual Conference 2025',
        'body' => '📍 Main Auditorium | 🕒 09:00 AM 2025-01-15',
        'event_id' => 1,
        'type' => 'created',
        'is_read' => 0,
        'is_notify' => 0,
    ],
    [
        'user_id' => $user->id,
        'title' => '🔔 Event Reminder: Weekly Prayer Meeting',
        'body' => '📍 Chapel | 🕒 07:00 PM Today',
        'event_id' => 2,
        'type' => 'reminder',
        'is_read' => 0,
        'is_notify' => 0,
    ],
    [
        'user_id' => $user->id,
        'title' => '✅ Registration Confirmed: Youth Camp',
        'body' => '📍 Camp Site | 🕒 08:00 AM 2025-02-01',
        'event_id' => 3,
        'type' => 'registration',
        'is_read' => 0,
        'is_notify' => 0,
    ],
    [
        'user_id' => $user->id,
        'title' => '📝 Event Updated: Sunday Service',
        'body' => '📍 New Location: Community Center | 🕒 10:00 AM Sunday',
        'event_id' => 4,
        'type' => 'updated',
        'is_read' => 0,
        'is_notify' => 0,
    ]
];

// Delete existing unnotified notifications for clean test
Notification::where('user_id', $user->id)->where('is_notify', 0)->delete();

echo "Creating test notifications...\n";
foreach ($testNotifications as $index => $notificationData) {
    $notification = Notification::create($notificationData);
    echo "Created notification " . ($index + 1) . ": ID {$notification->id} - {$notification->title}\n";
}

echo "\n=== Testing getNewNotifications API ===\n";

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
    
    if ($data && isset($data['data']) && is_array($data['data'])) {
        echo "=== Detailed Notification Data Analysis ===\n";
        echo "Status: " . ($data['status'] ? 'true' : 'false') . "\n";
        echo "Notifications Count: " . count($data['data']) . "\n\n";
        
        foreach ($data['data'] as $index => $notification) {
            echo "--- Notification " . ($index + 1) . " ---\n";
            foreach ($notification as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : 
                               (is_null($value) ? 'NULL' : $value);
                echo "{$key}: {$displayValue}\n";
            }
            echo "\n";
        }
        
        // Show all unique fields across all notifications
        echo "=== All Available Fields ===\n";
        $allFields = [];
        foreach ($data['data'] as $notification) {
            $allFields = array_merge($allFields, array_keys($notification));
        }
        $uniqueFields = array_unique($allFields);
        sort($uniqueFields);
        
        foreach ($uniqueFields as $field) {
            echo "- {$field}\n";
        }
        
        echo "\n=== Data Types ===\n";
        if (count($data['data']) > 0) {
            $sample = $data['data'][0];
            foreach ($sample as $key => $value) {
                echo "{$key}: " . gettype($value) . "\n";
            }
        }
    } else {
        echo "No data or invalid response format\n";
    }
    
} catch (Exception $e) {
    echo "Error testing API: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Checking Database State After API Call ===\n";
$afterNotifications = Notification::where('user_id', $user->id)->get();
echo "Total notifications for user: " . $afterNotifications->count() . "\n";
echo "Unnotified notifications: " . $afterNotifications->where('is_notify', 0)->count() . "\n";
echo "Notified notifications: " . $afterNotifications->where('is_notify', 1)->count() . "\n";

echo "\n=== Test Complete ===\n";