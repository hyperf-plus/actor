# HPlus Actor 模型组件

高性能、多进程、高稳定性的Actor系统，专为游戏场景优化。

## 特性

- 🚀 **高性能**: 基于Swoole协程，支持大并发
- 🔄 **多进程**: 多工作进程并行处理消息
- 🛡️ **高稳定性**: 完善的错误处理和Actor重启机制
- 🎮 **游戏优化**: 专门为游戏场景设计的Actor类型
- 📊 **监控统计**: 实时监控Actor系统状态
- 🔧 **易于扩展**: 灵活的Actor继承和消息系统

## 安装

```bash
composer require hyperf-plus/actor
```

## 配置

发布配置文件：

```bash
php bin/hyperf.php vendor:publish hyperf-plus/actor
```

配置文件位于 `config/autoload/actor.php`

## 快速开始

### 1. 创建自定义Actor

```php
<?php
use HPlus\Actor\AbstractActor;
use HPlus\Actor\Message\MessageInterface;

class MyGameActor extends AbstractActor
{
    public function receive(MessageInterface $message): mixed
    {
        $type = $message->getType();
        $payload = $message->getPayload();

        return match ($type) {
            'hello' => $this->handleHello($payload),
            'ping' => ['pong' => time()],
            default => ['error' => 'Unknown message type'],
        };
    }

    private function handleHello(array $payload): array
    {
        $name = $payload['name'] ?? 'World';
        return ['message' => "Hello, {$name}!"];
    }
}
```

### 2. 创建和使用Actor

```php
<?php
use HPlus\Actor\System\ActorSystem;
use HPlus\Actor\Message\Message;

// 获取Actor系统
$actorSystem = $container->get(ActorSystem::class);

// 创建Actor
$actorPath = $actorSystem->actorOf(MyGameActor::class, 'my-actor');

// 发送消息
$message = new Message('hello', ['name' => 'HPlus']);
$router = $actorSystem->getRouter();
$router->route($message);
```

### 3. 游戏场景示例

```php
<?php
use HPlus\Actor\Game\PlayerActor;
use HPlus\Actor\Game\RoomActor;

// 创建游戏房间
$roomPath = $actorSystem->actorOf(RoomActor::class, 'game-room-1', [
    'max_players' => 4,
    'min_players' => 2,
]);

// 创建玩家
$playerPath = $actorSystem->actorOf(PlayerActor::class, 'player-1');

// 玩家加入房间
$joinMessage = new Message('player.join_room', [
    'room_path' => $roomPath,
]);
```

## 核心概念

### Actor模型

Actor是系统中的基本计算单元，具有以下特点：

- **独立性**: 每个Actor都有自己的状态和行为
- **消息传递**: Actor之间通过异步消息通信
- **顺序处理**: 每个Actor按顺序处理消息
- **容错性**: Actor失败不会影响其他Actor

### 消息系统

消息是Actor之间通信的载体：

```php
$message = new Message(
    'message_type',     // 消息类型
    ['key' => 'value'], // 消息载荷
    '/user/target',     // 接收者路径
    '/user/sender',     // 发送者路径
    1,                  // 优先级
    true,               // 是否需要回复
    'reply_id'          // 回复ID
);
```

### 邮箱系统

每个Actor都有一个邮箱来接收和缓存消息：

- **容量控制**: 防止内存溢出
- **优先级**: 支持消息优先级
- **批处理**: 提高处理效率

## 游戏专用组件

### PlayerActor

处理玩家相关逻辑：

```php
// 玩家登录
$loginMsg = new Message('player.login', [
    'username' => 'player1',
    'level' => 10,
]);

// 玩家游戏动作
$actionMsg = new Message('player.game_action', [
    'action_type' => 'move',
    'x' => 100,
    'y' => 200,
]);
```

### RoomActor

管理游戏房间：

```php
// 开始游戏
$startMsg = new Message('room.start_game', [
    'game_mode' => 'classic',
    'duration' => 300,
]);

// 广播消息
$broadcastMsg = new Message('room.broadcast', [
    'message' => 'Game will start in 10 seconds',
]);
```

## 监控和管理

### 查看系统状态

```bash
php bin/hyperf.php actor:stats
```

### 实时监控

```bash
php bin/hyperf.php actor:stats --watch
```

### 获取详细统计

```php
$status = $actorSystem->getStatus();
// 返回：
// [
//     'started' => true,
//     'actors' => 10,
//     'worker_processes' => 4,
//     'memory_usage' => 52428800,
//     'peak_memory' => 67108864,
// ]
```

## 性能优化

### 配置优化

```php
// config/autoload/actor.php
return [
    'worker_processes' => 8,        // 增加工作进程
    'batch_size' => 200,           // 提高批处理大小
    'mailbox' => [
        'capacity' => 2000,        // 增加邮箱容量
    ],
];
```

### 内存管理

```php
// 设置内存限制
'memory' => [
    'max_memory' => 1024 * 1024 * 1024,  // 1GB
    'max_system_memory' => 2048 * 1024 * 1024,  // 2GB
],
```

### 批处理优化

```php
// 批量发送消息
$messages = [
    new Message('type1', $data1),
    new Message('type2', $data2),
    // ...
];
$router->routeBatch($messages);
```

## 故障处理

### 重启策略

```php
'supervision' => [
    'restart_strategy' => 'one_for_one',  // 重启策略
    'max_restarts' => 3,                  // 最大重启次数
    'restart_window' => 60,               // 重启时间窗口
],
```

### 错误处理

```php
class MyActor extends AbstractActor
{
    public function receive(MessageInterface $message): mixed
    {
        try {
            // 处理消息
            return $this->processMessage($message);
        } catch (\Throwable $e) {
            $this->logger->error("Error processing message: " . $e->getMessage());
            return ['error' => 'Processing failed'];
        }
    }
}
```

## 扩展开发

### 自定义邮箱

```php
class CustomMailbox implements MailboxInterface
{
    // 实现接口方法
}
```

### 自定义消息路由

```php
class CustomRouter extends MessageRouter
{
    public function route(MessageInterface $message): void
    {
        // 自定义路由逻辑
        parent::route($message);
    }
}
```

## 最佳实践

### 1. Actor设计原则

- **单一职责**: 每个Actor只负责一个明确的功能
- **无状态共享**: 避免Actor间直接共享状态
- **异步通信**: 使用消息传递而非直接调用

### 2. 消息设计

- **幂等性**: 重复处理相同消息应该产生相同结果
- **版本控制**: 为消息类型添加版本信息
- **错误处理**: 总是处理消息处理失败的情况

### 3. 性能优化

- **批处理**: 尽可能批量处理消息
- **连接池**: 重用数据库连接等资源
- **缓存**: 合理使用缓存减少计算

### 4. 监控告警

- **关键指标**: 监控Actor数量、消息处理速度、内存使用
- **告警阈值**: 设置合理的告警阈值
- **日志记录**: 记录关键操作和错误信息

## 故障排除

### 常见问题

1. **内存泄漏**
   - 检查Actor状态是否正确清理
   - 确认消息处理完成后资源释放

2. **消息堆积**
   - 增加工作进程数量
   - 优化消息处理逻辑
   - 检查邮箱容量设置

3. **Actor崩溃**
   - 查看错误日志
   - 检查重启策略配置
   - 确认消息格式正确

### 调试技巧

```php
// 启用详细日志
'logging' => [
    'level' => 'debug',
    'verbose' => true,
],

// 启用消息追踪
'performance' => [
    'message_tracing' => true,
],
```

## 贡献

欢迎提交问题和改进建议到 [GitHub Issues](https://github.com/lphkxd/hyperf-plus/issues)

## 许可证

MIT License 