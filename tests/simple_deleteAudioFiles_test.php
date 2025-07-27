<?php
/**
 * Simple test for updated deleteAudioFiles() method
 * Tests basic functionality without complex database setup
 */

require_once __DIR__ . '/../classes/AudioFileHelper.php';

class SimpleDeleteAudioFilesTest
{
    public static function testMethodExists(): void
    {
        echo "Testing deleteAudioFiles() method exists...\n";
        
        if (!method_exists('AudioFileHelper', 'deleteAudioFiles')) {
            throw new Exception('deleteAudioFiles method not found');
        }
        
        if (!is_callable(['AudioFileHelper', 'deleteAudioFiles'])) {
            throw new Exception('deleteAudioFiles method is not callable');
        }
        
        echo "✓ Method exists and is callable\n";
    }
    
    public static function testMethodSignature(): void
    {
        echo "Testing deleteAudioFiles() method signature...\n";
        
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
        
        echo "✓ Method signature is correct\n";
    }
    
    public static function testUsesUnifiedMethods(): void
    {
        echo "Testing that deleteAudioFiles() uses unified methods...\n";
        
        // Read the method source to verify it uses deleteAudioFile()
        $reflection = new ReflectionMethod('AudioFileHelper', 'deleteAudioFiles');
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();
        
        $lines = file($filename);
        $methodSource = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        
        // Check that it uses deleteAudioFile() instead of direct unlink()
        if (strpos($methodSource, 'deleteAudioFile(') === false) {
            throw new Exception('deleteAudioFiles should use deleteAudioFile() method');
        }
        
        if (strpos($methodSource, 'unlink(') !== false) {
            throw new Exception('deleteAudioFiles should not use direct unlink() calls');
        }
        
        // Check that it uses logFileOperation()
        if (strpos($methodSource, 'logFileOperation(') === false) {
            throw new Exception('deleteAudioFiles should use logFileOperation() for logging');
        }
        
        echo "✓ Method uses unified methods correctly\n";
    }
    
    public static function testBackwardCompatibility(): void
    {
        echo "Testing backward compatibility...\n";
        
        // Test that the method signature hasn't changed
        $reflection = new ReflectionMethod('AudioFileHelper', 'deleteAudioFiles');
        
        // Check return type
        $returnType = $reflection->getReturnType();
        if ($returnType && $returnType->getName() !== 'bool') {
            throw new Exception('deleteAudioFiles should return bool');
        }
        
        // Check parameter types
        $parameters = $reflection->getParameters();
        $pdoParam = $parameters[0];
        $audioIdParam = $parameters[1];
        
        if ($pdoParam->getType() && $pdoParam->getType()->getName() !== 'PDO') {
            throw new Exception('First parameter should be PDO type');
        }
        
        if ($audioIdParam->getType() && $audioIdParam->getType()->getName() !== 'int') {
            throw new Exception('Second parameter should be int type');
        }
        
        echo "✓ Backward compatibility maintained\n";
    }
    
    public static function runAllTests(): void
    {
        echo "Running simple deleteAudioFiles() method tests...\n";
        
        try {
            self::testMethodExists();
            self::testMethodSignature();
            self::testUsesUnifiedMethods();
            self::testBackwardCompatibility();
            
            echo "All simple deleteAudioFiles() tests passed!\n";
        } catch (Exception $e) {
            echo "Test failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    SimpleDeleteAudioFilesTest::runAllTests();
}