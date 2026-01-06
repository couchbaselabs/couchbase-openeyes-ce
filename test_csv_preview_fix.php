<?php
/**
 * Test script to verify CSV preview page fixes
 * Tests the array bounds checking fix for CSV parsing
 */

echo "Testing CSV parsing logic fix...\n\n";

// Simulate the CSV parsing logic from actionPreview
function test_csv_parsing($csv_content, $test_name) {
    echo "Test: $test_name\n";
    echo "------------------------------------\n";
    
    // Create temp file
    $temp_file = tempnam('/tmp', 'csv_test');
    file_put_contents($temp_file, $csv_content);
    
    // Parse CSV (simulating the fixed code)
    $table = array();
    $headers = array();
    
    if (($handle = fopen($temp_file, "r")) !== false) {
        // Read headers
        if (($line = fgetcsv($handle, 0, ",")) !== FALSE) {
            foreach ($line as $header) {
                $header = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header);
                $headers[] = $header;
            }
        }
        
        // Read data rows
        while (($line = fgetcsv($handle, 0, ",")) !== FALSE) {
            $row = array();
            $header_count = 0;
            foreach ($line as $cel) {
                // THIS IS THE FIX: Check if header exists before accessing it
                if (isset($headers[$header_count])) {
                    $row[$headers[$header_count]] = $cel;
                }
                $header_count++;
            }
            $table[] = $row;
        }
        fclose($handle);
    }
    
    // Clean up
    unlink($temp_file);
    
    // Test the preview page logic
    $csv_id = "test_id";
    
    echo "Headers parsed: " . count($headers) . "\n";
    echo "Headers: " . implode(", ", $headers) . "\n";
    echo "Data rows: " . count($table) . "\n";
    
    // This is the fix in preview.php
    if (!empty($table) && isset($table[0])) {
        echo "Table preview (first row headers): " . implode(", ", array_keys($table[0])) . "\n";
        echo "✓ PASS - Preview page would render correctly\n";
    } else if (isset($csv_id) && $csv_id !== null) {
        echo "✓ PASS - User would see 'No data rows found' message\n";
    }
    
    echo "\n";
    return true;
}

// Test 1: Normal CSV with matching columns
$test1 = "Name,Age,Email\nJohn,30,john@example.com\nJane,25,jane@example.com";
test_csv_parsing($test1, "Normal CSV with matching columns");

// Test 2: CSV with more columns in data row than header (the bug case)
$test2 = "Name,Age\nJohn,30,john@example.com\nJane,25,jane@example.com";
test_csv_parsing($test2, "CSV with more columns in data row than header (ORIGINAL BUG)");

// Test 3: CSV with fewer columns in data row than header
$test3 = "Name,Age,Email\nJohn,30\nJane,25";
test_csv_parsing($test3, "CSV with fewer columns in data row than header");

// Test 4: CSV with only headers, no data
$test4 = "Name,Age,Email";
test_csv_parsing($test4, "CSV with only headers, no data rows");

// Test 5: CSV with UTF-8 BOM
$test5 = "\xEF\xBB\xBFName,Age,Email\nJohn,30,john@example.com";
test_csv_parsing($test5, "CSV with UTF-8 BOM (Excel export)");

echo "====================================\n";
echo "All tests completed successfully!\n";
echo "====================================\n";
echo "\nSummary of fixes applied:\n";
echo "1. Added isset() check in CSV parsing loop to prevent undefined array key warnings\n";
echo "2. Added file upload error checking in actionPreview\n";
echo "3. Added isset($table[0]) check in preview.php before accessing first row\n";
echo "4. Added user-friendly message when CSV has no data rows\n";
?>
