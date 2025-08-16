#!/bin/bash

# Скрипт для удобной работы с Composer в Docker контейнере

set -e

CONTAINER_NAME="zubrilka_proto_php"

# Функция для проверки, что контейнер запущен
check_container() {
    if ! docker ps --format "table {{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
        echo "❌ Контейнер ${CONTAINER_NAME} не запущен."
        echo "Запустите: docker-compose up -d"
        exit 1
    fi
}

# Функция для выполнения команд composer в контейнере
composer_exec() {
    check_container
    echo "🔧 Выполняем: composer $*"
    docker exec $CONTAINER_NAME composer "$@"
}

# Функция для инициализации проекта (первый запуск)
init() {
    echo "🚀 Инициализация Composer зависимостей..."
    
    # Создаем vendor том, если его нет
    docker volume create zubrilka_proto_vendor
    
    # Запускаем контейнеры
    docker-compose up -d --build
    
    # Ждем, пока контейнер полностью запустится
    echo "⏳ Ожидание запуска контейнера..."
    sleep 5
    
    # Устанавливаем зависимости
    composer_exec install --optimize-autoloader
    
    echo "✅ Инициализация завершена!"
}

# Основная логика скрипта
case "$1" in
    "init")
        init
        ;;
    "install"|"update"|"require"|"remove"|"show"|"outdated"|"check-platform-reqs")
        composer_exec "$@"
        ;;
    "shell")
        check_container
        echo "🐚 Подключение к контейнеру..."
        docker exec -it $CONTAINER_NAME bash
        ;;
    *)
        echo "Использование: $0 {init|install|update|require|remove|show|outdated|check-platform-reqs|shell}"
        echo ""
        echo "Команды:"
        echo "  init                    - Первичная инициализация проекта"
        echo "  install                 - Установка зависимостей"
        echo "  update                  - Обновление зависимостей"
        echo "  require <package>       - Добавление новой зависимости"
        echo "  remove <package>        - Удаление зависимости"
        echo "  show                    - Список установленных пакетов"
        echo "  outdated                - Проверка устаревших пакетов"
        echo "  check-platform-reqs     - Проверка системных требований"
        echo "  shell                   - Подключение к bash контейнера"
        echo ""
        echo "Пример:"
        echo "  $0 init                 # Первый запуск"
        echo "  $0 require monolog/monolog"
        echo "  $0 shell                # Для отладки"
        exit 1
        ;;
esac