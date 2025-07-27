<?php
/**
 * Integration test for restore_original_audio.php changes
 * Tests that AudioFileHelper methods are called correctly in restore workflow
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';

class RestoreOriginalAudioIntegrationTest
{
    private static $testFragmentId = 997;
    
    public static function testRestoreWorkflow(): void
    {
        echo "Testing restore_original_audio.php workflow...\n";
        
        // Ensure test directory exists
        AudioFileHelper::ensureFragmentDirectory(self::$testFragmentId);
        
        // Test 1: Create test files to simulate restore workflow
        $originalFile = 'test-original-' . time() . '.mp3';
        $trimmedFile = 'test-trimmed-' . time() . '.mp3';
        
        $originalPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $originalFile);
        $trimmedPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $trimmedFile);
        
        // Create test files
        file_put_contents($originalPath, 'original audio content');
        file_put_contents($trimmedPath, 'trimmed audio content');
        
        if (!file_exists($originalPath) || !file_exists($trimmedPath)) {
            throw new Exception('Failed to create test files');
        }
        
        // Test 2: Simulate the restore logic from restore_original_audio.php
        $audioData = [
            'filename' => $trimmedFile,
            'original_filename' => $originalFile,
            'fragment_id' => self::$testFragmentId
        ];
        
        // Verify original file exists (as done in the script)
        $originalPath = AudioFileHelper::getAbsoluteAudioPath($audioData['fragment_id'], $audioData['original_filename']);
        if (!file_exists($originalPath)) {
            throw new Exception('Original file should exist for restore test');
        }
        
        // Simulate deletion of trimmed file using unified method
        $result = AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['filename']);
        if (!$result) {
            throw new Exception('Failed to delete trimmed file');
        }
        
        // Test 3: Verify trimmed file is deleted but original remains
        if (file_exists($trimmedPath)) {
            throw new Exception('Trimmed file should be deleted');
        }
        
        if (!file_exists($originalPath)) {
            throw new Exception('Original file should remain after restore');
        }
        
        echo "✓ Restore workflow test passed\n";
    }
    
    public static function testRestoreNonExistentTrimmedFile(): void
    {
        echo "Testing restore with non-existent trimmed file...\n";
        
        // Test deletion of trimmed file that doesn't exist (should not fail)
        $result = AudioFileHelper::deleteAudioFile(self::$testFragmentId, 'non-existent-trimmed.mp3', false);
        
        if (!$result) {
            throw new Exception('Deletion of non-existent trimmed file should return true');
        }
        
        echo "✓ Non-existent trimmed file test passed\n";
    }
    
    public static function testTransactionRollbackSafety(): void
    {
        echo "Testing transaction rollback safety...\n";
        
        // Create test files
        $originalFile = 'test-rollback-original-' . time() . '.mp3';
        $trimmedFile = 'test-rollback-trimmed-' . time() . '.mp3';
        
        $originalPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $originalFile);
        $trimmedPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $trimmedFile);
        
        file_put_contents($originalPath, 'original content');
        file_put_contents($trimmedPath, 'trimmed content');
        
        $audioData = [
            'filename' => $trimmedFile,
            'original_filename' => $originalFile,
            'fragment_id' => self::$testFragmentId
        ];
        
        try {
            // Simulate transaction begin
            $transactionActive = true;
            
            // File deletion (this can't be rolled back)
            AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['filename']);
            
            // Verify file is deleted
            if (file_exists($trimmedPath)) {
                throw new Exception('Trimmed file should be deleted');
            }
            
            // Simulate a database error that would cause rollback
            // In real scenario, the file deletion can't be undone
            throw new Exception('Simulated database error');
            
        } catch (Exception $e) {
            if ($transactionActive) {
                // Simulate rollback - file operations can't be rolled back
                // This is expected behavior and should be handled by the application
            }
            
            // Verify that file is still deleted (can't be rolled back)
            if (file_exists($trimmedPath)) {
                throw new Exception('File deletion cannot be rolled back (expected behavior)');
            }
            
            if ($e->getMessage() !== 'Simulated database error') {
                throw $e;
            }
        }
        
        echo "✓ Transaction rollback safety test passed\n";
    }
    
    public static function testFilePathHandling(): void
    {
        echo "Testing file path handling...\n";
        
        // Test that the method works with various filename formats
        $testFiles = [
            'simple-file.mp3',
            'file-with-timestamp-' . time() . '.mp3',
            'файл-с-русскими-буквами-' . time() . '.mp3'
        ];
        
        foreach ($testFiles as $filename) {
            // Test that deleteAudioFile can handle various filename formats
            $result = AudioFileHelper::deleteAudioFile(self::$testFragmentId, $filename, false);
            if (!$result) {
                throw new Exception("Failed to handle filename: $filename");
            }
        }
        
        echo "✓ File path handling test passed\n";
    }
    
    public static function runAllTests(): void
    {
        echo "Running restore_original_audio.php integration tests...\n";
        
        try {
            self::testRestoreWorkflow();
            self::testRestoreNonExistentTrimmedFile();
            self::testTransactionRollbackSafety();
            self::testFilePathHandling();
            
            // Cleanup test directory
            self::cleanup();
            
            echo "All integration tests passed!\n";
        } catch (Exception $e) {
            self::cleanup();
            echo "Integration test failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private static function cleanup(): void
    {
        $testDir = __DIR__ . '/../uploads/audio/' . self::$testFragmentId;
        if (is_dir($testDir)) {
            $files = array_diff(scandir($testDir), ['.', '..']);
            foreach ($files as $file) {
                unlink($testDir . '/' . $file);
            }
            rmdir($testDir);
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    RestoreOriginalAudioIntegrationTest::runAllTests();
}