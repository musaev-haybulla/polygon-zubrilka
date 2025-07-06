<?php
/**
 * Конфигурация размеров стихотворений
 */
declare(strict_types=1);

// Градация размеров стихов по количеству строк
const POEM_SIZE_CONFIG = [
    'short' => [
        'max_lines' => 8,
        'label' => 'Короткие',
        'description' => 'До 8 строк включительно'
    ],
    'medium' => [
        'min_lines' => 9,
        'max_lines' => 20,
        'label' => 'Средние',
        'description' => 'От 9 до 20 строк включительно'
    ],
    'large' => [
        'min_lines' => 21,
        'label' => 'Крупные',
        'description' => 'Более 20 строк'
    ]
];

/**
 * Определяет размер стихотворения по количеству строк
 * @param int $lineCount Количество строк
 * @return string Размер ('short', 'medium', 'large')
 */
function getPoemSize(int $lineCount): string {
    if ($lineCount <= POEM_SIZE_CONFIG['short']['max_lines']) {
        return 'short';
    } elseif ($lineCount <= POEM_SIZE_CONFIG['medium']['max_lines']) {
        return 'medium';
    } else {
        return 'large';
    }
}

/**
 * Получает метку размера для отображения
 * @param string $size Размер ('short', 'medium', 'large')
 * @return string Метка для отображения
 */
function getPoemSizeLabel(string $size): string {
    return POEM_SIZE_CONFIG[$size]['label'] ?? 'Неизвестно';
}