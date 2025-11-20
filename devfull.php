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
    
    public function test_write() {
        echo "Attempting to write to " . $this->target . "...\n";
        
        if (!$this->validate_target()) {
            return 2;
        }
        
        $context = stream_context_create();
        $fp = @fopen($this->target, 'w', false, $context);
        
        if ($fp === false) {
            echo "Error: Cannot open " . $this->target . " for writing.\n";
            return 1;
        }
        
        $bytes_written = @fwrite($fp, $this->test_data);
        $write_error = error_get_last();
        
        $flush_result = @fflush($fp);
        $flush_error = error_get_last();
        
        $close_result = @fclose($fp);
        $close_error = error_get_last();
        
        if ($bytes_written === false) {
            echo "✓ Expected failure: Write returned false.\n";
            echo "  Error: " . ($write_error['message'] ?? 'Unknown error') . "\n";
            return 0;
        }
        
        if ($flush_result === false) {
            echo "✓ Expected failure: Flush failed.\n";
            echo "  Error: " . ($flush_error['message'] ?? 'Unknown error') . "\n";
            return 0;
        }
        
        if ($close_result === false) {
            echo "✓ Expected failure: Close failed.\n";
            echo "  Error: " . ($close_error['message'] ?? 'Unknown error') . "\n";
            return 0;
        }
        
        if (strpos($write_error['message'] ?? '', 'No space left on device') !== false ||
            strpos($flush_error['message'] ?? '', 'No space left on device') !== false ||
            strpos($close_error['message'] ?? '', 'No space left on device') !== false) {
            echo "✓ Expected failure: Disk full simulation successful.\n";
            echo "  Bytes attempted: " . $bytes_written . "\n";
            return 0;
        }
        
        echo "Unexpected result: Write may have succeeded.\n";
        echo "  Bytes written: " . $bytes_written . "\n";
        return 1;
    }
    
    public function test_read() {
        echo "\nTesting read behavior from " . $this->target . "...\n";
        
        $fp = @fopen($this->target, 'r');
        if ($fp === false) {
            echo "Error: Cannot open " . $this->target . " for reading.\n";
            return 1;
        }
        
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
