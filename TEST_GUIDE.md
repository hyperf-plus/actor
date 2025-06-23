# Actor系统测试指南

本指南详细介绍了Actor系统的完整测试体系，包括测试架构、运行方式、覆盖范围等。

## 📋 测试概览

### 测试架构

```
tests/
├── bootstrap.php           # 测试引导文件
├── Unit/                  # 单元测试
│   ├── MessageTest.php    # 消息系统测试
│   ├── MailboxTest.php    # 邮箱系统测试
│   ├── ActorRegistryTest.php # Actor注册表测试
│   ├── PlayerActorTest.php   # 玩家Actor测试
│   └── RoomActorTest.php     # 房间Actor测试
├── Integration/           # 集成测试
│   └── ActorSystemIntegrationTest.php
├── Performance/           # 性能测试
│   └── PerformanceTest.php
├── FaultTolerance/        # 故障容错测试
│   └── FaultToleranceTest.php
└── Feature/               # 功能测试
    └── ActorSystemTest.php
```

### 测试分类

#### 🔧 单元测试 (Unit Tests)
- **消息系统测试**: 验证消息创建、序列化、属性访问等
- **邮箱系统测试**: 测试消息队列、入队出队、容量限制等
- **Actor注册表测试**: 验证Actor创建、注册、查找、停止等
- **游戏Actor测试**: 测试玩家Actor和房间Actor的业务逻辑

#### 🔗 集成测试 (Integration Tests)
- **系统级交互测试**: 验证各组件间的协作
- **完整游戏流程测试**: 模拟真实的游戏场景
- **消息路由测试**: 验证消息在系统中的流转
- **状态持久化测试**: 验证Actor状态管理

#### ⚡ 性能测试 (Performance Tests)
- **Actor创建性能**: 测试大量Actor创建的性能
- **消息路由性能**: 测试高并发消息处理能力
- **邮箱性能**: 测试消息队列的吞吐量
- **内存使用测试**: 监控内存占用和清理
- **游戏房间性能**: 测试并发游戏场景

#### 🛡️ 故障容错测试 (Fault Tolerance Tests)
- **Actor失败处理**: 测试Actor异常和重启机制
- **内存泄漏检测**: 检测和处理内存泄漏
- **系统恢复测试**: 验证大规模故障后的恢复能力
- **资源耗尽处理**: 测试资源限制下的系统行为

## 🚀 运行测试

### 1. 快速开始

```bash
# 安装依赖
composer install --dev

# 运行所有基础测试（不包括性能测试）
./run-tests.sh

# 或者使用PHPUnit
vendor/bin/phpunit --exclude-group performance
```

### 2. 运行特定测试套件

```bash
# 单元测试
./run-tests.sh -s Unit

# 集成测试
./run-tests.sh -s Integration

# 性能测试
./run-tests.sh -p

# 故障容错测试
./run-tests.sh -s FaultTolerance
```

### 3. 生成代码覆盖率报告

```bash
# 生成HTML覆盖率报告
./run-tests.sh -c

# 查看报告
open build/coverage/index.html
```

### 4. 过滤特定测试

```bash
# 运行包含特定名称的测试
./run-tests.sh -f testActorCreation

# 运行特定类的测试
./run-tests.sh -f MessageTest
```

## 📊 测试覆盖范围

### 核心组件覆盖

| 组件 | 覆盖率目标 | 测试类型 |
|------|------------|----------|
| Message系统 | 100% | 单元测试 |
| Mailbox系统 | 95% | 单元测试 + 性能测试 |
| Actor注册表 | 90% | 单元测试 + 集成测试 |
| 消息路由器 | 90% | 集成测试 + 性能测试 |
| 游戏Actor | 85% | 单元测试 + 集成测试 |
| 系统监督 | 80% | 故障容错测试 |

### 功能覆盖

✅ **基础功能**
- Actor创建和生命周期管理
- 消息发送和接收
- 邮箱队列管理
- 系统状态监控

✅ **高级功能**
- 消息优先级处理
- Actor故障恢复
- 内存管理和清理
- 并发处理能力

✅ **游戏特性**
- 玩家状态管理
- 房间创建和管理
- 游戏流程控制
- 实时消息广播

## 🎯 性能基准

### 预期性能指标

| 指标 | 目标值 | 测试场景 |
|------|--------|----------|
| Actor创建速率 | >200/秒 | 1000个Actor创建 |
| 消息路由速率 | >5000/秒 | 10000条消息路由 |
| 邮箱吞吐量 | >10000/秒 | 入队出队操作 |
| 内存使用 | <50KB/Actor | 平均内存占用 |
| 并发游戏房间 | 50房间200玩家 | 同时在线处理 |

### 性能测试结果示例

```
🚀 Actor创建性能: 1000个Actor，耗时4.123秒，速率243/秒
📮 消息路由性能: 10000条消息，耗时1.876秒，速率5330/秒
📫 邮箱性能: 入队12547/秒，出队15632/秒
🎮 游戏房间性能: 50房间200玩家，设置2.1秒，游戏流程1.8秒
💾 内存使用: 创建45.2MB，消息12.8MB，总计58.0MB
```

## 🔍 测试最佳实践

### 1. 编写测试的原则

- **单一职责**: 每个测试方法只验证一个功能点
- **独立性**: 测试之间不应有依赖关系
- **可重复**: 测试结果应该可重现
- **快速执行**: 单元测试应该快速完成
- **清晰命名**: 测试方法名应该描述测试内容

### 2. 测试数据管理

```php
// 使用setUp方法准备测试环境
protected function setUp(): void
{
    parent::setUp();
    $this->actorSystem = $this->createActorSystem();
}

// 使用tearDown方法清理资源
protected function tearDown(): void
{
    $this->actorSystem->shutdown();
    parent::tearDown();
}
```

### 3. 异常测试

```php
// 验证异常情况
public function testInvalidMessage(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Message type cannot be empty');
    
    new Message('', []);
}
```

### 4. Mock对象使用

```php
// 使用Mock对象隔离依赖
$mockRouter = \Mockery::mock(MessageRouter::class);
$mockRouter->shouldReceive('route')
    ->once()
    ->with(\Mockery::type(Message::class))
    ->andReturn(true);
```

## 🐛 调试测试

### 1. 查看详细输出

```bash
# 启用详细输出
./run-tests.sh -v

# 或者
vendor/bin/phpunit --verbose
```

### 2. 调试单个测试

```bash
# 运行单个测试方法
vendor/bin/phpunit --filter testActorCreation tests/Unit/ActorRegistryTest.php
```

### 3. 查看测试覆盖率

```bash
# 生成覆盖率报告
./run-tests.sh -c

# 查看具体文件的覆盖情况
ls -la build/coverage/
```

## 📈 持续集成

### GitHub Actions配置示例

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: swoole
    
    - name: Install dependencies
      run: composer install --dev
    
    - name: Run tests
      run: ./run-tests.sh
    
    - name: Run performance tests
      run: ./run-tests.sh -p
```

## 🎭 测试环境

### 1. 本地开发环境

```bash
# 安装Swoole扩展
pecl install swoole

# 确认PHP版本
php -v  # 需要PHP 8.1+

# 检查扩展
php -m | grep swoole
```

### 2. 容器化测试环境

```dockerfile
FROM php:8.1-cli

# 安装Swoole
RUN pecl install swoole && docker-php-ext-enable swoole

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /app

# 复制代码
COPY . .

# 安装依赖
RUN composer install --dev

# 运行测试
CMD ["./run-tests.sh"]
```

## 🔧 常见问题

### Q: 测试运行很慢怎么办？
A: 
- 确保安装了PHP OPcache
- 排除性能测试：`./run-tests.sh`
- 使用并行测试工具

### Q: 内存不足错误
A:
- 增加PHP内存限制：`php -d memory_limit=512M vendor/bin/phpunit`
- 检查内存泄漏测试
- 使用垃圾回收：`gc_collect_cycles()`

### Q: Swoole扩展问题
A:
- 确认Swoole版本兼容性
- 检查PHP版本要求
- 查看扩展加载状态

### Q: 测试覆盖率低
A:
- 检查测试配置文件
- 确认测试路径正确
- 添加更多边界情况测试

## 📚 相关资源

- [PHPUnit文档](https://phpunit.de/documentation.html)
- [Mockery文档](http://docs.mockery.io/)
- [Swoole文档](https://www.swoole.co.uk/)
- [Actor模型理论](https://en.wikipedia.org/wiki/Actor_model)

---

💡 **提示**: 定期运行完整的测试套件，确保代码质量和系统稳定性。在添加新功能时，务必编写相应的测试用例。 