<?php
/**
 * Автозагрузчик классов
 */
declare(strict_types=1);

/**
 * Простой автозагрузчик для классов проекта
 */
spl_autoload_register(function (string $className): void {
    $baseDir = __DIR__ . '/';

    // 1) PSR-4 для пространств имён App\\ -> classes/
    if (strpos($className, 'App\\') === 0) {
        $relative = substr($className, 4); // убрать 'App\'
        $psr4Path = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($psr4Path)) {
            require_once $psr4Path;
            return;
        }
    }

    // 2) Фолбэк: искать по базовому имени класса в корне classes/
    $basename = ($pos = strrpos($className, '\\')) !== false
        ? substr($className, $pos + 1)
        : $className;
    $flatPath = $baseDir . $basename . '.php';
    if (file_exists($flatPath)) {
        require_once $flatPath;
        return;
    }

    // 3) Последняя попытка: прямое преобразование пространства имён в путь
    $directPath = $baseDir . str_replace('\\', '/', $className) . '.php';
    if (file_exists($directPath)) {
        require_once $directPath;
        return;
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