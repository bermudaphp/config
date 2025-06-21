# Bermuda Config

[![PHP Version Require](https://poser.pugx.org/bermudaphp/config/require/php)](https://packagist.org/packages/bermudaphp/config)
[![Latest Stable Version](https://poser.pugx.org/bermudaphp/config/v/stable)](https://packagist.org/packages/bermudaphp/config)
[![License](https://poser.pugx.org/bermudaphp/config/license)](https://packagist.org/packages/bermudaphp/config)

📖 **Доступно на других языках**: [English](README.md)

Библиотека для управления конфигурацией PHP 8.4+ приложений. Поддерживает множественные форматы файлов, переменные окружения, кэширование и интеграцию с контейнерами внедрения зависимостей.

## Возможности

- ✅ Поддержка множественных форматов файлов: PHP, JSON, XML, CSV, YAML
- 🌍 Загрузка переменных окружения из .env файлов
- 🔒 Неизменяемые объекты конфигурации с доступом как к массиву
- 🎯 Точечная нотация для доступа к вложенным значениям
- 🔄 Автоматическое преобразование типов
- ⚡ Файловое кэширование для повышения производительности
- 🔧 Вызываемые значения конфигурации с интеграцией контейнера
- 📦 Агрегация конфигурации из множественных источников
- ✔️ Валидация обязательных значений с типизированными методами ensure*
- 🎛️ Фильтрация конфигурации с методами only() и except()

## Установка

```bash
composer require bermudaphp/config
```

## Быстрый старт

### Базовое использование

```php
use Bermuda\Config\ConfigLoader;
use Bermuda\Config\FileProvider;

// Загрузка конфигурации из файлов
$loader = new ConfigLoader('/path/to/.env');
$config = $loader->load(
    new FileProvider('config/*.php'),
    new FileProvider('config/*.json')
);

// Доступ к значениям конфигурации (точечная нотация по умолчанию)
$databaseHost = $config->get('database.host', 'localhost');
$debugMode = $config->getBool('app.debug', false);
$allowedHosts = $config->getArray('app.allowed_hosts', []);

// Фильтрация конфигурации
$dbOnlyConfig = $config->only(['database', 'cache']); // Только БД и кэш
$publicConfig = $config->except(['app.key', 'passwords']); // Без секретов

// Работа с ключами содержащими точки
$apiKey = $config->get(key('external.api.key')); // Литеральный ключ
```

### Переменные окружения

Создайте файл `.env`:
```
APP_NAME=MyApp
APP_DEBUG=true
DB_HOST=localhost
DB_PORT=3306
DB_PASSWORD="пароль с пробелами"
```

Доступ к переменным окружения:
```php
use Bermuda\Config\Env\EnvLoader;

$envLoader = new EnvLoader('/path/to/project');
$envVars = $envLoader->load();

// Использование глобальной функции-помощника - читает из суперглобального $_ENV
$appName = env('APP_NAME', 'DefaultApp');
$debug = env('APP_DEBUG', false); // Автоматически конвертируется в boolean
```

**Важно**: Функция `env()` читает из суперглобального массива `$_ENV`, который может изменяться во время выполнения скрипта. Для неизменяемого доступа к окружению используйте `$config->environment`.

## Доступ к конфигурации

### Точечная нотация

Все методы доступа к конфигурации используют точечную нотацию

```php
// Доступ к вложенным значениям через точечную нотацию 
$host = $config->get('database.connections.mysql.host', 'localhost');
$firstProvider = $config->get('app.providers.0');

// Проверка существования ключа конфигурации
if ($config->has('database.redis')) {
    $redisConfig = $config->get('database.redis');
}

$port = $config->getInt('database.port');
$debug = $config->getBool('app.debug');
$hosts = $config->getArray('app.trusted_hosts');
```

### Ключи с точками (литеральные ключи)

Для работы с ключами, которые содержат точки как часть имени, используйте класс `Key`:

```php
// Конфигурация с ключами содержащими точки
$config = Config::create([
    'external.api.key' => 'secret-123',
    'stripe.publishable.key' => 'pk_test_456',
    'mail.from.address' => 'noreply@example.com',
    'app' => [
        'name' => 'MyApp',
        'debug' => true
    ]
]);

// Доступ к обычным вложенным ключам (точечная нотация)
$appName = $config->get('app.name');  // 'MyApp'
$debug = $config->getBool('app.debug'); // true

// Доступ к литеральным ключам с точками
use Bermuda\Config\Key;

$apiKey = $config->get(Key::literal('external.api.key')); // 'secret-123' 
$stripeKey = $config->get(Key::literal('stripe.publishable.key')); // 'pk_test_456'

// Использование функции-помощника key()
$fromAddress = $config->get(key('mail.from.address')); // 'noreply@example.com'

// Проверка существования литеральных ключей
if ($config->has(key('external.api.key'))) {
    echo "External API key найден";
}
```

### Фильтрация конфигурации

Библиотека предоставляет мощные методы для создания подмножеств конфигурации:

```php
// Создание конфигурации только с указанными ключами
$dbConfig = $config->only(['database', 'cache']);
$mysqlConfig = $config->only(['database.connections.mysql', 'database.default']);

// Несколько ключей как аргументы
$appConfig = $config->only('app.name', 'app.debug', 'app.timezone');

// Работа с литеральными ключами
$apiConfig = $config->only([
    key('external.api.key'),           // Литеральный ключ с точками
    key('stripe.publishable.key'),     // Еще один литеральный ключ
    'app.name'                         // Обычная точечная нотация
]);

// Создание конфигурации без указанных ключей  
$publicConfig = $config->except([
    'app.key', 
    'database.password',
    key('stripe.secret.key')           // Исключить литеральный ключ
]);

// Цепочка фильтрации
$secureDbConfig = $config
    ->only(['database'])                              // Только база данных
    ->except(['database.connections.mysql.password']) // Исключить пароль
    ->only(['database.connections.mysql']);           // Только MySQL

// Практические примеры
// Конфигурация для микросервиса авторизации
$authServiceConfig = $config->only([
    'app.name',
    'app.key', 
    'database.connections.mysql',
    'cache'
]);

// Публичная конфигурация для фронтенда (без секретов)
$publicApiConfig = $config->except([
    'app.key',
    'database.connections.mysql.password',
    'mail.password',
    key('stripe.secret.key'),
    key('external.api.key')
])->only([
    'app.name',
    'app.locale',
    'database.connections.mysql.host',
    'database.connections.mysql.database',
    key('stripe.publishable.key')
]);

// Конфигурация для внешней библиотеки
$doctrineConfig = $config->only([
    'database.connections.mysql.driver',
    'database.connections.mysql.host',
    'database.connections.mysql.port',
    'database.connections.mysql.database',
    'database.connections.mysql.username',
    'database.connections.mysql.password'
]);
```

**Особенности методов фильтрации:**
- Возвращают новый объект `Config` (не изменяют исходный)
- Поддерживают точечную нотацию для вложенных ключей
- Поддерживают `Key::literal()` и `key()` для ключей с точками
- Сохраняют `environment` и `container` в новом объекте
- Можно объединять в цепочки для сложной фильтрации
- Автоматически очищают пустые массивы после удаления вложенных ключей

### Типобезопасные геттеры

```php
// Получение значений с автоматическим преобразованием типов
$port = $config->getInt('database.port', 3306);
$debug = $config->getBool('app.debug', false);
$hosts = $config->getArray('app.trusted_hosts', []);
$name = $config->getString('app.name', 'DefaultApp');

// Доступ к вложенным значениям
$maxConnections = $config->getInt('database.pool.max_connections', 100);
$sslEnabled = $config->getBool('database.ssl.enabled', false);

// Работа с литеральными ключами, содержащими точки
$apiKey = $config->getString(key('external.api.key'), 'default-key');
$stripeKey = $config->getString(Key::literal('stripe.secret.key'));

// Переменные окружения с преобразованием типов
$maxConnections = $config->environment->getInt('DB_MAX_CONNECTIONS', 100);
$sslEnabled = $config->environment->getBool('DB_SSL_ENABLED', false);
```

### Валидация обязательных значений

Используйте `ensure*()` методы для обеспечения наличия критически важных значений конфигурации:

```php
try {
    // Базовая валидация - выбрасывает OutOfBoundsException если значения отсутствуют или равны null
    $databaseUrl = $config->ensureValue('database.url');
    $apiSecret = $config->ensureValue('api.secret');
    $jwtKey = $config->ensureValue('auth.jwt.key');
    
    // Типобезопасная валидация с автоматическим преобразованием (точечная нотация по умолчанию)
    $dbPort = $config->ensureInt('database.port'); // Должно приводиться к integer
    $debugMode = $config->ensureBool('app.debug'); // Должно приводиться к boolean
    $trustedHosts = $config->ensureArray('app.trusted_hosts'); // Должно приводиться к array
    $appName = $config->ensureString('app.name'); // Должно приводиться к string
    $requestTimeout = $config->ensureFloat('http.timeout'); // Должно приводиться к float
    
    // Работает с вложенными значениями автоматически
    $smtpPassword = $config->ensureString('mail.smtp.password');
    $maxConnections = $config->ensureInt('database.max_connections');
    $sslEnabled = $config->ensureBool('database.ssl.enabled');
    
    // Валидация литеральных ключей с точками
    $apiKey = $config->ensureString(key('external.api.key'));
    $stripeSecret = $config->ensureString(Key::literal('stripe.secret.key'));
    
    // Поддерживает флаги поведения вызываемых значений
    $serviceInstance = $config->ensureValue('services.critical', Config::CALL_VALUES);
    $port = $config->ensureInt('dynamic.port', Config::NO_CACHE);
    
} catch (\OutOfBoundsException $e) {
    // Обработка отсутствующей обязательной конфигурации
    error_log("Отсутствует обязательная конфигурация: " . $e->getMessage());
    exit(1);
} catch (\InvalidArgumentException $e) {
    // Обработка ошибок преобразования типов
    error_log("Неверный тип конфигурации: " . $e->getMessage());
    exit(1);
}
```

### Валидация переменных окружения

```php
try {
    // Базовая валидация переменных окружения
    $appKey = $config->environment->ensureString('APP_KEY');
    $dbHost = $config->environment->ensureString('DB_HOST');
    $dbPort = $config->environment->ensureInt('DB_PORT');
    $debugMode = $config->environment->ensureBool('APP_DEBUG');
    
    // Валидация массивов из переменных окружения
    $allowedOrigins = $config->environment->ensureArray('CORS_ALLOWED_ORIGINS');
    
} catch (\OutOfBoundsException $e) {
    error_log("Отсутствует обязательная переменная окружения: " . $e->getMessage());
    exit(1);
} catch (\InvalidArgumentException $e) {
    error_log("Неверный тип переменной окружения: " . $e->getMessage());
    exit(1);
}
```

### Практические примеры валидации

```php
// Валидация конфигурации для ранней проверки при запуске
function validateRequiredConfig(Config $config): void 
{
    // Базовая валидация
    $config->ensureValue('app.key');
    $config->ensureString('app.name');
    $config->ensureValue('database.url');
    
    // Типизированная валидация
    $config->ensureInt('database.port'); // Должно быть валидным integer
    $config->ensureBool('app.debug'); // Должно быть валидным boolean
    $config->ensureArray('app.providers'); // Должно быть валидным array
    
    // Вложенная валидация с точечной нотацией
    $config->ensureString('mail.from.address', Config::CALL_VALUES, true);
    $config->ensureInt('cache.ttl', Config::CALL_VALUES, true);
    $config->ensureBool('features.api_enabled', Config::CALL_VALUES, true);
}

// Валидация продакшен конфигурации
function validateProductionConfig(Config $config): void
{
    if ($config->ensureString('app.env') === 'production') {
        // В продакшене должны быть корректные типы
        $config->ensureString('app.key'); // Обязательная строка
        $config->ensureBool('app.debug'); // Должно быть false в продакшене
        $config->ensureInt('database.pool_size'); // Должно быть валидным числом
        $config->ensureArray('cors.allowed_origins'); // Должно быть массивом
        
        // Убедиться что отладка отключена
        if ($config->ensureBool('app.debug')) {
            throw new \RuntimeException('Режим отладки должен быть отключен в продакшене');
        }
    }
}
```

### Доступ к окружению: `env()` против `$config->environment`

Библиотека предоставляет два способа доступа к переменным окружения с различными характеристиками:

#### Глобальная функция `env()`
- Читает напрямую из суперглобального массива `$_ENV`
- **Изменяемая**: Может изменяться во время выполнения скрипта при модификации `$_ENV`
- Всегда возвращает текущее значение из `$_ENV`
- Обеспечивает автоматическое преобразование типов

```php
$_ENV['DYNAMIC_VALUE'] = 'начальное';
echo env('DYNAMIC_VALUE'); // выводит: 'начальное'

$_ENV['DYNAMIC_VALUE'] = 'изменённое';
echo env('DYNAMIC_VALUE'); // выводит: 'изменённое'
```

#### Объект `$config->environment`
- **Неизменяемый**: Содержит снимок переменных окружения на момент загрузки
- Потокобезопасный и предсказуемый
- Не может быть изменён после создания
- Предоставляет типобезопасные методы (`getInt()`, `getBool()`, `getArray()`)
- Поддерживает валидацию через методы `ensure*()`

```php
$config = $loader->load(/* провайдеры */);
echo $config->environment->get('DYNAMIC_VALUE'); // выводит: 'начальное'

$_ENV['DYNAMIC_VALUE'] = 'изменённое';
echo $config->environment->get('DYNAMIC_VALUE'); // всё ещё выводит: 'начальное'
```

**Рекомендация**: Используйте `$config->environment` для согласованного, предсказуемого поведения в приложениях. Используйте `env()` только когда нужен доступ к переменным окружения, изменённым во время выполнения.

### Вызываемые значения конфигурации

Значения конфигурации могут быть замыканиями, которые выполняются при обращении:

```php
return [
    'database_url' => function(Config $config) {
        return sprintf(
            'mysql://%s:%s@%s:%d/%s',
            $config->environment->get('DB_USER'),
            $config->environment->get('DB_PASSWORD'),
            $config->environment->get('DB_HOST'),
            $config->environment->getInt('DB_PORT'),
            $config->environment->get('DB_NAME')
        );
    },
    
    'cache_ttl' => function(Config $config) {
        return $config->getBool('app.debug') ? 0 : 3600;
    }
];

// Доступ к вызываемым значениям (автоматически выполняются)
$dbUrl = $config->get('database_url'); // Возвращает форматированную строку подключения
$ttl = $config->get('cache_ttl');      // Возвращает вычисленный TTL
```

#### Управление поведением вызываемых значений

Контролируйте когда и как выполняются замыкания с помощью флагов:

```php
use Bermuda\Config\Config;

// Основные флаги
Config::CALL_VALUES    // Выполнять замыкания в значениях конфига (по умолчанию)
Config::CALL_DEFAULTS  // Выполнять замыкания только в значениях по умолчанию
Config::CALL_ALL       // Выполнять все замыкания
Config::CALL_NONE      // Возвращать замыкания без выполнения
Config::NO_CACHE       // Отключить кэширование результатов

// Примеры использования (точечная нотация работает автоматически)
$service = $config->get('service', null, Config::CALL_VALUES);
$closure = $config->get('lazy_service', null, Config::CALL_NONE);
$freshData = $config->get('timestamp', null, Config::NO_CACHE);

// Все методы доступа к конфигурации поддерживают флаги
$port = $config->getInt('db.port', 3306, Config::CALL_VALUES);
$debug = $config->getBool('app.debug', false, Config::NO_CACHE);
$hosts = $config->getArray('trusted_hosts', [], Config::CALL_ALL);

// ensure* методы также поддерживают флаги
$service = $config->ensureValue('critical.service', Config::CALL_VALUES);
$rawClosure = $config->ensureValue('lazy.service', Config::CALL_NONE);
$port = $config->ensureInt('dynamic.port', Config::NO_CACHE);
$debug = $config->ensureBool('runtime.debug', Config::CALL_ALL);

// Работа с литеральными ключами и флагами
$externalService = $config->get(key('external.api.endpoint'), null, Config::NO_CACHE);
$stripeConfig = $config->ensureArray(Key::literal('stripe.webhooks.endpoints'), Config::CALL_VALUES);
```

## Поддержка форматов файлов

### PHP файлы конфигурации

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'Мое приложение'),
    'debug' => env('APP_DEBUG', false),
    'services' => [
        'mailer' => [
            'driver' => env('MAIL_DRIVER', 'smtp'),
            'host' => env('MAIL_HOST'),
        ]
    ]
];
```

### JSON файлы конфигурации

```json
{
    "database": {
        "default": "mysql",
        "connections": {
            "mysql": {
                "driver": "mysql",
                "host": "localhost",
                "port": 3306
            }
        }
    }
}
```

### XML файлы конфигурации

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <database>
        <default>mysql</default>
        <connections>
            <mysql driver="mysql" host="localhost" port="3306"/>
        </connections>
    </database>
</config>
```

### CSV файлы конфигурации

```csv
service,class,singleton
logger,Psr\Log\NullLogger,true
cache,Symfony\Component\Cache\Adapter\FilesystemAdapter,true
```

### YAML файлы конфигурации

```yaml
database:
  default: mysql
  connections:
    mysql:
      driver: mysql
      host: localhost
      port: 3306
```

## Загрузка переменных окружения

### Возможности .env файлов

```bash
# Базовые переменные
APP_NAME=MyApp
APP_ENV=production

# Значения в кавычках с пробелами
APP_KEY="base64:SGVsbG8gV29ybGQ="
DB_PASSWORD="пароль с пробелами"

# Многострочные значения в двойных кавычках
PRIVATE_KEY="-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC..."

# Escape-последовательности в двойных кавычках
LOG_FORMAT="[%datetime%] %channel%.%level_name%: %message%\n"
```

### Расширенная загрузка окружения

```php
use Bermuda\Config\Env\EnvLoader;

// Загрузка из множественных путей с обязательными переменными
$loader = new EnvLoader(
    paths: ['/app', '/app/config', '/etc/myapp'],
    envFiles: ['.env.local', '.env', '.env.example'],
    requiredVars: ['APP_KEY', 'DB_PASSWORD'],
    override: false
);

$envVars = $loader->load();
```

## Провайдеры конфигурации

### File Provider

```php
use Bermuda\Config\FileProvider;

// Загрузка из glob паттернов
$provider = new FileProvider('config/*.php');
$multiProvider = new FileProvider('config/{app,database,services}.{php,json}');

// Загрузка с пользовательскими читателями файлов
$csvProvider = new FileProvider('config/*.csv', [
    CsvFileReader::createWithAutoDetection()
]);
```

### Callable провайдеры

```php
// Простой провайдер массива
$arrayProvider = function() {
    return [
        'app' => [
            'name' => 'Мое приложение',
            'version' => '1.0.0'
        ]
    ];
};

// Динамический провайдер
$runtimeProvider = function() {
    return [
        'runtime' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'start_time' => time(),
        ]
    ];
};
```

### ConfigProvider классы

Структурированные провайдеры конфигурации для контейнеров внедрения зависимостей:

```php
use Bermuda\Config\ConfigProvider;

class AppConfigProvider extends ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            'app' => [
                'name' => env('APP_NAME', 'Мое приложение'),
                'debug' => env('APP_DEBUG', false),
            ],
            'database' => [
                'host' => env('DB_HOST', 'localhost'),
                'port' => env('DB_PORT', 3306),
            ]
        ];
    }
    
    protected function getFactories(): array
    {
        return [
            PDO::class => function($container) {
                $config = $container->get('config');
                $host = $config->get('database.host');
                $port = $config->getInt('database.port');
                
                return new PDO("mysql:host=$host;port=$port");
            }
        ];
    }
    
    protected function getAliases(): array
    {
        return [
            'db' => PDO::class,
        ];
    }
}
```

### Загрузка конфигурации

```php
$loader = new ConfigLoader('/path/to/project');

$config = $loader->load(
    // Файловые провайдеры
    new FileProvider('config/app.php'),
    new FileProvider('config/{database,cache}.json'),
    
    // Классовые провайдеры
    new AppConfigProvider(),
    
    // Callable провайдеры
    function() {
        return ['runtime' => ['timestamp' => time()]];
    }
);
```

## Кэширование

### Кэширование конфигурации

```php
// Загрузка с включенным кэшированием
$loader = new ConfigLoader(
    envPaths: '/app',
    cacheFile: '/tmp/config.cache.php'
);

$config = $loader->load(
    new FileProvider('config/*.php'),
    new AppConfigProvider()
);

// Экспорт конфигурации в файл кэша
$loader->export('/tmp/config.cache.php', $config);
```

### Использование кэшированной конфигурации

```php
// Загрузка из кэша (провайдеры игнорируются при наличии кэша)
$loader = new ConfigLoader(cacheFile: '/tmp/config.cache.php');
$config = $loader->load();
```

**Важно**: Экспорт кэша не может обрабатывать значения-объекты. Оборачивайте объекты в замыкания:

```php
// ❌ Не делайте так - объекты нельзя кэшировать
return [
    'logger' => new Logger('app'),
    'connection' => new PDO($dsn)
];

// ✅ Делайте так - оборачивайте объекты в замыкания
return [
    'logger' => fn(Config $config) => new Logger('app'),
    'connection' => fn(Config $config) => new PDO(
        $config->get('database.dsn')
    )
];
```

## Интеграция с контейнером

### С PSR-11 контейнером

```php
// Загрузка конфигурации с поддержкой контейнера
$loader = new ConfigLoader('/app');
$config = $loader->loadWithContainer(
    $container,
    new FileProvider('config/*.php'),
    new AppConfigProvider()
);

// Сервисы теперь могут получать доступ к конфигурации через контейнер
$userService = $container->get(UserService::class);
```

### Доступ к контейнеру в конфигурации

```php
return [
    'database' => function(Config $config) {
        $connection = $config->container->get(PDO::class);
        return new DatabaseManager($connection);
    },
    
    'event_dispatcher' => function(Config $config) {
        $dispatcher = $config->container->get(EventDispatcherInterface::class);
        
        foreach ($config->getArray('events.listeners') as $listener) {
            $dispatcher->addListener($listener);
        }
        
        return $dispatcher;
    }
];
```

## Обработка ошибок

### Ошибки валидации конфигурации

```php
use Bermuda\Config\Env\EnvLoaderException;

try {
    $loader = new EnvLoader('/path/to/config', requiredVars: ['APP_KEY']);
    $envVars = $loader->load();
} catch (EnvLoaderException $e) {
    echo "Ошибка загрузки окружения: " . $e->getMessage();
}

// Валидация обязательной конфигурации
try {
    $apiKey = $config->ensureValue('api.key');
    $dbPassword = $config->ensureValue('database.password');
    $jwtSecret = $config->ensureValue('auth.jwt.secret');
} catch (\OutOfBoundsException $e) {
    echo "Отсутствует обязательная конфигурация: " . $e->getMessage();
    // Обработка отсутствующей конфигурации (логирование, выход, значения по умолчанию и т.д.)
    exit(1);
}

// Валидация типов конфигурации
try {
    $port = $config->ensureInt('database.port');
    $debug = $config->ensureBool('app.debug');
} catch (\InvalidArgumentException $e) {
    echo "Неверный тип конфигурации: " . $e->getMessage();
    exit(1);
}
```

## Глобальные функции-помощники

```php
// Получение переменной окружения с автоматическим преобразованием типов
// Примечание: Читает из суперглобального $_ENV (изменяемый во время выполнения)
$debug = env('APP_DEBUG', false);      // boolean
$port = env('DB_PORT', 3306);         // integer  
$name = env('APP_NAME', 'MyApp');     // string

// Типизированные функции для переменных окружения
$appName = env_string('APP_NAME', 'DefaultApp');
$dbPort = env_int('DB_PORT', 3306);
$debugMode = env_bool('APP_DEBUG', false);
$maxMemory = env_float('MEMORY_LIMIT', 128.0);
$trustedHosts = env_array('TRUSTED_HOSTS', []);

// Получение конфигурации из контейнера
$config = conf($container);

// Получение сервиса с fallback по умолчанию
$logger = cget($container, 'logger', new NullLogger());

// Создание литерального ключа (для ключей с точками)
$apiKey = $config->get(key('external.api.key')); // Эквивалентно Key::literal('external.api.key')
$stripeKey = $config->getString(key('stripe.publishable.key'));

// Для неизменяемого доступа к окружению используйте:
$debug = $config->environment->getBool('APP_DEBUG', false);
$port = $config->environment->getInt('DB_PORT', 3306);
```

## Настройка читателей файлов

```php
use Bermuda\Config\Reader\CsvFileReader;
use Bermuda\Config\Reader\XmlFileReader;

// Пользовательский CSV читатель
$csvReader = new CsvFileReader(
    delimiter: ';',
    hasHeaders: true,
    autoDetectTypes: true
);

// Пользовательский XML читатель
$xmlReader = XmlFileReader::createForConfig();

$provider = new FileProvider('config/*', [$csvReader, $xmlReader]);
```

## Системные требования

- PHP 8.4 или выше
- ext-json (для поддержки JSON)
- ext-simplexml (для поддержки XML)
- symfony/yaml (опционально, для поддержки YAML)

## Лицензия

Эта библиотека лицензирована под лицензией MIT. Смотрите файл [LICENSE](LICENSE) для подробностей.
