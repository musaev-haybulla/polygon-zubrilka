# 🔍 Протокол анти-дублирования при рефакторинге

Простой чек-лист для предотвращения дублирования функционала и потери логики при миграции на Laravel.

## 🎯 Общий принцип

> **3 минуты проверки экономят 3 часа отладки**

Каждый раз, когда добавляете новый метод или UseCase - потратьте 3 минуты на проверку существующего кода.

## 📋 Чек-лист на каждый шаг

### ⏰ **ПЕРЕД написанием кода** (30 секунд)

#### 1. Поиск похожего функционала
```bash
# Поиск по ключевым словам вашего функционала
grep -r "publish" classes/
grep -r "timing" classes/ 
grep -r "audio.*duration" classes/
grep -r "fragment.*verses" classes/

# Поиск по SQL операциям
grep -r "UPDATE.*status" . --include="*.php"
grep -r "INSERT INTO tracks" . --include="*.php"
grep -r "SELECT.*timings" . --include="*.php"
```

#### 2. Проверка существующих методов
```bash
# Поиск методов с похожими именами
grep -r "function.*[Cc]omplete" classes/
grep -r "function.*[Vv]alidate" classes/
grep -r "function.*[Cc]heck" classes/
```

### 🛠️ **ВО ВРЕМЯ написания** 

#### 1. Правило именования методов
```php
// ✅ ХОРОШО - четкие, описательные имена
public function isTimingComplete(): bool           // Проверка состояния
public function canBePublished(): bool            // Проверка возможности
public function calculateReadingTime(): int       // Вычисление
public function activateTrack(): bool             // Действие
public function getFormattedDuration(): string    // Получение данных

// ❌ ПЛОХО - неясные имена (высокий риск дублирования)
public function check(): bool         // Что проверяем?
public function validate(): bool      // Что валидируем?
public function process(): mixed      // Что обрабатываем?
public function handle(): void        // Что обрабатываем?
```

#### 2. Поиск во время написания
Если пишете логику типа:
```php
// Если пишете проверку статуса
if ($this->status === 'published') {
    // СТОП! Поищите похожую проверку:
    grep -r "status.*published" classes/
}

// Если пишете подсчет строк
$versesCount = $this->verses()->count();
// СТОП! Поищите:
grep -r "verses.*count" classes/
```

### ✅ **ПОСЛЕ написания метода** (2 минуты)

#### 1. Финальная проверка дублирования
```bash
# Если написали метод isTimingComplete()
grep -r "timing.*complete" classes/
grep -r "complete.*timing" classes/

# Если написали метод calculateReadingTime()
grep -r "reading.*time" classes/
grep -r "calculate.*time" classes/
```

#### 2. Проверка логической целостности
```php
// Проверьте все методы класса на логику
class Track extends Model {
    public function isTimingComplete(): bool     // ✅
    public function canBeActivated(): bool       // ✅ 
    public function isComplete(): bool           // ❌ ДУБЛЬ! Что это?
}
```

## 🗂️ **Карта функциональности** - ведите по ходу

### Создайте простой файл FUNCTIONALITY_MAP.md
```markdown
## Проверки статуса (is/can методы)
- Poem::isPublished() - проверка статуса published
- Poem::canBePublished() - проверка возможности публикации  
- Track::isActive() - проверка статуса active
- Track::isTimingComplete() - проверка завершенности таймингов
- Fragment::hasActiveAudio() - проверка наличия активного аудио

## Вычисления (calculate/get методы)
- Poem::calculateReadingTime() - время чтения в минутах
- Track::getFormattedDuration() - форматированная длительность
- Fragment::getSize() - размер фрагмента (short/medium/large)

## Действия (activate/publish/create методы)
- Poem::publish() - публикация стихотворения
- Track::activate() - активация трека
- Track::setVerseTiming() - установка тайминга строки

## Парсинг и обработка
- Poem::parseTextToVerses() - парсинг текста в строки
- AudioFileHelper::generateFilename() - генерация имени файла
```

## ⚠️ **Красные флаги** - когда точно проверить

### 1. Методы с похожими именами
```php
// ❌ Подозрительно - похожие имена
public function isComplete(): bool
public function isTimingComplete(): bool
public function checkComplete(): bool

// ✅ Хорошо - четкие различия
public function isPublished(): bool
public function isTimingComplete(): bool  
public function canBeActivated(): bool
```

### 2. Похожие SQL запросы
```php
// ❌ Подозрительно
// В методе A:
SELECT COUNT(*) FROM verses WHERE fragment_id = ?

// В методе B:  
SELECT COUNT(verses.id) FROM verses WHERE fragment_id = ?

// ✅ Хорошо - переиспользуем
public function getVersesCount(): int {
    return $this->verses()->count(); // Один раз через Eloquent
}
```

### 3. Одинаковая логика валидации
```php
// ❌ Дублирование
public function canBePublished(): bool {
    return $this->fragments()->exists() && 
           $this->fragments()->whereHas('verses')->exists();
}

public function isReadyForPublishing(): bool {
    return $this->fragments()->exists() && 
           $this->fragments()->whereHas('verses')->exists();
}

// ✅ Решение
public function canBePublished(): bool {
    return $this->hasRequiredFragments();
}

private function hasRequiredFragments(): bool {
    return $this->fragments()->exists() && 
           $this->fragments()->whereHas('verses')->exists();
}
```

## 🔧 **Инструменты для проверки**

### 1. Простые bash команды
```bash
# Алиасы для быстрого поиска (добавьте в .bashrc)
alias findmethod="grep -r 'function.*' classes/"
alias findlogic="grep -r"
alias findsql="grep -r 'SELECT\|INSERT\|UPDATE\|DELETE' . --include='*.php'"
```

### 2. IDE помощники
```php
// Используйте PHPDoc для описания назначения
/**
 * Проверяет, завершена ли разметка тайминга трека.
 * Доменное правило: последняя строка не размечается.
 * 
 * @return bool true если тайминги завершены
 */
public function isTimingComplete(): bool
```

### 3. Простая проверка в коде
```php
// Добавляйте комментарии о связанных методах
class Track extends Model {
    /**
     * @see TimingDomainService::setVerseTiming() - установка таймингов
     * @see Track::activate() - активация после завершения
     */
    public function isTimingComplete(): bool
    {
        // логика
    }
}
```

## 📊 **Еженедельная проверка** (15 минут)

### Раз в неделю прогоняйте:
```bash
# 1. Поиск методов с похожими именами
grep -r "function.*complete" classes/ | sort
grep -r "function.*validate" classes/ | sort  
grep -r "function.*check" classes/ | sort

# 2. Поиск похожих SQL паттернов
grep -r "COUNT.*verses" . --include="*.php"
grep -r "WHERE.*status" . --include="*.php"

# 3. Поиск magic numbers и strings
grep -r "'published'" classes/
grep -r "'active'" classes/
grep -r "150" classes/  # Если 150 = слов в минуту
```

## 🎯 **Практические примеры из вашего проекта**

### Найденные потенциальные дубли:

#### 1. Проверки завершенности
```php
// В TimingService.php есть логика проверки завершенности
// В Track модели тоже может появиться isTimingComplete()
// ПРОВЕРИТЬ: не дублируется ли логика?
```

#### 2. Парсинг текста
```php
// В PoemProcessor.php есть parseVerses()
// При создании Poem::parseTextToVerses() 
// ПРОВЕРИТЬ: используем ли ту же логику?
```

#### 3. Работа с файлами
```php
// В AudioFileHelper много методов для путей
// При создании Track::getPlayUrl()
// ПРОВЕРИТЬ: не дублируем ли генерацию путей?
```

## 🚀 **Быстрый протокол** (для ленивых)

### 30-секундная проверка:
```bash
# 1. Поиск по главному слову функционала
grep -r "KEYWORD" classes/

# 2. Поиск методов с похожими именами  
grep -r "function.*KEYWORD" classes/

# 3. Если находим - читаем 30 секунд, решаем переиспользовать или создать новое
```

### Правило 🔴🟡🟢:
- 🔴 **Красный**: Точно дубль → переиспользуем
- 🟡 **Желтый**: Похоже, но разное → добавляем комментарий о различии  
- 🟢 **Зеленый**: Уникальная логика → создаем новое

## 💡 **Главное правило**

> **"Doubt it, grep it, decide it"**
> 
> Сомневаешься → поищи в коде → прими решение

**Лучше потратить 3 минуты на проверку, чем 3 часа на рефакторинг дублей!** 🎯