<?php
/**
 * Автозагрузчик классов
 */
declare(strict_types=1);

/**
 * Простой автозагрузчик для классов проекта
 */
spl_autoload_register(function (string $className): void {
    $classFile = __DIR__ . '/' . $className . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

/**
 * Функция для подключения всех классов сразу
 * Используется для обеспечения совместимости с существующим кодом
 */
function loadAllClasses(): void {
    $classFiles = [
        'DatabaseHelper',
        'ResponseHelper', 
        'FragmentQuery',
        'SearchService',
        'PoemProcessor'
    ];
    
    foreach ($classFiles as $className) {
        $classFile = __DIR__ . '/' . $className . '.php';
        if (file_exists($classFile)) {
            require_once $classFile;
        }
    }
}