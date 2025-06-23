<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Performance;

use HPlus\Actor\Game\PlayerActor;
use HPlus\Actor\Game\RoomActor;
use HPlus\Actor\Message\Message;
use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Router\MessageRouter;
use HPlus\Actor\Mailbox\MailboxFactory;
use HPlus\Actor\System\ActorSystem;
use HPlus\Actor\System\ActorContext;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Actor系统性能测试
 * 测试系统在高负载下的表现
 */
class PerformanceTest extends \TestCase
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

    /**
     * @group performance
     */
    public function testActorCreationPerformance(): void
    {
        $actorCount = 1000;
        $start = microtime(true);

        $actorPaths = [];
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "perf_player_{$i}");
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->assertCount($actorCount, $actorPaths);
        $this->assertLessThan(5.0, $duration, "创建{$actorCount}个Actor耗时{$duration}秒，超过预期");
        
        // 计算创建速率
        $creationRate = $actorCount / $duration;
        $this->assertGreaterThan(200, $creationRate, "Actor创建速率{$creationRate}/秒低于预期");

        echo "\n🚀 Actor创建性能: {$actorCount}个Actor，耗时" . round($duration, 3) . "秒，速率" . round($creationRate) . "/秒\n";
    }

    /**
     * @group performance
     */
    public function testMessageRoutingPerformance(): void
    {
        $actorCount = 100;
        $messagesPerActor = 100;
        $totalMessages = $actorCount * $messagesPerActor;

        // 创建Actor
        $actorPaths = [];
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "routing_perf_player_{$i}");
        }

        $start = microtime(true);

        // 发送消息
        foreach ($actorPaths as $index => $actorPath) {
            for ($j = 0; $j < $messagesPerActor; $j++) {
                $message = new Message('player.update_info', [
                    'player_index' => $index,
                    'message_index' => $j,
                    'timestamp' => microtime(true),
                ], $actorPath);
                
                $this->router->route($message);
            }
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->assertLessThan(2.0, $duration, "路由{$totalMessages}条消息耗时{$duration}秒，超过预期");
        
        $routingRate = $totalMessages / $duration;
        $this->assertGreaterThan(5000, $routingRate, "消息路由速率{$routingRate}/秒低于预期");

        echo "\n📮 消息路由性能: {$totalMessages}条消息，耗时" . round($duration, 3) . "秒，速率" . round($routingRate) . "/秒\n";
    }

    /**
     * @group performance
     */
    public function testMailboxPerformance(): void
    {
        $messageCount = 10000;
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'mailbox_perf_player');
        $mailbox = $this->actorSystem->getMailboxFactory()->getMailbox($playerPath);

        // 测试入队性能
        $start = microtime(true);
        for ($i = 0; $i < $messageCount; $i++) {
            $message = new Message("perf_test_{$i}", ['index' => $i]);
            $mailbox->enqueue($message);
        }
        $enqueueTime = microtime(true) - $start;

        // 测试出队性能
        $start = microtime(true);
        for ($i = 0; $i < $messageCount; $i++) {
            $mailbox->dequeue();
        }
        $dequeueTime = microtime(true) - $start;

        $enqueueRate = $messageCount / $enqueueTime;
        $dequeueRate = $messageCount / $dequeueTime;

        $this->assertGreaterThan(10000, $enqueueRate, "邮箱入队速率{$enqueueRate}/秒低于预期");
        $this->assertGreaterThan(10000, $dequeueRate, "邮箱出队速率{$dequeueRate}/秒低于预期");

        echo "\n📫 邮箱性能: 入队" . round($enqueueRate) . "/秒，出队" . round($dequeueRate) . "/秒\n";
    }

    /**
     * @group performance
     */
    public function testConcurrentGameRoomPerformance(): void
    {
        $roomCount = 50;
        $playersPerRoom = 4;
        $totalPlayers = $roomCount * $playersPerRoom;

        $start = microtime(true);

        // 创建房间和玩家
        $rooms = [];
        $players = [];
        
        for ($i = 0; $i < $roomCount; $i++) {
            $roomPath = $this->actorSystem->actorOf(RoomActor::class, "perf_room_{$i}", [
                'max_players' => $playersPerRoom,
                'min_players' => 2,
            ]);
            $rooms[] = $roomPath;

            // 为每个房间创建玩家
            for ($j = 0; $j < $playersPerRoom; $j++) {
                $playerPath = $this->actorSystem->actorOf(PlayerActor::class, "perf_room_{$i}_player_{$j}");
                $players[] = ['path' => $playerPath, 'room' => $roomPath];
            }
        }

        $setupTime = microtime(true) - $start;

        // 模拟游戏流程
        $start = microtime(true);

        foreach ($players as $playerInfo) {
            $player = $this->registry->get($playerInfo['path']);
            $room = $this->registry->get($playerInfo['room']);
            
            // 玩家登录
            $player->receive(new Message('player.login', [
                'username' => basename($playerInfo['path']),
            ]));

            // 加入房间
            $room->receive(new Message('room.join_request', [
                'player_id' => $player->getId(),
                'player_path' => $playerInfo['path'],
                'player_data' => ['username' => basename($playerInfo['path'])],
            ]));
        }

        // 开始所有房间的游戏
        foreach ($rooms as $roomPath) {
            $room = $this->registry->get($roomPath);
            $room->receive(new Message('room.start_game', [
                'game_mode' => 'performance_test',
            ]));
        }

        $gameFlowTime = microtime(true) - $start;
        $totalTime = $setupTime + $gameFlowTime;

        $this->assertLessThan(10.0, $totalTime, "处理{$roomCount}个房间{$totalPlayers}个玩家的游戏流程耗时{$totalTime}秒，超过预期");

        echo "\n🎮 游戏房间性能: {$roomCount}房间{$totalPlayers}玩家，设置" . round($setupTime, 3) . "秒，游戏流程" . round($gameFlowTime, 3) . "秒\n";
    }

    /**
     * @group performance
     */
    public function testMemoryUsageUnderLoad(): void
    {
        $initialMemory = memory_get_usage(true);
        $actorCount = 500;

        // 创建大量Actor
        $actorPaths = [];
        for ($i = 0; $i < $actorCount; $i++) {
            $actorPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "memory_test_player_{$i}");
        }

        $afterCreationMemory = memory_get_usage(true);
        $creationMemoryUsage = $afterCreationMemory - $initialMemory;

        // 发送大量消息
        foreach ($actorPaths as $actorPath) {
            for ($j = 0; $j < 10; $j++) {
                $message = new Message('player.update_info', [
                    'large_data' => str_repeat('test_data_', 100), // 模拟较大的消息
                    'index' => $j,
                ], $actorPath);
                
                $this->router->route($message);
            }
        }

        $afterMessagesMemory = memory_get_usage(true);
        $messagesMemoryUsage = $afterMessagesMemory - $afterCreationMemory;
        $totalMemoryUsage = $afterMessagesMemory - $initialMemory;

        // 内存使用断言
        $memoryPerActor = $creationMemoryUsage / $actorCount;
        $this->assertLessThan(50 * 1024, $memoryPerActor, "每个Actor平均内存使用{$memoryPerActor}字节过高");

        $maxTotalMemory = 100 * 1024 * 1024; // 100MB
        $this->assertLessThan($maxTotalMemory, $totalMemoryUsage, "总内存使用" . round($totalMemoryUsage / 1024 / 1024, 2) . "MB超过限制");

        echo "\n💾 内存使用: 创建" . round($creationMemoryUsage / 1024 / 1024, 2) . "MB，消息" . round($messagesMemoryUsage / 1024 / 1024, 2) . "MB，总计" . round($totalMemoryUsage / 1024 / 1024, 2) . "MB\n";
    }

    /**
     * @group performance
     */
    public function testActorLifecyclePerformance(): void
    {
        $cycleCount = 200;
        $start = microtime(true);

        for ($i = 0; $i < $cycleCount; $i++) {
            // 创建Actor
            $actorPath = $this->actorSystem->actorOf(PlayerActor::class, "lifecycle_test_{$i}");
            
            // 使用Actor
            $actor = $this->registry->get($actorPath);
            $actor->receive(new Message('player.login', ['username' => "user_{$i}"]));
            $actor->receive(new Message('player.update_info', ['level' => $i]));
            
            // 停止Actor
            $this->registry->stop($actorPath);
        }

        $end = microtime(true);
        $duration = $end - $start;

        $lifecycleRate = $cycleCount / $duration;
        $this->assertGreaterThan(50, $lifecycleRate, "Actor生命周期速率{$lifecycleRate}/秒低于预期");

        echo "\n♻️ Actor生命周期: {$cycleCount}次循环，耗时" . round($duration, 3) . "秒，速率" . round($lifecycleRate) . "/秒\n";
    }

    /**
     * @group performance
     */
    public function testMessagePriorityPerformance(): void
    {
        $actorPath = $this->actorSystem->actorOf(PlayerActor::class, 'priority_test_player');
        $mailbox = $this->actorSystem->getMailboxFactory()->getMailbox($actorPath);

        $messageCount = 1000;
        $start = microtime(true);

        // 发送不同优先级的消息
        for ($i = 0; $i < $messageCount; $i++) {
            $priority = $i % 10; // 0-9的优先级
            $message = new Message("priority_test_{$i}", ['index' => $i], $actorPath, null, $priority);
            $this->router->route($message);
        }

        $routingTime = microtime(true) - $start;

        // 处理消息
        $start = microtime(true);
        $processedCount = 0;
        while (!$mailbox->isEmpty() && $processedCount < $messageCount) {
            $message = $mailbox->dequeue();
            if ($message) {
                $processedCount++;
            }
        }
        $processingTime = microtime(true) - $start;

        $routingRate = $messageCount / $routingTime;
        $processingRate = $processedCount / $processingTime;

        $this->assertEquals($messageCount, $processedCount);
        $this->assertGreaterThan(1000, $routingRate, "优先级消息路由速率{$routingRate}/秒低于预期");
        $this->assertGreaterThan(1000, $processingRate, "优先级消息处理速率{$processingRate}/秒低于预期");

        echo "\n⚡ 优先级消息: 路由" . round($routingRate) . "/秒，处理" . round($processingRate) . "/秒\n";
    }
} 