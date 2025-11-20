<?php

$TARGET = "/dev/full";
$DATA = "Test entry: " . date('Y-m-d H:i:s');
$DIVIDER = "=" . str_repeat("=", 49);

function get_timestamp() {
    return date('Y-m-d H:i:s');
}

function print_header() {
    global $DIVIDER, $TARGET, $DATA;
    echo $DIVIDER . "\n";
    echo "Start of /dev/full write test\n";
    echo "Timestamp: " . get_timestamp() . "\n";
    echo "Target file: " . $TARGET . "\n";
    echo "Test data: " . $DATA . "\n";
    echo $DIVIDER . "\n";
}

function print_footer($exit_code = 0) {
    global $DIVIDER;
    echo $DIVIDER . "\n";
    echo "Test complete. Exit code: " . $exit_code . "\n";
    echo $DIVIDER . "\n";
}

function test_dev_full_write() {
    global $TARGET, $DATA;
    
    echo "Attempting to write to " . $TARGET . "...\n";
    
    if (!file_exists($TARGET)) {
        echo "Error: Target device " . $TARGET . " does not exist on this system.\n";
        echo "This test requires a Unix-like system with /dev/full device.\n";
        return 2;
    }
    
    error_clear_last();
    
    try {
        $bytes_written = file_put_contents($TARGET, $DATA);
        $error = error_get_last();
        
        if ($bytes_written === false || $error !== null) {
            if (strpos($error['message'] ?? '', 'No space left on device') !== false) {
                echo "✓ Expected failure: Disk full simulation successful.\n";
                echo "  Error: " . $error['message'] . "\n";
                return 0;
            } else {
                echo "✗ Write failed with unexpected error.\n";
                echo "  Error: " . ($error['message'] ?? 'Unknown error') . "\n";
                return 1;
            }
        } else {
            echo "Unexpected result: Write succeeded (" . $bytes_written . " bytes written).\n";
            echo "This should not happen with /dev/full. Please investigate system behavior.\n";
            return 1;
        }
    } catch (Exception $ex) {
        echo "✗ An unexpected exception occurred during the write operation.\n";
        echo "  Exception type: " . get_class($ex) . "\n";
        echo "  Exception message: " . $ex->getMessage() . "\n";
        return 1;
    }
}

function test_dev_full_read() {
    global $TARGET;
    
    echo "\nTesting read behavior from " . $TARGET . "...\n";
    
    try {
        $data = file_get_contents($TARGET, false, null, 0, 100);
        
        if ($data === false) {
            echo "✗ Read operation failed unexpectedly.\n";
            return 1;
        }
        
        if ($data === str_repeat("\0", strlen($data))) {
            echo "✓ Expected behavior: Read returned null bytes as expected.\n";
            echo "  Bytes read: " . strlen($data) . "\n";
            return 0;
        } elseif ($data === "") {
            echo "? Read returned empty data (unexpected but might be system-dependent).\n";
            return 0;
        } else {
            echo "✗ Unexpected behavior: Read returned non-null data.\n";
            $display_data = bin2hex(substr($data, 0, 50));
            echo "  First 50 bytes (hex): " . $display_data . "\n";
            return 1;
        }
    } catch (Exception $ex) {
        echo "✗ Unexpected exception during read: " . get_class($ex) . ": " . $ex->getMessage() . "\n";
        return 1;
    }
}

function main() {
    print_header();
    
    $write_result = test_dev_full_write();
    $read_result = test_dev_full_read();
    
    $overall_result = ($write_result !== 0 || $read_result !== 0) ? 1 : 0;
    
    print_footer($overall_result);
    exit($overall_result);
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    main();
}

?>
