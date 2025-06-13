<?php
declare(strict_types=1);

// Application settings
const APP_NAME = 'Поиск стихотворений';
const APP_VERSION = '1.0.0';
const APP_ENV = 'development'; // 'production' or 'development'

// Session settings
const SESSION_LIFETIME = 3600; // 1 hour
const SESSION_NAME = 'poem_search_session';

// Default values
const DEFAULT_USER_ID = 1;

// Paths
const UPLOADS_DIR = __DIR__ . '/uploads';
const LOGS_DIR = __DIR__ . '/logs';
