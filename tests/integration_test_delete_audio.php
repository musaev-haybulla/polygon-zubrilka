<?php
/**
 * Integration test for delete_audio.php changes
 * Tests that AudioFileHelper methods are called correctly in deletion workflow
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';

class DeleteAudioIntegrationTest
{
    private static $testFragmentId = 998;
    
    public static function testDeleteAudioWorkflow(): void
    {
        echo "Testing delete_audio.php workflow...\n";
        
        // Ensure test directory exists
        AudioFileHelper::ensureFragmentDirectory(self::$testFragmentId);
        
        // Test 1: Create test files to simulate audio deletion
        $mainFile = 'test-main-' . time() . '.mp3';
        $originalFile = 'test-original-' . time() . '.mp3';
        
        $mainPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $mainFile);
        $originalPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $originalFile);
        
        // Create test files
        file_put_contents($mainPath, 'test main audio content');
        file_put_contents($originalPath, 'test original audio content');
        
        if (!file_exists($mainPath) || !file_exists($originalPath)) {
            throw new Exception('Failed to create test files');
        }
        
        // Test 2: Simulate the deletion logic from delete_audio.php
        $audioData = [
            'filename' => $mainFile,
            'original_filename' => $originalFile,
            'fragment_id' => self::$testFragmentId
        ];
        
        // Simulate deletion workflow
        if ($audioData['filename']) {
            $result1 = AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['filename']);
            if (!$result1) {
                throw new Exception('Failed to delete main file');
            }
        }
        
        if ($audioData['original_filename']) {
            $result2 = AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['original_filename']);
            if (!$result2) {
                throw new Exception('Failed to delete original file');
            }
        }
        
        // Test 3: Verify files are deleted
        if (file_exists($mainPath)) {
            throw new Exception('Main file should be deleted');
        }
        
        if (file_exists($originalPath)) {
            throw new Exception('Original file should be deleted');
        }
        
        echo "✓ Delete audio workflow test passed\n";
    }
    
    public static function testDeleteNonExistentFiles(): void
    {
        echo "Testing deletion of non-existent files...\n";
        
        // Test deletion of files that don't exist (should not fail)
        $result1 = AudioFileHelper::deleteAudioFile(self::$testFragmentId, 'non-existent-main.mp3', false);
        $result2 = AudioFileHelper::deleteAudioFile(self::$testFragmentId, 'non-existent-original.mp3', false);
        
        if (!$result1 || !$result2) {
            throw new Exception('Deletion of non-existent files should return true');
        }
        
        echo "✓ Non-existent files deletion test passed\n";
    }
    
    public static function testTransactionSafety(): void
    {
        echo "Testing transaction safety...\n";
        
        // Simulate the transaction logic structure from delete_audio.php
        $audioData = [
            'filename' => 'test-transaction-' . time() . '.mp3',
            'original_filename' => null,
            'fragment_id' => self::$testFragmentId
        ];
        
        // Create test file
        $filePath = AudioFileHelper::getAbsoluteAudioPath($audioData['fragment_id'], $audioData['filename']);
        file_put_contents($filePath, 'test content');
        
        try {
            // Simulate transaction begin
            $transactionActive = true;
            
            // File deletion (should work even if transaction fails later)
            if ($audioData['filename']) {
                AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['filename']);
            }
            
            // Verify file is deleted
            if (file_exists($filePath)) {
                throw new Exception('File should be deleted even in transaction context');
            }
            
            // Simulate transaction commit
            $transactionActive = false;
            
        } catch (Exception $e) {
            if ($transactionActive) {
                // Simulate rollback - but file operations can't be rolled back
                // This is expected behavior
            }
            throw $e;
        }
        
        echo "✓ Transaction safety test passed\n";
    }
    
    public static function runAllTests(): void
    {
        echo "Running delete_audio.php integration tests...\n";
        
        try {
            self::testDeleteAudioWorkflow();
            self::testDeleteNonExistentFiles();
            self::testTransactionSafety();
            
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
    DeleteAudioIntegrationTest::runAllTests();
}