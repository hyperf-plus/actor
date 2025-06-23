<?php
declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext as UtilsApplicationContext;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// 设置测试环境
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// 创建测试容器
$container = new Container(new DefinitionSource([]));

// 模拟配置
$config = [
    'actor' => [
        'enabled' => true,
        'worker_processes' => 2,
        'batch_size' => 50,
        'mailbox' => [
            'capacity' => 100,
        ],
        'supervision' => [
            'interval' => 5,
            'restart_strategy' => 'one_for_one',
            'max_restarts' => 3,
            'restart_window' => 60,
        ],
        'memory' => [
            'max_memory' => 256 * 1024 * 1024, // 256MB
            'max_system_memory' => 512 * 1024 * 1024, // 512MB
        ],
        'health_check' => [
            'interval' => 10,
            'enabled' => true,
        ],
        'stats' => [
            'interval' => 30,
            'enabled' => true,
        ],
        'game' => [
            'default_room' => [
                'max_players' => 4,
                'min_players' => 2,
                'auto_start' => false,
                'timeout' => 300,
            ],
        ],
        'logging' => [
            'level' => 'debug',
            'verbose' => true,
        ],
    ],
];

$configInterface = new class($config) implements ConfigInterface {
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    public function has(string $keys): bool
    {
        return $this->get($keys) !== null;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }
};

// 注册基础服务
$container->set(ConfigInterface::class, $configInterface);
$container->set(LoggerFactory::class, new class() {
    public function get(string $name = 'default')
    {
        return new class() {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
            public function debug(string $message, array $context = []): void {}
        };
    }
});

// 设置应用上下文
ApplicationContext::setContainer($container);

// 测试基类
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration;

    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = ApplicationContext::getContainer();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
} 