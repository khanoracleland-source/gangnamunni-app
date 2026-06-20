<?php
/**
 * API Test Script
 * Run: php -S localhost:8000 index.php
 * Then: php test_api.php
 */

echo "🧪 Hospital API Test\n";
echo "================================================================================\n\n";

$baseUrl = 'http://localhost:8000/api';

function testEndpoint($name, $url) {
    echo "Testing: {$name}\n";
    echo "URL: {$url}\n";

    $response = @file_get_contents($url);
    if ($response === false) {
        echo "❌ Failed to connect\n\n";
        return;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Invalid JSON response\n\n";
        return;
    }

    echo "✅ Success\n";
    echo "Response: " . substr(json_encode($data, JSON_PRETTY_PRINT), 0, 200) . "...\n\n";
}

// Test health check
testEndpoint('Health Check', $baseUrl . '/health');

// Test stats
testEndpoint('Statistics', $baseUrl . '/stats');

// Test categories
testEndpoint('Categories', $baseUrl . '/categories');

// Test all hospitals
testEndpoint('All Hospitals (page 1)', $baseUrl . '/hospitals?page=1&per_page=5');

// Test search
testEndpoint('Search: 성형외과', $baseUrl . '/hospitals?search=성형외과&per_page=3');

// Test filter by rating
testEndpoint('Filter: Rating >= 9.5', $baseUrl . '/hospitals?min_rating=9.5&per_page=3');

// Test single hospital
testEndpoint('Single Hospital (ID: 116)', $baseUrl . '/hospitals/116');

echo "================================================================================\n";
echo "✅ All tests completed\n";
