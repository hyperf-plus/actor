<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\FaultTolerance;

use HPlus\Actor\Game\PlayerActor;
use HPlus\Actor\Game\RoomActor;
use HPlus\Actor\Message\Message;
use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Router\MessageRouter;
use HPlus\Actor\Mailbox\MailboxFactory;
use HPlus\Actor\System\ActorSystem;
use HPlus\Actor\System\ActorContext;
use HPlus\Actor\AbstractActor;
use HPlus\Actor\Message\MessageInterface;

require_once __DIR__ . '/../bootstrap.php';

// 故障测试用Actor
class FaultyActor extends AbstractActor
{
    private int $callCount = 0;
    private int $failAfter;

    public function __construct(string $id, string $path, ActorContext $context, int $failAfter = 3)
    {
        parent::__construct($id, $path, $context);
        $this->failAfter = $failAfter;
    }

    public function receive(MessageInterface $message): mixed
    {
        $this->callCount++;
        
        if ($this->callCount >= $this->failAfter) {
            throw new \RuntimeException("Simulated failure after {$this->callCount} calls");
        }

        return ['success' => true, 'call_count' => $this->callCount];
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

// 内存泄漏测试Actor
class MemoryLeakActor extends AbstractActor
{
    private array $memoryHog = [];

    public function receive(MessageInterface $message): mixed
    {
        // 模拟内存泄漏
        if ($message->getType() === 'memory.leak') {
            $this->memoryHog[] = str_repeat('x', 1024 * 100); // 100KB
        }

        if ($message->getType() === 'memory.cleanup') {
            $this->memoryHog = [];
        }

        return [
            'success' => true,
            'memory_items' => count($this->memoryHog),
            'estimated_memory' => count($this->memoryHog) * 100 * 1024,
        ];
    }
}

/**
 * 故障容错测试
 * 测试系统的容错能力和恢复机制
 */
class FaultToleranceTest extends \TestCase
{
    private ActorSystem $actorSystem;
    private ActorRegistry $registry;
    private MessageRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->registry = new ActorRegistry($this->container);
        $mailboxFactory = new MailboxFactory($this->container, $this->container->get(\Hyperf\Contract\ConfigInterface::class));
        $this->router = new MessageRouter($this->container, $this->registry, $mailboxFactory);
        
        $this->actorSystem = new ActorSystem(
            $this->container,
            $this->container->get(\Hyperf\Contract\ConfigInterface::class),
            $this->registry,
            $this->router,
            $mailboxFactory
        );

        $this->container->set(ActorRegistry::class, $this->registry);
        $this->container->set(MessageRouter::class, $this->router);
        $this->container->set(ActorContext::class, function() use ($mailboxFactory) {
            return new ActorContext($this->container, $this->registry, $this->router);
        });
    }

    public function testActorFailureAndRestart(): void
    {
        // 创建故障Actor
        $actorPath = $this->actorSystem->actorOf(FaultyActor::class, 'faulty-actor', [2]); // 2次调用后失败
        $actor = $this->registry->get($actorPath);

        // 前两次调用应该成功
        $result1 = $actor->receive(new Message('test.call', []));
        $this->assertTrue($result1['success']);
        $this->assertEquals(1, $result1['call_count']);

        $result2 = $actor->receive(new Message('test.call', []));
        $this->assertTrue($result2['success']);
        $this->assertEquals(2, $result2['call_count']);

        // 第三次调用应该失败
        $this->expectException(\RuntimeException::class);
        $actor->receive(new Message('test.call', []));
    }

    public function testActorRestartAfterFailure(): void
    {
        $actorPath = $this->actorSystem->actorOf(FaultyActor::class, 'restart-test-actor', [3]);
        $actor = $this->registry->get($actorPath);
        $originalId = $actor->getId();

        // 模拟Actor失败并重启
        $exception = new \RuntimeException('Test failure');
        $this->registry->restart($actorPath, $exception);

        // 验证Actor被重启
        $restartedActor = $this->registry->get($actorPath);
        $this->assertNotNull($restartedActor);
        $this->assertEquals($originalId, $restartedActor->getId());
        
        // 重启后的Actor应该重置状态
        $result = $restartedActor->receive(new Message('test.call', []));
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['call_count']); // 重置为1
    }

    public function testMultipleActorFailures(): void
    {
        $actorCount = 5;
        $actorPaths = [];

        // 创建多个故障Actor
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(FaultyActor::class, "multi-fault-actor-{$i}", [2]);
        }

        // 让所有Actor都失败
        foreach ($actorPaths as $actorPath) {
            $actor = $this->registry->get($actorPath);
            
            // 前两次成功
            $actor->receive(new Message('test.call', []));
            $actor->receive(new Message('test.call', []));
            
            // 第三次失败并重启
            try {
                $actor->receive(new Message('test.call', []));
            } catch (\RuntimeException $e) {
                $this->registry->restart($actorPath, $e);
            }
        }

        // 验证所有Actor都已重启
        foreach ($actorPaths as $actorPath) {
            $actor = $this->registry->get($actorPath);
            $this->assertNotNull($actor);
            
            $result = $actor->receive(new Message('test.call', []));
            $this->assertEquals(1, $result['call_count']);
        }
    }

    public function testMemoryLeakDetectionAndCleanup(): void
    {
        $actorPath = $this->actorSystem->actorOf(MemoryLeakActor::class, 'memory-leak-actor');
        $actor = $this->registry->get($actorPath);

        $initialMemory = memory_get_usage(true);

        // 模拟内存泄漏
        for ($i = 0; $i < 10; $i++) {
            $result = $actor->receive(new Message('memory.leak', []));
            $this->assertTrue($result['success']);
        }

        $afterLeakMemory = memory_get_usage(true);
        $leakMemory = $afterLeakMemory - $initialMemory;

        // 验证内存增长
        $this->assertGreaterThan(500 * 1024, $leakMemory, "内存泄漏测试未产生预期的内存增长");

        // 清理内存
        $cleanupResult = $actor->receive(new Message('memory.cleanup', []));
        $this->assertTrue($cleanupResult['success']);
        $this->assertEquals(0, $cleanupResult['memory_items']);

        // 强制垃圾回收
        gc_collect_cycles();

        $afterCleanupMemory = memory_get_usage(true);
        $cleanupMemory = $afterCleanupMemory - $initialMemory;

        // 验证内存已清理（允许一些基础内存使用）
        $this->assertLessThan($leakMemory / 2, $cleanupMemory, "内存清理效果不明显");

        echo "\n🧹 内存清理测试: 泄漏" . round($leakMemory / 1024) . "KB，清理后" . round($cleanupMemory / 1024) . "KB\n";
    }

    public function testSystemRecoveryAfterMassiveFailure(): void
    {
        $actorCount = 20;
        $actorPaths = [];

        // 创建大量Actor
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "recovery-test-player-{$i}");
        }

        // 记录初始状态
        $initialStatus = $this->actorSystem->getStatus();
        $this->assertEquals($actorCount, $initialStatus['actors']);

        // 模拟大规模故障（停止一半的Actor）
        $failedCount = 0;
        for ($i = 0; $i < $actorCount / 2; $i++) {
            $this->registry->stop($actorPaths[$i]);
            $failedCount++;
        }

        // 验证故障后状态
        $afterFailureStatus = $this->actorSystem->getStatus();
        $this->assertEquals($actorCount - $failedCount, $afterFailureStatus['actors']);

        // 重新创建失败的Actor
        for ($i = 0; $i < $failedCount; $i++) {
            $newActorPath = $this->actorSystem->actorOf(PlayerActor::class, "recovery-new-player-{$i}");
            $this->assertNotNull($this->registry->get($newActorPath));
        }

        // 验证系统恢复
        $recoveryStatus = $this->actorSystem->getStatus();
        $this->assertEquals($actorCount, $recoveryStatus['actors']);
    }

    public function testMessageRoutingUnderFailure(): void
    {
        $actorPath = $this->actorSystem->actorOf(PlayerActor::class, 'message-routing-test');
        $mailbox = $this->actorSystem->getMailboxFactory()->getMailbox($actorPath);

        // 发送消息到邮箱
        $messageCount = 10;
        for ($i = 0; $i < $messageCount; $i++) {
            $message = new Message("test.message.{$i}", ['index' => $i], $actorPath);
            $this->router->route($message);
        }

        $this->assertEquals($messageCount, $mailbox->size());

        // 模拟Actor失败
        $this->registry->stop($actorPath);
        $this->assertNull($this->registry->get($actorPath));

        // 重新创建Actor
        $newActorPath = $this->actorSystem->actorOf(PlayerActor::class, 'message-routing-test-new');
        $newMailbox = $this->actorSystem->getMailboxFactory()->getMailbox($newActorPath);

        // 新Actor应该有空的邮箱
        $this->assertTrue($newMailbox->isEmpty());

        // 重新发送消息
        for ($i = 0; $i < $messageCount; $i++) {
            $message = new Message("recovery.message.{$i}", ['index' => $i], $newActorPath);
            $this->router->route($message);
        }

        $this->assertEquals($messageCount, $newMailbox->size());
    }

    public function testConcurrentFailuresAndRecovery(): void
    {
        $actorCount = 10;
        $actorPaths = [];

        // 创建多个Actor
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "concurrent-failure-{$i}");
        }

        // 并发发送消息给所有Actor
        foreach ($actorPaths as $actorPath) {
            for ($j = 0; $j < 5; $j++) {
                $message = new Message('player.update_info', ['index' => $j], $actorPath);
                $this->router->route($message);
            }
        }

        // 验证所有邮箱都有消息
        foreach ($actorPaths as $actorPath) {
            $mailbox = $this->actorSystem->getMailboxFactory()->getMailbox($actorPath);
            $this->assertEquals(5, $mailbox->size());
        }

        // 随机失败一半的Actor
        $failedPaths = array_slice($actorPaths, 0, $actorCount / 2);
        foreach ($failedPaths as $actorPath) {
            $this->registry->stop($actorPath);
        }

        // 验证失败的Actor不存在
        foreach ($failedPaths as $actorPath) {
            $this->assertNull($this->registry->get($actorPath));
        }

        // 验证剩余的Actor仍然正常
        $remainingPaths = array_slice($actorPaths, $actorCount / 2);
        foreach ($remainingPaths as $actorPath) {
            $actor = $this->registry->get($actorPath);
            $this->assertNotNull($actor);
            
            $result = $actor->receive(new Message('player.get_info', []));
            $this->assertTrue($result['success']);
        }

        echo "\n⚡ 并发故障测试: {$actorCount}个Actor，" . ($actorCount/2) . "个失败，系统正常运行\n";
    }

    public function testResourceExhaustionRecovery(): void
    {
        $actorPath = $this->actorSystem->actorOf(PlayerActor::class, 'resource-test');
        $mailbox = $this->actorSystem->getMailboxFactory()->getMailbox($actorPath);

        $initialMemory = memory_get_usage(true);
        $messageCount = 1000;

        // 发送大量消息以测试资源使用
        for ($i = 0; $i < $messageCount; $i++) {
            $largePayload = [
                'index' => $i,
                'data' => str_repeat("test_data_{$i}_", 50), // 较大的消息载荷
                'timestamp' => microtime(true),
            ];
            
            $message = new Message('player.large_update', $largePayload, $actorPath);
            $this->router->route($message);
        }

        $afterMessagesMemory = memory_get_usage(true);
        $memoryUsed = $afterMessagesMemory - $initialMemory;

        $this->assertEquals($messageCount, $mailbox->size());

        // 清空邮箱模拟处理完消息
        $mailbox->clear();
        $this->assertTrue($mailbox->isEmpty());

        // 强制垃圾回收
        gc_collect_cycles();

        $afterCleanupMemory = memory_get_usage(true);
        $memoryAfterCleanup = $afterCleanupMemory - $initialMemory;

        // 验证内存得到释放
        $this->assertLessThan($memoryUsed * 0.5, $memoryAfterCleanup, "资源清理效果不明显");

        echo "\n🗑️ 资源清理测试: 使用" . round($memoryUsed / 1024) . "KB，清理后" . round($memoryAfterCleanup / 1024) . "KB\n";
    }

    public function testSystemStabilityUnderLoad(): void
    {
        $actorCount = 50;
        $messageCount = 20;
        $actorPaths = [];

        $start = microtime(true);

        // 创建大量Actor
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "stability-test-{$i}");
        }

        // 高频发送消息
        for ($round = 0; $round < $messageCount; $round++) {
            foreach ($actorPaths as $actorPath) {
                $message = new Message('player.update_info', [
                    'round' => $round,
                    'timestamp' => microtime(true),
                ], $actorPath);
                
                $this->router->route($message);
            }
        }

        $end = microtime(true);
        $duration = $end - $start;

        // 验证系统状态
        $status = $this->actorSystem->getStatus();
        $this->assertEquals($actorCount, $status['actors']);

        // 验证所有邮箱都有消息
        foreach ($actorPaths as $actorPath) {
            $mailbox = $this->actorSystem->getMailboxFactory()->getMailbox($actorPath);
            $this->assertEquals($messageCount, $mailbox->size());
        }

        $totalMessages = $actorCount * $messageCount;
        $throughput = $totalMessages / $duration;

        echo "\n🏋️ 稳定性测试: {$actorCount}个Actor，{$totalMessages}条消息，耗时" . round($duration, 3) . "秒，吞吐量" . round($throughput) . "/秒\n";
    }
} 