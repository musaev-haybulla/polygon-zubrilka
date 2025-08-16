<?php
declare(strict_types=1);

// Meilisearch configuration
// Для PHP (внутри контейнера) используем service name
const MEILISEARCH_HOST_INTERNAL = 'http://meili:7700';
// Для браузера (снаружи контейнера) используем localhost
const MEILISEARCH_HOST_EXTERNAL = 'http://localhost:7700';
const MEILISEARCH_KEY = '';  // В dev режиме ключ не нужен

// По умолчанию используем внутренний хост (для PHP сервисов)
const MEILISEARCH_HOST = MEILISEARCH_HOST_INTERNAL;
const MEILISEARCH_INDEX = 'content';

// Search settings
const SEARCH_RESULTS_PER_PAGE = 10;
const SEARCH_DEBOUNCE_MS = 300; // Задержка перед поиском при вводе текста
