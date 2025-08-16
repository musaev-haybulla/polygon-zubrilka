#!/bin/bash

# Скрипт для удобной работы с Phinx миграциями в Docker контейнере

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

# Функция для выполнения команд phinx в контейнере
phinx_exec() {
    check_container
    echo "🔧 Выполняем: phinx $*"
    docker exec $CONTAINER_NAME ./vendor/bin/phinx "$@"
}

# Основная логика скрипта
case "$1" in
    "status")
        phinx_exec status
        ;;
    "migrate")
        phinx_exec migrate
        ;;
    "rollback")
        phinx_exec rollback "$@"
        ;;
    "create")
        if [ -z "$2" ]; then
            echo "Использование: $0 create <MigrationName>"
            exit 1
        fi
        phinx_exec create "$2"
        ;;
    "seed:create")
        if [ -z "$2" ]; then
            echo "Использование: $0 seed:create <SeederName>"
            exit 1
        fi
        phinx_exec seed:create "$2"
        ;;
    "seed:run")
        phinx_exec seed:run
        ;;
    *)
        echo "Использование: $0 {status|migrate|rollback|create|seed:create|seed:run}"
        echo ""
        echo "Команды:"
        echo "  status                  - Показать статус миграций"
        echo "  migrate                 - Запустить все новые миграции"
        echo "  rollback                - Откатить последнюю миграцию"
        echo "  create <name>           - Создать новую миграцию"
        echo "  seed:create <name>      - Создать новый сидер"
        echo "  seed:run                - Запустить все сидеры"
        echo ""
        echo "Примеры:"
        echo "  $0 status"
        echo "  $0 create CreatePoemsTable"
        echo "  $0 migrate"
        echo "  $0 rollback"
        echo "  $0 seed:create AuthorsSeeder"
        exit 1
        ;;
esac