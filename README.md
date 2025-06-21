# Bermuda Config

[![PHP Version Require](https://poser.pugx.org/bermudaphp/config/require/php)](https://packagist.org/packages/bermudaphp/config)
[![Latest Stable Version](https://poser.pugx.org/bermudaphp/config/v/stable)](https://packagist.org/packages/bermudaphp/config)
[![License](https://poser.pugx.org/bermudaphp/config/license)](https://packagist.org/packages/bermudaphp/config)

📖 **Available in other languages**: [English](README.md) | [Русский](README.RU.md)

A powerful configuration management library for PHP 8.4+ applications. Supports multiple file formats, environment variables, caching, and dependency injection container integration.

## Features

- ✅ Multiple file format support: PHP, JSON, XML, CSV, YAML
- 🌍 Environment variable loading from .env files
- 🔒 Immutable configuration objects with array-like access
- 🎯 Dot notation for accessing nested values
- 🔄 Automatic type conversion
- ⚡ File caching for improved performance
- 🔧 Callable configuration values with container integration
- 📦 Configuration aggregation from multiple sources
- ✔️ Required value validation with typed ensure* methods
- 🎛️ Configuration filtering with only() and except() methods

## Installation

```bash
composer require bermudaphp/config
```

## Quick Start

### Basic Usage

```php
use Bermuda\Config\ConfigLoader;
use Bermuda\Config\FileProvider;

// Load configuration from files
$loader = new ConfigLoader('/path/to/.env');
$config = $loader->load(
    new FileProvider('config/*.php'),
    new FileProvider('config/*.json')
);

// Access configuration values (dot notation by default)
$databaseHost = $config->get('database.host', 'localhost');
$debugMode = $config->getBool('app.debug', false);
$allowedHosts = $config->getArray('app.allowed_hosts', []);

// Filter configuration
$dbOnlyConfig = $config->only(['database', 'cache']); // Only DB and cache
$publicConfig = $config->except(['app.key', 'passwords']); // Without secrets

// Working with keys containing dots
$apiKey = $config->get(key('external.api.key')); // Literal key
```

### Environment Variables

Create a `.env` file:
```
APP_NAME=MyApp
APP_DEBUG=true
DB_HOST=localhost
DB_PORT=3306
DB_PASSWORD="password with spaces"
```

Access environment variables:
```php
use Bermuda\Config\Env\EnvLoader;

$envLoader = new EnvLoader('/path/to/project');
$envVars = $envLoader->load();

// Using global helper function - reads from $_ENV superglobal
$appName = env('APP_NAME', 'DefaultApp');
$debug = env('APP_DEBUG', false); // Automatically converted to boolean
```

**Important**: The `env()` function reads from the `$_ENV` superglobal, which can change during script execution. For immutable environment access, use `$config->environment`.

## Configuration Access

### Dot Notation

All configuration access methods use dot notation by default:

```php
// Access nested values using dot notation
$host = $config->get('database.connections.mysql.host', 'localhost');
$firstProvider = $config->get('app.providers.0');

// Check if configuration key exists
if ($config->has('database.redis')) {
    $redisConfig = $config->get('database.redis');
}

$port = $config->getInt('database.port');
$debug = $config->getBool('app.debug');
$hosts = $config->getArray('app.trusted_hosts');
```

### Keys with Dots (Literal Keys)

For working with keys that contain dots as part of the key name, use the `Key` class:

```php
// Configuration with literal keys containing dots
$config = Config::create([
    'external.api.key' => 'secret-123',
    'stripe.publishable.key' => 'pk_test_456',
    'mail.from.address' => 'noreply@example.com',
    'app' => [
        'name' => 'MyApp',
        'debug' => true
    ]
]);

// Access regular nested keys (dot notation)
$appName = $config->get('app.name');  // 'MyApp'
$debug = $config->getBool('app.debug'); // true

// Access literal keys containing dots
use Bermuda\Config\Key;

$apiKey = $config->get(Key::literal('external.api.key')); // 'secret-123' 
$stripeKey = $config->get(Key::literal('stripe.publishable.key')); // 'pk_test_456'

// Using the key() helper function
$fromAddress = $config->get(key('mail.from.address')); // 'noreply@example.com'

// Check existence of literal keys
if ($config->has(key('external.api.key'))) {
    echo "External API key found";
}
```

### Configuration Filtering

The library provides powerful methods for creating configuration subsets:

```php
// Create configuration with only specified keys
$dbConfig = $config->only(['database', 'cache']);
$mysqlConfig = $config->only(['database.connections.mysql', 'database.default']);

// Multiple keys as arguments
$appConfig = $config->only('app.name', 'app.debug', 'app.timezone');

// Working with literal keys
$apiConfig = $config->only([
    key('external.api.key'),           // Literal key with dots
    key('stripe.publishable.key'),     // Another literal key
    'app.name'                         // Regular dot notation
]);

// Create configuration without specified keys  
$publicConfig = $config->except([
    'app.key', 
    'database.password',
    key('stripe.secret.key')           // Exclude literal key
]);

// Filter chaining
$secureDbConfig = $config
    ->only(['database'])                              // Only database
    ->except(['database.connections.mysql.password']) // Exclude password
    ->only(['database.connections.mysql']);           // Only MySQL

// Practical examples
// Configuration for authentication microservice
$authServiceConfig = $config->only([
    'app.name',
    'app.key', 
    'database.connections.mysql',
    'cache'
]);

// Public configuration for frontend (without secrets)
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

// Configuration for external library
$doctrineConfig = $config->only([
    'database.connections.mysql.driver',
    'database.connections.mysql.host',
    'database.connections.mysql.port',
    'database.connections.mysql.database',
    'database.connections.mysql.username',
    'database.connections.mysql.password'
]);
```

**Filter method features:**
- Return new `Config` object (don't modify original)
- Support dot notation for nested keys
- Support `Key::literal()` and `key()` for keys with dots
- Preserve `environment` and `container` in new object
- Can be chained for complex filtering
- Automatically clean up empty arrays after removing nested keys

### Type-Safe Getters

```php
// Get values with automatic type conversion
$port = $config->getInt('database.port', 3306);
$debug = $config->getBool('app.debug', false);
$hosts = $config->getArray('app.trusted_hosts', []);
$name = $config->getString('app.name', 'DefaultApp');

// Access nested values
$maxConnections = $config->getInt('database.pool.max_connections', 100);
$sslEnabled = $config->getBool('database.ssl.enabled', false);

// Working with literal keys containing dots
$apiKey = $config->getString(key('external.api.key'), 'default-key');
$stripeKey = $config->getString(Key::literal('stripe.secret.key'));

// Environment variables with type conversion
$maxConnections = $config->environment->getInt('DB_MAX_CONNECTIONS', 100);
$sslEnabled = $config->environment->getBool('DB_SSL_ENABLED', false);
```

### Required Value Validation

Use `ensure*()` methods to enforce the presence of critical configuration values:

```php
try {
    // Basic validation - throws OutOfBoundsException if values are missing or null
    $databaseUrl = $config->ensureValue('database.url');
    $apiSecret = $config->ensureValue('api.secret');
    $jwtKey = $config->ensureValue('auth.jwt.key');
    
    // Type-safe validation with automatic conversion (dot notation by default)
    $dbPort = $config->ensureInt('database.port'); // Must be convertible to integer
    $debugMode = $config->ensureBool('app.debug'); // Must be convertible to boolean
    $trustedHosts = $config->ensureArray('app.trusted_hosts'); // Must be convertible to array
    $appName = $config->ensureString('app.name'); // Must be convertible to string
    $requestTimeout = $config->ensureFloat('http.timeout'); // Must be convertible to float
    
    // Works with nested values automatically
    $smtpPassword = $config->ensureString('mail.smtp.password');
    $maxConnections = $config->ensureInt('database.max_connections');
    $sslEnabled = $config->ensureBool('database.ssl.enabled');
    
    // Validation of literal keys with dots
    $apiKey = $config->ensureString(key('external.api.key'));
    $stripeSecret = $config->ensureString(Key::literal('stripe.secret.key'));
    
    // Supports callable behavior flags
    $serviceInstance = $config->ensureValue('services.critical', Config::CALL_VALUES);
    $port = $config->ensureInt('dynamic.port', Config::NO_CACHE);
    
} catch (\OutOfBoundsException $e) {
    // Handle missing required configuration
    error_log("Missing required configuration: " . $e->getMessage());
    exit(1);
} catch (\InvalidArgumentException $e) {
    // Handle type conversion errors
    error_log("Invalid configuration type: " . $e->getMessage());
    exit(1);
}
```

### Environment Variable Validation

```php
try {
    // Basic environment variable validation
    $appKey = $config->environment->ensureString('APP_KEY');
    $dbHost = $config->environment->ensureString('DB_HOST');
    $dbPort = $config->environment->ensureInt('DB_PORT');
    $debugMode = $config->environment->ensureBool('APP_DEBUG');
    
    // Validate arrays from environment variables
    $allowedOrigins = $config->environment->ensureArray('CORS_ALLOWED_ORIGINS');
    
} catch (\OutOfBoundsException $e) {
    error_log("Missing required environment variable: " . $e->getMessage());
    exit(1);
} catch (\InvalidArgumentException $e) {
    error_log("Invalid environment variable type: " . $e->getMessage());
    exit(1);
}
```

### Environment Access: `env()` vs `$config->environment`

The library provides two ways to access environment variables with different characteristics:

#### Global `env()` Function
- Reads directly from the `$_ENV` superglobal
- **Mutable**: Can change during script execution when `$_ENV` is modified
- Always returns current value from `$_ENV`
- Provides automatic type conversion

```php
$_ENV['DYNAMIC_VALUE'] = 'initial';
echo env('DYNAMIC_VALUE'); // outputs: 'initial'

$_ENV['DYNAMIC_VALUE'] = 'changed';
echo env('DYNAMIC_VALUE'); // outputs: 'changed'
```

#### `$config->environment` Object
- **Immutable**: Contains snapshot of environment variables at load time
- Thread-safe and predictable
- Cannot be changed after creation
- Provides type-safe methods (`getInt()`, `getBool()`, `getArray()`)
- Supports validation through `ensure*()` methods

```php
$config = $loader->load(/* providers */);
echo $config->environment->get('DYNAMIC_VALUE'); // outputs: 'initial'

$_ENV['DYNAMIC_VALUE'] = 'changed';
echo $config->environment->get('DYNAMIC_VALUE'); // still outputs: 'initial'
```

**Recommendation**: Use `$config->environment` for consistent, predictable behavior in applications. Use `env()` only when you need access to environment variables changed during runtime.

### Callable Configuration Values

Configuration values can be closures that are executed when accessed:

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

// Access callable values (automatically executed)
$dbUrl = $config->get('database_url'); // Returns formatted connection string
$ttl = $config->get('cache_ttl');      // Returns computed TTL
```

#### Callable Behavior Control

Control when and how closures are executed using flags:

```php
use Bermuda\Config\Config;

// Core flags
Config::CALL_VALUES    // Execute closures in config values (default)
Config::CALL_DEFAULTS  // Execute closures only in default values
Config::CALL_ALL       // Execute all closures
Config::CALL_NONE      // Return closures without execution
Config::NO_CACHE       // Disable result caching

// Usage examples (dot notation works automatically)
$service = $config->get('service', null, Config::CALL_VALUES);
$closure = $config->get('lazy_service', null, Config::CALL_NONE);
$freshData = $config->get('timestamp', null, Config::NO_CACHE);

// All configuration access methods support flags
$port = $config->getInt('db.port', 3306, Config::CALL_VALUES);
$debug = $config->getBool('app.debug', false, Config::NO_CACHE);
$hosts = $config->getArray('trusted_hosts', [], Config::CALL_ALL);

// ensure* methods also support flags
$service = $config->ensureValue('critical.service', Config::CALL_VALUES);
$rawClosure = $config->ensureValue('lazy.service', Config::CALL_NONE);
$port = $config->ensureInt('dynamic.port', Config::NO_CACHE);
$debug = $config->ensureBool('runtime.debug', Config::CALL_ALL);

// Working with literal keys and flags
$externalService = $config->get(key('external.api.endpoint'), null, Config::NO_CACHE);
$stripeConfig = $config->ensureArray(Key::literal('stripe.webhooks.endpoints'), Config::CALL_VALUES);
```

## File Format Support

### PHP Configuration Files

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'My Application'),
    'debug' => env('APP_DEBUG', false),
    'services' => [
        'mailer' => [
            'driver' => env('MAIL_DRIVER', 'smtp'),
            'host' => env('MAIL_HOST'),
        ]
    ]
];
```

### JSON Configuration Files

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

### XML Configuration Files

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

### CSV Configuration Files

```csv
service,class,singleton
logger,Psr\Log\NullLogger,true
cache,Symfony\Component\Cache\Adapter\FilesystemAdapter,true
```

### YAML Configuration Files

```yaml
database:
  default: mysql
  connections:
    mysql:
      driver: mysql
      host: localhost
      port: 3306
```

## Environment Variable Loading

### .env File Features

```bash
# Basic variables
APP_NAME=MyApp
APP_ENV=production

# Quoted values with spaces
APP_KEY="base64:SGVsbG8gV29ybGQ="
DB_PASSWORD="password with spaces"

# Multiline values in double quotes
PRIVATE_KEY="-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC..."

# Escape sequences in double quotes
LOG_FORMAT="[%datetime%] %channel%.%level_name%: %message%\n"
```

### Advanced Environment Loading

```php
use Bermuda\Config\Env\EnvLoader;

// Load from multiple paths with required variables
$loader = new EnvLoader(
    paths: ['/app', '/app/config', '/etc/myapp'],
    envFiles: ['.env.local', '.env', '.env.example'],
    requiredVars: ['APP_KEY', 'DB_PASSWORD'],
    override: false
);

$envVars = $loader->load();
```

## Configuration Providers

### File Provider

```php
use Bermuda\Config\FileProvider;

// Load from glob patterns
$provider = new FileProvider('config/*.php');
$multiProvider = new FileProvider('config/{app,database,services}.{php,json}');

// Load with custom file readers
$csvProvider = new FileProvider('config/*.csv', [
    CsvFileReader::createWithAutoDetection()
]);
```

### Callable Providers

```php
// Simple array provider
$arrayProvider = function() {
    return [
        'app' => [
            'name' => 'My Application',
            'version' => '1.0.0'
        ]
    ];
};

// Dynamic provider
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

### ConfigProvider Classes

Structured configuration providers for dependency injection containers:

```php
use Bermuda\Config\ConfigProvider;

class AppConfigProvider extends ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            'app' => [
                'name' => env('APP_NAME', 'My Application'),
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

### Loading Configuration

```php
$loader = new ConfigLoader('/path/to/project');

$config = $loader->load(
    // File providers
    new FileProvider('config/app.php'),
    new FileProvider('config/{database,cache}.json'),
    
    // Class providers
    new AppConfigProvider(),
    
    // Callable providers
    function() {
        return ['runtime' => ['timestamp' => time()]];
    }
);
```

## Caching

### Configuration Caching

```php
// Load with caching enabled
$loader = new ConfigLoader(envPaths: '/app');

$config = $loader->load(
    new FileProvider('config/*.php'),
    new AppConfigProvider()
);

// Export configuration to cache file
$loader->export('/tmp/config.cache.php', $config);
```

### Using Cached Configuration

```php
// Load from cache (providers passed to `load` are ignored when cache exists, environment variables are also loaded from cache, paths passed to constructor are ignored)
$loader = new ConfigLoader(cacheFile: '/tmp/config.cache.php');
$config = $loader->load();
```

**Important**: Cache export cannot handle object values. Wrap objects in closures:

```php
// ❌ Don't do this - objects can't be cached
return [
    'logger' => new Logger('app'),
    'connection' => new PDO($dsn)
];

// ✅ Do this - wrap objects in closures
return [
    'logger' => fn(Config $config) => new Logger('app'),
    'connection' => fn(Config $config) => new PDO(
        $config->get('database.dsn')
    )
];
```

## Container Integration

### With PSR-11 Container

```php
// Load configuration with container support
$loader = new ConfigLoader('/app');
$config = $loader->loadWithContainer(
    $container,
    new FileProvider('config/*.php'),
    new AppConfigProvider()
);

// Services can now access configuration through container
$userService = $container->get(UserService::class);
```

### Container Access in Configuration

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

## Error Handling

### Configuration Validation Errors

```php
use Bermuda\Config\Env\EnvLoaderException;

try {
    $loader = new EnvLoader('/path/to/config', requiredVars: ['APP_KEY']);
    $envVars = $loader->load();
} catch (EnvLoaderException $e) {
    echo "Environment loading error: " . $e->getMessage();
}

// Required configuration validation
try {
    $apiKey = $config->ensureValue('api.key');
    $dbPassword = $config->ensureValue('database.password');
    $jwtSecret = $config->ensureValue('auth.jwt.secret');
} catch (\OutOfBoundsException $e) {
    echo "Missing required configuration: " . $e->getMessage();
    // Handle missing configuration (logging, exit, defaults, etc.)
    exit(1);
}

// Configuration type validation
try {
    $port = $config->ensureInt('database.port');
    $debug = $config->ensureBool('app.debug');
} catch (\InvalidArgumentException $e) {
    echo "Invalid configuration type: " . $e->getMessage();
    exit(1);
}
```

## Global Helper Functions

```php
// Get environment variable with automatic type conversion
// Note: Reads from $_ENV superglobal (mutable during runtime)
$debug = env('APP_DEBUG', false);      // boolean
$port = env('DB_PORT', 3306);         // integer  
$name = env('APP_NAME', 'MyApp');     // string

// Typed functions for environment variables
$appName = env_string('APP_NAME', 'DefaultApp');
$dbPort = env_int('DB_PORT', 3306);
$debugMode = env_bool('APP_DEBUG', false);
$maxMemory = env_float('MEMORY_LIMIT', 128.0);
$trustedHosts = env_array('TRUSTED_HOSTS', []);

// Get configuration from container
$config = conf($container);

// Get service with default fallback
$logger = cget($container, 'logger', new NullLogger());

// Create literal key (for keys containing dots)
$apiKey = $config->get(key('external.api.key')); // Equivalent to Key::literal('external.api.key')
$stripeKey = $config->getString(key('stripe.publishable.key'));

// For immutable environment access use:
$debug = $config->environment->getBool('APP_DEBUG', false);
$port = $config->environment->getInt('DB_PORT', 3306);
```

## Custom File Readers

```php
use Bermuda\Config\Reader\CsvFileReader;
use Bermuda\Config\Reader\XmlFileReader;

// Custom CSV reader
$csvReader = new CsvFileReader(
    delimiter: ';',
    hasHeaders: true,
    autoDetectTypes: true
);

// Custom XML reader
$xmlReader = XmlFileReader::createForConfig();

$provider = new FileProvider('config/*', [$csvReader, $xmlReader]);
```

## System Requirements

- PHP 8.4 or higher
- ext-json
- ext-simplexml
- symfony/yaml

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
