<?php

namespace App\Audio;

class AudioPauseDetector
{
    private string $binaryPath;

    public function __construct(?string $binaryPath = null)
    {
        // classes/ -> project root
        $projectRoot = \dirname(__DIR__);
        if ($binaryPath === null) {
            // Основной путь — composer bin
            $candidate = $projectRoot . '/vendor/bin/audio-pause-detector';
            if (!\is_file($candidate)) {
                // Фолбэк — локальный пакет
                $candidate = $projectRoot . '/packages/audio-pause-detector/bin/audio-pause-detector';
            }
            $binaryPath = $candidate;
        }

        if (!\is_file($binaryPath)) {
            throw new \RuntimeException('Audio pause detector binary not found at: ' . $binaryPath);
        }

        $this->binaryPath = $binaryPath;
    }

    /**
     * Запускает детектор пауз и возвращает полный JSON-результат как массив.
     * @param string $audioFilePath Путь к аудиофайлу
     * @param int|null $numLines Кол-во строк (для выбора оптимальных пауз); если null — вернуть все найденные
     * @return array Ассоциативный массив результата
     */
    public function detectPauses(string $audioFilePath, ?int $numLines = null): array
    {
        if (!\is_file($audioFilePath)) {
            throw new \InvalidArgumentException('Audio file not found: ' . $audioFilePath);
        }

        $command = \escapeshellarg($this->binaryPath) . ' ' . \escapeshellarg($audioFilePath) . ' --json';
        if ($numLines !== null) {
            $command .= ' --num_lines ' . (int) $numLines;
        }

        $output = [];
        $returnCode = 0;
        \exec($command . ' 2>&1', $output, $returnCode);

        $jsonOutput = \implode("\n", $output);
        $result = \json_decode($jsonOutput, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON parse error: ' . \json_last_error_msg() . "\nOutput: " . $jsonOutput);
        }

        if ($returnCode !== 0 || empty($result['success'])) {
            $errorMsg = $result['error'] ?? 'Unknown error';
            throw new \RuntimeException('Script execution failed: ' . $errorMsg);
        }

        return $result;
    }

    /**
     * Возвращает только точки разбиения (секунды), вычисленные детектором.
     */
    public function getSplitPoints(string $audioFilePath, ?int $numLines = null): array
    {
        $result = $this->detectPauses($audioFilePath, $numLines);
        return $result['splits'] ?? [];
    }
}
