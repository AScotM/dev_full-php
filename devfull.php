<?php

class DevFullTester {
    private $target;
    private $test_data;
    
    public function __construct($target = "/dev/full") {
        $this->target = $target;
        $this->test_data = "Test entry: " . date('Y-m-d H:i:s');
    }
    
    private function get_timestamp() {
        return date('Y-m-d H:i:s');
    }
    
    private function print_divider() {
        echo "=" . str_repeat("=", 49) . "\n";
    }
    
    public function print_header() {
        $this->print_divider();
        echo "Start of /dev/full write test\n";
        echo "Timestamp: " . $this->get_timestamp() . "\n";
        echo "Target file: " . $this->target . "\n";
        echo "Test data: " . $this->test_data . "\n";
        $this->print_divider();
    }
    
    public function print_footer($exit_code = 0) {
        $this->print_divider();
        echo "Test complete. Exit code: " . $exit_code . "\n";
        $this->print_divider();
    }
    
    private function validate_target() {
        if (!file_exists($this->target)) {
            echo "Error: Target device " . $this->target . " does not exist.\n";
            return false;
        }
        
        if (!is_readable($this->target)) {
            echo "Error: Target device " . $this->target . " is not readable.\n";
            return false;
        }
        
        if (!is_writable($this->target)) {
            echo "Error: Target device " . $this->target . " is not writable.\n";
            return false;
        }
        
        $stat = stat($this->target);
        if (($stat['mode'] & 0170000) !== 0020000) {
            echo "Warning: " . $this->target . " may not be a character device.\n";
        }
        
        return true;
    }
    
    private function checkForDiskFullError($error) {
        return strpos($error['message'] ?? '', 'No space left on device') !== false;
    }
    
    private function clear_errors() {
        @trigger_error('');
    }
    
    public function test_write() {
        echo "Attempting to write to " . $this->target . "...\n";
        
        if (!$this->validate_target()) {
            return 2;
        }
        
        $fp = @fopen($this->target, 'w');
        
        if ($fp === false) {
            echo "Error: Cannot open " . $this->target . " for writing.\n";
            return 1;
        }
        
        // Clear any previous errors and attempt write
        $this->clear_errors();
        $bytes_written = @fwrite($fp, $this->test_data);
        $write_error = error_get_last();
        
        // Clear errors and attempt flush
        $this->clear_errors();
        $flush_result = @fflush($fp);
        $flush_error = error_get_last();
        
        // Clear errors and attempt close
        $this->clear_errors();
        $close_result = @fclose($fp);
        $close_error = error_get_last();
        
        // Check for the expected disk full error in any operation
        if ($this->checkForDiskFullError($write_error) ||
            $this->checkForDiskFullError($flush_error) ||
            $this->checkForDiskFullError($close_error)) {
            echo "✓ Expected failure: Disk full simulation successful.\n";
            echo "  Bytes attempted: " . ($bytes_written ?: 0) . "\n";
            if ($write_error) echo "  Write error: " . $write_error['message'] . "\n";
            if ($flush_error) echo "  Flush error: " . $flush_error['message'] . "\n";
            if ($close_error) echo "  Close error: " . $close_error['message'] . "\n";
            return 0;
        }
        
        // If bytes_written is false, that's also a failure (though unlikely with /dev/full)
        if ($bytes_written === false) {
            echo "✓ Expected failure: Write returned false.\n";
            echo "  Error: " . ($write_error['message'] ?? 'Unknown error') . "\n";
            return 0;
        }
        
        // If we got here without the expected error, something might be wrong
        echo "Unexpected result: Expected 'No space left on device' error but didn't get it.\n";
        echo "  Bytes written: " . ($bytes_written ?: 0) . "\n";
        echo "  Write error: " . ($write_error['message'] ?? 'None') . "\n";
        echo "  Flush error: " . ($flush_error['message'] ?? 'None') . "\n";
        echo "  Close error: " . ($close_error['message'] ?? 'None') . "\n";
        return 1;
    }
    
    public function test_read() {
        echo "\nTesting read behavior from " . $this->target . "...\n";
        
        $fp = @fopen($this->target, 'r');
        if ($fp === false) {
            echo "Error: Cannot open " . $this->target . " for reading.\n";
            return 1;
        }
        
        $this->clear_errors();
        $data = @fread($fp, 100);
        $read_error = error_get_last();
        fclose($fp);
        
        if ($data === false) {
            echo "✗ Read operation failed.\n";
            echo "  Error: " . ($read_error['message'] ?? 'Unknown error') . "\n";
            return 1;
        }
        
        $expected_null_bytes = str_repeat("\0", strlen($data));
        if ($data === $expected_null_bytes) {
            echo "✓ Expected behavior: Read returned null bytes.\n";
            echo "  Bytes read: " . strlen($data) . "\n";
            return 0;
        }
        
        echo "✗ Unexpected behavior: Read returned non-null data.\n";
        $display_data = bin2hex(substr($data, 0, min(50, strlen($data))));
        echo "  First bytes (hex): " . $display_data . "\n";
        return 1;
    }
    
    public function run() {
        $this->print_header();
        
        $write_result = $this->test_write();
        $read_result = $this->test_read();
        
        $overall_result = ($write_result !== 0 || $read_result !== 0) ? 1 : 0;
        
        $this->print_footer($overall_result);
        return $overall_result;
    }
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $tester = new DevFullTester();
    exit($tester->run());
}
