<?php
/**
 * Вспомогательный класс для работы с JSON ответами
 */
declare(strict_types=1);

class ResponseHelper 
{
    /**
     * Отправка JSON ответа
     */
    public static function json(array $data, int $httpCode = 200): void 
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Отправка успешного ответа
     */
    public static function success(array $data = []): void 
    {
        self::json([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Отправка ответа с ошибкой
     */
    public static function error(string $message, int $httpCode = 500): void 
    {
        self::json([
            'success' => false,
            'error' => $message
        ], $httpCode);
    }
    
    /**
     * Отправка ошибки валидации
     */
    public static function validationError(string $message): void 
    {
        self::error($message, 400);
    }
    
    /**
     * Отправка ошибки базы данных
     */
    public static function databaseError(string $message = 'Ошибка при работе с базой данных'): void 
    {
        self::error($message, 500);
    }
    
    /**
     * Отправка ошибки "не найдено"
     */
    public static function notFound(string $message = 'Запрашиваемый ресурс не найден'): void 
    {
        self::error($message, 404);
    }
    
    /**
     * Настройка заголовков для CORS (если нужно)
     */
    public static function setCorsHeaders(): void 
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    
    /**
     * Настройка заголовков для API ответа
     */
    public static function setApiHeaders(): void 
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (APP_ENV === 'development') {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }
    }
}