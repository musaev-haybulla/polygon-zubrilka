<?php
/**
 * Integration test for add_audio_step1.php changes
 * Tests that AudioFileHelper methods are called correctly
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';

class AddAudioStep1IntegrationTest
{
    public static function testDeleteAudioFileUsage(): void
    {
        echo "Testing AudioFileHelper::deleteAudioFile() usage...\n";
        
        // Test 1: Verify method exists and is callable
        if (!method_exists('AudioFileHelper', 'deleteAudioFile')) {
            throw new Exception('AudioFileHelper::deleteAudioFile() method not found');
        }
        
        if (!is_callable(['AudioFileHelper', 'deleteAudioFile'])) {
            throw new Exception('AudioFileHelper::deleteAudioFile() is not callable');
        }
        
        // Test 2: Test with non-existent file (should return true)
        $result = AudioFileHelper::deleteAudioFile(999, 'non-existent-file.mp3', false);
        if (!$result) {
            throw new Exception('deleteAudioFile should return true for non-existent file');
        }
        
        // Test 3: Test basename() extraction (simulating catch block logic)
        $testPath = '/path/to/uploads/audio/123/test-file-123456.mp3';
        $fileName = basename($testPath);
        if ($fileName !== 'test-file-123456.mp3') {
            throw new Exception('basename() extraction failed');
        }
        
        echo "✓ All integration tests passed\n";
    }
    
    public static function testFilePathHandling(): void
    {
        echo "Testing file path handling...\n";
        
        // Simulate the file path creation logic from add_audio_step1.php
        $fragmentId = 123;
        $audioTitle = "Test Audio";
        
        // Test filename generation
        $fileName = AudioFileHelper::generateFilename($audioTitle);
        if (empty($fileName) || !str_ends_with($fileName, '.mp3')) {
            throw new Exception('Filename generation failed');
        }
        
        // Test path generation
        $relativePath = AudioFileHelper::getAudioPath($fragmentId, $fileName);
        $absolutePath = AudioFileHelper::getAbsoluteAudioPath($fragmentId, $fileName);
        
        if (empty($relativePath) || empty($absolutePath)) {
            throw new Exception('Path generation failed');
        }
        
        // Test basename extraction (catch block scenario)
        $extractedName = basename($absolutePath);
        if ($extractedName !== $fileName) {
            throw new Exception('Basename extraction mismatch');
        }
        
        echo "✓ File path handling tests passed\n";
    }
    
    public static function runAllTests(): void
    {
        echo "Running add_audio_step1.php integration tests...\n";
        
        try {
            self::testDeleteAudioFileUsage();
            self::testFilePathHandling();
            echo "All integration tests passed!\n";
        } catch (Exception $e) {
            echo "Integration test failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    AddAudioStep1IntegrationTest::runAllTests();
}