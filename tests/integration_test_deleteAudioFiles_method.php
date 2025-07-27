<?php
/**
 * Integration test for updated deleteAudioFiles() method
 * Tests that the method correctly uses unified file operations and logging
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';
require_once __DIR__ . '/../config/config.php';

class DeleteAudioFilesMethodTest
{
    private static $testFragmentId = 995;
    private static $pdo;
    
    public static function setUp(): void
    {
        self::$pdo = getPdo();
        
        // Clean up any existing test data first
        self::cleanup();
        
        // Create a test fragment (required for foreign key constraint)
        $stmt = self::$pdo->prepare("
            INSERT INTO fragments (poem_id, owner_id, label, sort_order, grade_level, status, created_at, updated_at)
            VALUES (1, 1, 'Test Fragment', 1, 'primary', 'draft', NOW(), NOW())
        ");
        
        try {
            $stmt->execute();
            self::$testFragmentId = self::$pdo->lastInsertId();
        } catch (Exception $e) {
            // Fragment might already exist, try to find it
            $stmt = self::$pdo->prepare("SELECT id FROM fragments WHERE label = 'Test Fragment' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result) {
                self::$testFragmentId = $result['id'];
            } else {
                throw new Exception('Could not create or find test fragment: ' . $e->getMessage());
            }
        }
        
        // Ensure test directory exists
        AudioFileHelper::ensureFragmentDirectory(self::$testFragmentId);
    }
    
    public static function testDeleteAudioFilesWithBothFiles(): void
    {
        echo "Testing deleteAudioFiles() with both main and original files...\n";
        
        // Create test files
        $mainFile = 'test-main-' . time() . '.mp3';
        $originalFile = 'test-original-' . time() . '.mp3';
        
        $mainPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $mainFile);
        $originalPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $originalFile);
        
        file_put_contents($mainPath, 'main audio content');
        file_put_contents($originalPath, 'original audio content');
        
        // Create test database record
        $stmt = self::$pdo->prepare("
            INSERT INTO audio_tracks (fragment_id, filename, original_filename, duration, is_ai_generated, title, sort_order, status, created_at, updated_at)
            VALUES (?, ?, ?, 10.5, 0, 'Test Audio', 1, 'draft', NOW(), NOW())
        ");
        $stmt->execute([self::$testFragmentId, $mainFile, $originalFile]);
        $audioId = self::$pdo->lastInsertId();
        
        // Create test line and timing record
        $stmt = self::$pdo->prepare("
            INSERT INTO lines (fragment_id, line_number, text, end_line, created_at, updated_at)
            VALUES (?, 1, 'Test line', 1, NOW(), NOW())
        ");
        $stmt->execute([self::$testFragmentId]);
        $lineId = self::$pdo->lastInsertId();
        
        $stmt = self::$pdo->prepare("
            INSERT INTO audio_timings (audio_track_id, line_id, end_time, created_at, updated_at)
            VALUES (?, ?, 5.0, NOW(), NOW())
        ");
        $stmt->execute([$audioId, $lineId]);
        
        // Test deleteAudioFiles method
        $result = AudioFileHelper::deleteAudioFiles(self::$pdo, $audioId);
        
        if (!$result) {
            throw new Exception('deleteAudioFiles should return true');
        }
        
        // Verify files are deleted
        if (file_exists($mainPath)) {
            throw new Exception('Main file should be deleted');
        }
        
        if (file_exists($originalPath)) {
            throw new Exception('Original file should be deleted');
        }
        
        // Verify timing records are deleted
        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM audio_timings WHERE audio_track_id = ?");
        $stmt->execute([$audioId]);
        $timingCount = $stmt->fetchColumn();
        
        if ($timingCount > 0) {
            throw new Exception('Audio timing records should be deleted');
        }
        
        echo "✓ deleteAudioFiles with both files test passed\n";
    }
    
    public static function testDeleteAudioFilesWithMainFileOnly(): void
    {
        echo "Testing deleteAudioFiles() with main file only...\n";
        
        // Create test file
        $mainFile = 'test-main-only-' . time() . '.mp3';
        $mainPath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $mainFile);
        
        file_put_contents($mainPath, 'main audio content');
        
        // Create test database record (no original_filename)
        $stmt = self::$pdo->prepare("
            INSERT INTO audio_tracks (fragment_id, filename, original_filename, duration, is_ai_generated, title, sort_order, status, created_at, updated_at)
            VALUES (?, ?, NULL, 8.3, 1, 'Test AI Audio', 1, 'active', NOW(), NOW())
        ");
        $stmt->execute([self::$testFragmentId, $mainFile]);
        $audioId = self::$pdo->lastInsertId();
        
        // Test deleteAudioFiles method
        $result = AudioFileHelper::deleteAudioFiles(self::$pdo, $audioId);
        
        if (!$result) {
            throw new Exception('deleteAudioFiles should return true');
        }
        
        // Verify main file is deleted
        if (file_exists($mainPath)) {
            throw new Exception('Main file should be deleted');
        }
        
        echo "✓ deleteAudioFiles with main file only test passed\n";
    }
    
    public static function testDeleteAudioFilesNonExistentRecord(): void
    {
        echo "Testing deleteAudioFiles() with non-existent record...\n";
        
        // Test with non-existent audio ID
        $result = AudioFileHelper::deleteAudioFiles(self::$pdo, 99999);
        
        if (!$result) {
            throw new Exception('deleteAudioFiles should return true for non-existent record');
        }
        
        echo "✓ deleteAudioFiles with non-existent record test passed\n";
    }
    
    public static function testDeleteAudioFilesNonExistentFiles(): void
    {
        echo "Testing deleteAudioFiles() with non-existent files...\n";
        
        // Create database record but no actual files
        $mainFile = 'non-existent-main.mp3';
        $originalFile = 'non-existent-original.mp3';
        
        $stmt = self::$pdo->prepare("
            INSERT INTO audio_tracks (fragment_id, filename, original_filename, duration, is_ai_generated, title, sort_order, status, created_at, updated_at)
            VALUES (?, ?, ?, 12.1, 0, 'Test Missing Files', 1, 'draft', NOW(), NOW())
        ");
        $stmt->execute([self::$testFragmentId, $mainFile, $originalFile]);
        $audioId = self::$pdo->lastInsertId();
        
        // Test deleteAudioFiles method (should not fail)
        $result = AudioFileHelper::deleteAudioFiles(self::$pdo, $audioId);
        
        if (!$result) {
            throw new Exception('deleteAudioFiles should return true even with missing files');
        }
        
        echo "✓ deleteAudioFiles with non-existent files test passed\n";
    }
    
    public static function testBackwardCompatibility(): void
    {
        echo "Testing backward compatibility...\n";
        
        // Test that the method signature and behavior remain the same
        $reflection = new ReflectionMethod('AudioFileHelper', 'deleteAudioFiles');
        $parameters = $reflection->getParameters();
        
        if (count($parameters) !== 2) {
            throw new Exception('deleteAudioFiles should have exactly 2 parameters');
        }
        
        if ($parameters[0]->getName() !== 'pdo') {
            throw new Exception('First parameter should be named pdo');
        }
        
        if ($parameters[1]->getName() !== 'audioId') {
            throw new Exception('Second parameter should be named audioId');
        }
        
        if (!$reflection->isStatic()) {
            throw new Exception('deleteAudioFiles should be static');
        }
        
        if (!$reflection->isPublic()) {
            throw new Exception('deleteAudioFiles should be public');
        }
        
        echo "✓ Backward compatibility test passed\n";
    }
    
    public static function runAllTests(): void
    {
        echo "Running deleteAudioFiles() method integration tests...\n";
        
        try {
            self::setUp();
            
            self::testDeleteAudioFilesWithBothFiles();
            self::testDeleteAudioFilesWithMainFileOnly();
            self::testDeleteAudioFilesNonExistentRecord();
            self::testDeleteAudioFilesNonExistentFiles();
            self::testBackwardCompatibility();
            
            self::cleanup();
            
            echo "All deleteAudioFiles() method tests passed!\n";
        } catch (Exception $e) {
            self::cleanup();
            echo "Integration test failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private static function cleanup(): void
    {
        // Clean up test database records
        if (self::$pdo && self::$testFragmentId) {
            $stmt = self::$pdo->prepare("DELETE FROM audio_timings WHERE audio_track_id IN (SELECT id FROM audio_tracks WHERE fragment_id = ?)");
            $stmt->execute([self::$testFragmentId]);
            
            $stmt = self::$pdo->prepare("DELETE FROM audio_tracks WHERE fragment_id = ?");
            $stmt->execute([self::$testFragmentId]);
            
            $stmt = self::$pdo->prepare("DELETE FROM lines WHERE fragment_id = ?");
            $stmt->execute([self::$testFragmentId]);
            
            $stmt = self::$pdo->prepare("DELETE FROM fragments WHERE id = ? AND label = 'Test Fragment'");
            $stmt->execute([self::$testFragmentId]);
        }
        
        // Clean up test files
        if (self::$testFragmentId) {
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
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    DeleteAudioFilesMethodTest::runAllTests();
}