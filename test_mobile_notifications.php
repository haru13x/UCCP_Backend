<?php
require_once 'vendor/autoload.php';
require_once 'app/Http/Controllers/EventController.php';
require_once 'app/Models/User.php';
require_once 'app/Models/Notification.php';

use App\Http\Controllers\EventController;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

echo "=== MOBILE APP NOTIFICATION TEST ===\n\n";

try {
    // Find or create test user
    $testUser = User::where('email', 'test@example.com')->first();
    if (!$testUser) {
        $testUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        echo "Created test user with ID: {$testUser->id}\n";
    } else {
        echo "Using existing test user with ID: {$testUser->id}\n";
    }

    // Clean up existing notifications for this user
    Notification::where('user_id', $testUser->id)->delete();
    echo "Cleaned up existing notifications\n\n";

    // Create test notifications with different types and data
    $notificationTypes = [
        [
            'type' => 'event_created',
            'title' => 'New Event: Tech Conference 2024',
            'body' => 'A new technology conference has been created. Join us for exciting talks and networking!',
            'event_id' => 1
        ],
        [
            'type' => 'event_reminder',
            'title' => 'Event Reminder: Workshop Tomorrow',
            'body' => 'Don\'t forget about the React Native workshop starting at 9:00 AM tomorrow.',
            'event_id' => 2
        ],
        [
            'type' => 'event_updated',
            'title' => 'Event Updated: Venue Changed',
            'body' => 'The venue for the AI Summit has been changed to the Grand Convention Center.',
            'event_id' => 3
        ],
        [
            'type' => 'registration_confirmed',
            'title' => 'Registration Confirmed',
            'body' => 'Your registration for the Mobile Development Bootcamp has been confirmed.',
            'event_id' => 4
        ],
        [
            'type' => 'event_cancelled',
            'title' => 'Event Cancelled: Photography Workshop',
            'body' => 'Unfortunately, the photography workshop has been cancelled due to low enrollment.',
            'event_id' => 5
        ]
    ];

    echo "Creating test notifications...\n";
    foreach ($notificationTypes as $index => $notifData) {
        $notification = Notification::create([
            'user_id' => $testUser->id,
            'title' => $notifData['title'],
            'body' => $notifData['body'],
            'type' => $notifData['type'],
            'event_id' => $notifData['event_id'],
            'is_read' => $index % 2 === 0 ? 0 : 1, // Mix of read/unread
            'is_notify' => 0, // Unnotified so they appear in /new endpoint
            'created_at' => now()->subMinutes($index * 10), // Different timestamps
            'updated_at' => now()->subMinutes($index * 10),
        ]);
        
        echo "  - Created notification ID {$notification->id}: {$notifData['type']}\n";
    }

    echo "\n=== TESTING NOTIFICATIONS/NEW ENDPOINT ===\n";
    
    // Simulate the mobile app calling notifications/new
    $request = new Request();
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    $controller = new EventController();
    $response = $controller->getNewNotifications($request);
    $responseData = json_decode($response->getContent(), true);
    
    echo "API Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Structure:\n";
    echo "  - Success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
    echo "  - Message: " . $responseData['message'] . "\n";
    echo "  - Data Count: " . count($responseData['data']) . "\n\n";
    
    if (!empty($responseData['data'])) {
        echo "=== NOTIFICATION DATA STRUCTURE ===\n";
        foreach ($responseData['data'] as $index => $notification) {
            echo "Notification " . ($index + 1) . ":\n";
            echo "  - ID: " . $notification['id'] . " (Type: " . gettype($notification['id']) . ")\n";
            echo "  - User ID: " . $notification['user_id'] . " (Type: " . gettype($notification['user_id']) . ")\n";
            echo "  - Title: " . $notification['title'] . " (Length: " . strlen($notification['title']) . ")\n";
            echo "  - Body: " . substr($notification['body'], 0, 50) . "... (Length: " . strlen($notification['body']) . ")\n";
            echo "  - Type: " . $notification['type'] . "\n";
            echo "  - Event ID: " . ($notification['event_id'] ?? 'null') . " (Type: " . gettype($notification['event_id']) . ")\n";
            echo "  - Is Read: " . ($notification['is_read'] ? 'true' : 'false') . " (Type: " . gettype($notification['is_read']) . ")\n";
            echo "  - Is Notify: " . ($notification['is_notify'] ? 'true' : 'false') . " (Type: " . gettype($notification['is_notify']) . ")\n";
            echo "  - Created At: " . $notification['created_at'] . "\n";
            echo "  - Updated At: " . $notification['updated_at'] . "\n";
            echo "\n";
        }
    }

    echo "=== TESTING NOTIFICATIONS/ALL ENDPOINT ===\n";
    
    // Test the /all endpoint that NotificationScreen uses
    $allResponse = $controller->getAllNotifications($request);
    $allResponseData = json_decode($allResponse->getContent(), true);
    
    echo "All Notifications API Response Status: " . $allResponse->getStatusCode() . "\n";
    echo "All Notifications Count: " . count($allResponseData['data']) . "\n";
    echo "Unread Count: " . count(array_filter($allResponseData['data'], function($n) { return !$n['is_read']; })) . "\n";
    echo "Read Count: " . count(array_filter($allResponseData['data'], function($n) { return $n['is_read']; })) . "\n\n";

    echo "=== MOBILE APP DISPLAY SIMULATION ===\n";
    
    // Simulate how the mobile app would display these notifications
    foreach ($allResponseData['data'] as $notification) {
        $isUnread = !$notification['is_read'];
        $timeAgo = calculateTimeAgo($notification['created_at']);
        
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ " . ($isUnread ? "🔵 " : "   ") . str_pad(substr($notification['title'], 0, 45), 45) . " │ " . str_pad($timeAgo, 8) . " │\n";
        echo "│   " . str_pad(substr($notification['body'], 0, 55), 55) . "   │\n";
        echo "│   Type: " . str_pad($notification['type'], 20) . " Event ID: " . str_pad($notification['event_id'] ?? 'N/A', 10) . "   │\n";
        echo "└─────────────────────────────────────────────────────────────┘\n";
    }

    echo "\n=== NOTIFICATION CLICK SIMULATION ===\n";
    
    // Simulate clicking on a notification with event_id
    $clickedNotification = $allResponseData['data'][0];
    echo "Simulating click on notification: " . $clickedNotification['title'] . "\n";
    echo "Event ID: " . $clickedNotification['event_id'] . "\n";
    echo "Action: Would navigate to EventDetails screen with event_id=" . $clickedNotification['event_id'] . "\n";
    echo "Mark as read: Would call markAsRead(" . $clickedNotification['id'] . ")\n\n";

    echo "=== DATABASE STATE AFTER TESTING ===\n";
    $finalNotifications = Notification::where('user_id', $testUser->id)->get();
    echo "Total notifications in database: " . $finalNotifications->count() . "\n";
    echo "Notified notifications: " . $finalNotifications->where('is_notify', 1)->count() . "\n";
    echo "Unnotified notifications: " . $finalNotifications->where('is_notify', 0)->count() . "\n";
    echo "Read notifications: " . $finalNotifications->where('is_read', 1)->count() . "\n";
    echo "Unread notifications: " . $finalNotifications->where('is_read', 0)->count() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

function calculateTimeAgo($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

echo "\n=== TEST COMPLETED ===\n";
?>