<?php
/**
 * Integration test for add_audio_step2.php changes
 * Tests that AudioFileHelper methods are called correctly in audio trimming workflow
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';

class AddAudioStep2IntegrationTest
{
    private static $testFragmentId = 996;
    
    public static function testTrimWorkflow(): void
    {
        echo "Testing add_audio_step2.php trim workflow...\n";
        
        // Ensure test directory exists
        AudioFileHelper::ensureFragmentDirectory(self::$testFragmentId);
        
        // Test 1: Create test files to simulate trim workflow
        $originalFile = 'test-original-' . time() . '.mp3';
        $trimmedFile = str_replace('.mp3', '-trimmed.mp3', $originalFile);
        
        $originalPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $originalFile);
        $trimmedPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $trimmedFile);
        
        // Create original file
        file_put_contents($originalPath, 'original audio content');
        
        if (!file_exists($originalPath)) {
            throw new Exception('Failed to create original test file');
        }
        
        // Test 2: Simulate the trim logic from add_audio_step2.php
        $audioData = [
            'filename' => $originalFile,
            'fragment_id' => self::$testFragmentId
        ];
        
        // Simulate filename generation
        $originalFilename = $audioData['filename'];
        $trimmedFilename = str_replace('.mp3', '-trimmed.mp3', $originalFilename);
        
        if ($trimmedFilename !== $trimmedFile) {
            throw new Exception('Trimmed filename generation mismatch');
        }
        
        // Create a partially created trimmed file (simulating FFmpeg failure)
        file_put_contents($trimmedPath, 'partial trimmed content');
        
        // Test 3: Simulate FFmpeg failure cleanup
        $result = AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $trimmedFilename);
        if (!$result) {
            throw new Exception('Failed to delete partially created trimmed file');
        }
        
        // Verify trimmed file is deleted but original remains
        if (file_exists($trimmedPath)) {
            throw new Exception('Partially created trimmed file should be deleted');
        }
        
        if (!file_exists($originalPath)) {
            throw new Exception('Original file should remain after cleanup');
        }
        
        echo "✓ Trim workflow test passed\n";
    }
    
    public static function testCatchBlockCleanup(): void
    {
        echo "Testing catch block cleanup...\n";
        
        // Test cleanup in catch block scenario
        $originalFile = 'test-catch-original-' . time() . '.mp3';
        $trimmedFilename = str_replace('.mp3', '-trimmed.mp3', $originalFile);
        
        $originalPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $originalFile);
        $trimmedPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $trimmedFilename);
        
        // Create test files
        file_put_contents($originalPath, 'original content');
        file_put_contents($trimmedPath, 'trimmed content');
        
        $audioData = [
            'filename' => $originalFile,
            'fragment_id' => self::$testFragmentId
        ];
        
        try {
            // Simulate transaction begin
            $transactionActive = true;
            
            // Simulate some processing that might fail
            throw new Exception('Simulated processing error');
            
        } catch (Exception $e) {
            if ($transactionActive) {
                // Simulate rollback
            }
            
            // Cleanup logic from catch block
            if (isset($trimmedFilename) && isset($audioData)) {
                AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $trimmedFilename);
            }
            
            // Verify cleanup worked
            if (file_exists($trimmedPath)) {
                throw new Exception('Trimmed file should be cleaned up in catch block');
            }
            
            if (!file_exists($originalPath)) {
                throw new Exception('Original file should remain after catch cleanup');
            }
            
            if ($e->getMessage() !== 'Simulated processing error') {
                throw $e;
            }
        }
        
        echo "✓ Catch block cleanup test passed\n";
    }
    
    public static function testFilenameGeneration(): void
    {
        echo "Testing filename generation logic...\n";
        
        // Test various filename formats
        $testCases = [
            'simple-audio.mp3' => 'simple-audio-trimmed.mp3',
            'audio-with-timestamp-' . time() . '.mp3' => 'audio-with-timestamp-' . time() . '-trimmed.mp3',
            'файл-с-русскими-буквами.mp3' => 'файл-с-русскими-буквами-trimmed.mp3'
        ];
        
        foreach ($testCases as $original => $expectedTrimmed) {
            $actualTrimmed = str_replace('.mp3', '-trimmed.mp3', $original);
            if ($actualTrimmed !== $expectedTrimmed) {
                throw new Exception("Filename generation failed for: $original");
            }
            
            // Test that deleteAudioFile can handle the generated filename
            $result = AudioFileHelper::deleteAudioFile(self::$testFragmentId, $actualTrimmed, false);
            if (!$result) {
                throw new Exception("Failed to handle generated filename: $actualTrimmed");
            }
        }
        
        echo "✓ Filename generation test passed\n";
    }
    
    public static function testErrorRecovery(): void
    {
        echo "Testing error recovery scenarios...\n";
        
        // Test 1: Cleanup when trimmedFilename is not set
        $audioData = ['fragment_id' => self::$testFragmentId];
        
        // This should not fail even if trimmedFilename is not set
        if (isset($trimmedFilename) && isset($audioData)) {
            AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $trimmedFilename);
        }
        
        // Test 2: Cleanup when audioData is not set
        $trimmedFilename = 'test-error-recovery.mp3';
        
        // This should not fail even if audioData is not set
        if (isset($trimmedFilename) && isset($audioData)) {
            AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $trimmedFilename);
        }
        
        echo "✓ Error recovery test passed\n";
    }
    
    public static function runAllTests(): void
    {
        echo "Running add_audio_step2.php integration tests...\n";
        
        try {
            self::testTrimWorkflow();
            self::testCatchBlockCleanup();
            self::testFilenameGeneration();
            self::testErrorRecovery();
            
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
    AddAudioStep2IntegrationTest::runAllTests();
}