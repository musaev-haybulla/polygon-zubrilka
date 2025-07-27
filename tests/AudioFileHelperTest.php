<?php
/**
 * Unit tests for AudioFileHelper class
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';

class AudioFileHelperTest
{
    private static $testFragmentId = 999;

    public static function runAllTests(): void
    {
        echo "Running AudioFileHelper tests...\n";
        
        self::setUp();
        
        try {
            self::testDeleteAudioFileExisting();
            self::testDeleteAudioFileNonExistent();
            self::testSafeUnlinkExisting();
            self::testSafeUnlinkNonExistent();
            self::testLogFileOperation();
            
            echo "All tests passed!\n";
        } catch (Exception $e) {
            echo "Test failed: " . $e->getMessage() . "\n";
        } finally {
            self::tearDown();
        }
    }

    private static function setUp(): void
    {
        // Создаем тестовую директорию в реальной структуре проекта
        AudioFileHelper::ensureFragmentDirectory(self::$testFragmentId);
    }

    private static function tearDown(): void
    {
        // Очищаем тестовую директорию
        $testDir = __DIR__ . '/../uploads/audio/' . self::$testFragmentId;
        if (is_dir($testDir)) {
            self::removeDirectory($testDir);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private static function testDeleteAudioFileExisting(): void
    {
        $filename = 'test-existing.mp3';
        $filePath = AudioFileHelper::getAbsoluteAudioPath(self::$testFragmentId, $filename);
        
        // Создаем тестовый файл
        file_put_contents($filePath, 'test content');
        
        if (!file_exists($filePath)) {
            throw new Exception('Failed to create test file');
        }
        
        // Тестируем удаление
        $result = AudioFileHelper::deleteAudioFile(self::$testFragmentId, $filename, false);
        
        if (!$result) {
            throw new Exception('deleteAudioFile should return true for existing file');
        }
        
        if (file_exists($filePath)) {
            throw new Exception('File should be deleted');
        }
        
        echo "✓ testDeleteAudioFileExisting passed\n";
    }

    private static function testDeleteAudioFileNonExistent(): void
    {
        $filename = 'test-nonexistent.mp3';
        
        // Тестируем удаление несуществующего файла
        $result = AudioFileHelper::deleteAudioFile(self::$testFragmentId, $filename, false);
        
        if (!$result) {
            throw new Exception('deleteAudioFile should return true for non-existent file');
        }
        
        echo "✓ testDeleteAudioFileNonExistent passed\n";
    }

    private static function testSafeUnlinkExisting(): void
    {
        $filePath = __DIR__ . '/../uploads/audio/' . self::$testFragmentId . '/test-safe-unlink.txt';
        
        // Создаем тестовый файл
        file_put_contents($filePath, 'test content');
        
        // Используем рефлексию для доступа к private методу
        $reflection = new ReflectionClass('AudioFileHelper');
        $method = $reflection->getMethod('safeUnlink');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, $filePath);
        
        if (!$result) {
            throw new Exception('safeUnlink should return true for existing file');
        }
        
        if (file_exists($filePath)) {
            throw new Exception('File should be deleted by safeUnlink');
        }
        
        echo "✓ testSafeUnlinkExisting passed\n";
    }

    private static function testSafeUnlinkNonExistent(): void
    {
        $filePath = __DIR__ . '/../uploads/audio/' . self::$testFragmentId . '/non-existent-file.txt';
        
        // Используем рефлексию для доступа к private методу
        $reflection = new ReflectionClass('AudioFileHelper');
        $method = $reflection->getMethod('safeUnlink');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, $filePath);
        
        if (!$result) {
            throw new Exception('safeUnlink should return true for non-existent file');
        }
        
        echo "✓ testSafeUnlinkNonExistent passed\n";
    }

    private static function testLogFileOperation(): void
    {
        // Используем рефлексию для доступа к private методу
        $reflection = new ReflectionClass('AudioFileHelper');
        $method = $reflection->getMethod('logFileOperation');
        $method->setAccessible(true);
        
        // Тестируем успешную операцию (просто проверяем что не падает)
        $method->invoke(null, 'DELETE', '/test/path.mp3', true);
        
        // Тестируем неуспешную операцию
        $method->invoke(null, 'DELETE', '/test/path.mp3', false, 'Permission denied');
        
        echo "✓ testLogFileOperation passed\n";
    }
}

// Запускаем тесты если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    AudioFileHelperTest::runAllTests();
}