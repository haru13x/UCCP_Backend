<?php

require_once 'bootstrap/app.php';

use App\Models\User;
use App\Models\Notification;
use App\Http\Controllers\EventController;
use Illuminate\Http\Request;

echo "=== DEBUGGING UNDEFINED VALUES IN NOTIFICATIONS ===\n\n";

try {
    // Get or create a test user
    $user = User::first();
    if (!$user) {
        echo "❌ No users found in database. Cannot test notifications.\n";
        exit(1);
    }
    
    echo "✅ Using test user: ID {$user->id}, Email: {$user->email}\n\n";

    // Clean up existing notifications
    Notification::where('user_id', $user->id)->delete();
    echo "🧹 Cleaned up existing notifications\n\n";

    // Create test notifications with potential undefined scenarios
    $testCases = [
        // Complete notification
        [
            'user_id' => $user->id,
            'title' => 'Complete Notification',
            'body' => 'This notification has all fields properly set',
            'type' => 'event_created',
            'event_id' => 1,
            'is_read' => 0,
            'is_notify' => 0
        ],
        // Missing optional fields
        [
            'user_id' => $user->id,
            'title' => 'Missing Event ID',
            'body' => 'This notification has no event_id',
            'type' => 'general',
            'event_id' => null,
            'is_read' => 0,
            'is_notify' => 0
        ],
        // Empty strings
        [
            'user_id' => $user->id,
            'title' => '',
            'body' => '',
            'type' => 'event_reminder',
            'event_id' => 2,
            'is_read' => 0,
            'is_notify' => 0
        ],
        // Null values
        [
            'user_id' => $user->id,
            'title' => null,
            'body' => null,
            'type' => 'event_updated',
            'event_id' => null,
            'is_read' => 0,
            'is_notify' => 0
        ]
    ];

    echo "📝 Creating test notifications with various scenarios...\n";
    $createdNotifications = [];
    
    foreach ($testCases as $index => $testCase) {
        try {
            $notification = Notification::create($testCase);
            $createdNotifications[] = $notification;
            echo "  ✅ Created notification {$notification->id}: " . 
                 ($testCase['title'] ?: '[EMPTY/NULL TITLE]') . "\n";
        } catch (Exception $e) {
            echo "  ❌ Failed to create notification " . ($index + 1) . ": " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== TESTING DATABASE RETRIEVAL ===\n";
    
    // Test direct database query
    $dbNotifications = Notification::where('user_id', $user->id)
        ->where('is_notify', 0)
        ->orderBy('created_at', 'asc')
        ->get();
    
    echo "📊 Retrieved {$dbNotifications->count()} notifications from database\n\n";
    
    foreach ($dbNotifications as $index => $notification) {
        echo "--- Database Notification " . ($index + 1) . " ---\n";
        echo "ID: " . ($notification->id ?? 'UNDEFINED') . " (Type: " . gettype($notification->id) . ")\n";
        echo "Title: " . ($notification->title ?? 'UNDEFINED') . " (Type: " . gettype($notification->title) . ")\n";
        echo "Body: " . ($notification->body ?? 'UNDEFINED') . " (Type: " . gettype($notification->body) . ")\n";
        echo "Type: " . ($notification->type ?? 'UNDEFINED') . " (Type: " . gettype($notification->type) . ")\n";
        echo "Event ID: " . ($notification->event_id ?? 'UNDEFINED') . " (Type: " . gettype($notification->event_id) . ")\n";
        echo "Is Read: " . ($notification->is_read ?? 'UNDEFINED') . " (Type: " . gettype($notification->is_read) . ")\n";
        echo "Is Notify: " . ($notification->is_notify ?? 'UNDEFINED') . " (Type: " . gettype($notification->is_notify) . ")\n";
        echo "Created At: " . ($notification->created_at ?? 'UNDEFINED') . " (Type: " . gettype($notification->created_at) . ")\n";
        echo "Updated At: " . ($notification->updated_at ?? 'UNDEFINED') . " (Type: " . gettype($notification->updated_at) . ")\n";
        echo "\n";
    }

    echo "=== TESTING toArray() METHOD ===\n";
    
    $arrayData = $dbNotifications->toArray();
    echo "📊 toArray() returned " . count($arrayData) . " notifications\n\n";
    
    foreach ($arrayData as $index => $notification) {
        echo "--- Array Notification " . ($index + 1) . " ---\n";
        foreach ($notification as $key => $value) {
            $displayValue = $value;
            $type = gettype($value);
            
            if ($value === null) {
                $displayValue = 'NULL';
            } elseif ($value === '') {
                $displayValue = '[EMPTY STRING]';
            } elseif (is_string($value) && strlen($value) > 50) {
                $displayValue = substr($value, 0, 50) . '... (Length: ' . strlen($value) . ')';
            }
            
            echo "{$key}: {$displayValue} (Type: {$type})\n";
        }
        echo "\n";
    }

    echo "=== TESTING API CONTROLLER ===\n";
    
    // Create mock request
    $request = new Request();
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    $controller = new EventController();
    $response = $controller->getNewNotifications($request);
    
    echo "📡 API Response Status: " . $response->getStatusCode() . "\n";
    
    $responseData = json_decode($response->getContent(), true);
    echo "📡 API Response Status Field: " . ($responseData['status'] ? 'true' : 'false') . "\n";
    echo "📡 API Response Data Count: " . count($responseData['data']) . "\n\n";
    
    foreach ($responseData['data'] as $index => $notification) {
        echo "--- API Response Notification " . ($index + 1) . " ---\n";
        foreach ($notification as $key => $value) {
            $displayValue = $value;
            $type = gettype($value);
            
            if ($value === null) {
                $displayValue = 'NULL';
            } elseif ($value === '') {
                $displayValue = '[EMPTY STRING]';
            } elseif (is_string($value) && strlen($value) > 50) {
                $displayValue = substr($value, 0, 50) . '... (Length: ' . strlen($value) . ')';
            }
            
            echo "{$key}: {$displayValue} (Type: {$type})\n";
        }
        echo "\n";
    }

    echo "=== TESTING MOBILE APP SIMULATION ===\n";
    
    echo "🔍 Simulating how mobile app would handle this data...\n\n";
    
    foreach ($responseData['data'] as $index => $notification) {
        echo "--- Mobile App Display " . ($index + 1) . " ---\n";
        
        // Simulate mobile app processing
        $title = $notification['title'] ?? null;
        $body = $notification['body'] ?? null;
        $eventId = $notification['event_id'] ?? null;
        $createdAt = $notification['created_at'] ?? null;
        
        echo "Original Title: " . ($title === null ? 'NULL' : ($title === '' ? '[EMPTY]' : $title)) . "\n";
        echo "Original Body: " . ($body === null ? 'NULL' : ($body === '' ? '[EMPTY]' : substr($body, 0, 30) . '...')) . "\n";
        echo "Original Event ID: " . ($eventId === null ? 'NULL' : $eventId) . "\n";
        echo "Original Created At: " . ($createdAt === null ? 'NULL' : $createdAt) . "\n";
        
        // Apply mobile app fallbacks
        $displayTitle = $title ?: 'Notification';
        $displayBody = $body ?: 'No description available';
        $displayEventId = $eventId ?? 'N/A';
        
        echo "Display Title: {$displayTitle}\n";
        echo "Display Body: {$displayBody}\n";
        echo "Display Event ID: {$displayEventId}\n";
        
        // Test formatTime simulation
        try {
            if (!$createdAt) {
                $displayTime = 'Unknown time';
            } else {
                $date = new DateTime($createdAt);
                if (!$date) {
                    $displayTime = 'Invalid date';
                } else {
                    $displayTime = $date->format('Y-m-d H:i:s');
                }
            }
            echo "Display Time: {$displayTime}\n";
        } catch (Exception $e) {
            echo "Display Time: Error formatting date - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    echo "=== UNDEFINED VALUE ANALYSIS ===\n";
    
    $undefinedFields = [];
    $emptyFields = [];
    $nullFields = [];
    
    foreach ($responseData['data'] as $notification) {
        foreach ($notification as $key => $value) {
            if ($value === null) {
                $nullFields[$key] = ($nullFields[$key] ?? 0) + 1;
            } elseif ($value === '') {
                $emptyFields[$key] = ($emptyFields[$key] ?? 0) + 1;
            }
        }
    }
    
    if (!empty($nullFields)) {
        echo "🔍 NULL Fields Found:\n";
        foreach ($nullFields as $field => $count) {
            echo "  - {$field}: {$count} occurrences\n";
        }
        echo "\n";
    }
    
    if (!empty($emptyFields)) {
        echo "🔍 Empty String Fields Found:\n";
        foreach ($emptyFields as $field => $count) {
            echo "  - {$field}: {$count} occurrences\n";
        }
        echo "\n";
    }
    
    if (empty($nullFields) && empty($emptyFields)) {
        echo "✅ No NULL or empty fields found in API response\n\n";
    }

    echo "=== RECOMMENDATIONS ===\n";
    
    if (!empty($nullFields) || !empty($emptyFields)) {
        echo "🔧 Issues found that could cause 'undefined' values:\n";
        
        if (isset($nullFields['title']) || isset($emptyFields['title'])) {
            echo "  - Title field has NULL/empty values - ensure database constraints\n";
        }
        
        if (isset($nullFields['body']) || isset($emptyFields['body'])) {
            echo "  - Body field has NULL/empty values - ensure database constraints\n";
        }
        
        if (isset($nullFields['created_at']) || isset($emptyFields['created_at'])) {
            echo "  - Created_at field has NULL/empty values - check timestamp handling\n";
        }
        
        echo "\n🔧 Suggested fixes:\n";
        echo "  1. Add database constraints for required fields\n";
        echo "  2. Add validation in the API controller\n";
        echo "  3. Ensure mobile app handles all edge cases\n";
        echo "  4. Add default values in the Notification model\n";
    } else {
        echo "✅ No issues found that would cause 'undefined' values\n";
        echo "✅ The mobile app fallbacks should handle all edge cases\n";
        echo "✅ Console logging improvements should prevent undefined in logs\n";
    }

} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG TEST COMPLETE ===\n";

?>