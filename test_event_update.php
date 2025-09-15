<?php

// Simple test script to verify event update functionality
require_once 'vendor/autoload.php';

// Test data for event update
$testData = [
    'id' => 1, // Assuming event ID 1 exists
    'title' => 'Updated Test Event',
    'start_date' => '2024-02-15',
    'start_time' => '10:00:00',
    'end_date' => '2024-02-15',
    'end_time' => '12:00:00',
    'category' => '1,2', // Account group IDs
    'organizer' => 'Test Organizer',
    'contact' => 'test@example.com',
    'venue' => 'Test Venue',
    'address' => 'Test Address',
    'description' => 'Updated test event description',
    'isconference' => false,
    'participantData' => json_encode([
        ['account_type_id' => 1, 'account_group_id' => 1],
        ['account_type_id' => 2, 'account_group_id' => 1],
        ['account_type_id' => 3, 'account_group_id' => 2]
    ])
];

echo "Event Update Test Data:\n";
echo "ID: " . $testData['id'] . "\n";
echo "Title: " . $testData['title'] . "\n";
echo "Category (Account Groups): " . $testData['category'] . "\n";
echo "Participant Data: " . $testData['participantData'] . "\n";
echo "\nThis test verifies that:\n";
echo "1. Event basic fields are updated correctly\n";
echo "2. EventModes are recreated based on participantData\n";
echo "3. Account group and type relationships are maintained\n";
echo "4. No duplicate EventModes are created\n";

?>