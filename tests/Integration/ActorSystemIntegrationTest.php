<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Integration;

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
 * Actor系统集成测试
 * 测试各组件间的交互
 */
class ActorSystemIntegrationTest extends \TestCase
{
    private ActorSystem $actorSystem;
    private ActorRegistry $registry;
    private MessageRouter $router;
    private MailboxFactory $mailboxFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建真实的组件实例进行集成测试
        $this->registry = new ActorRegistry($this->container);
        $this->mailboxFactory = new MailboxFactory($this->container, $this->container->get(\Hyperf\Contract\ConfigInterface::class));
        $this->router = new MessageRouter($this->container, $this->registry, $this->mailboxFactory);
        
        $this->actorSystem = new ActorSystem(
            $this->container,
            $this->container->get(\Hyperf\Contract\ConfigInterface::class),
            $this->registry,
            $this->router,
            $this->mailboxFactory
        );

        // 注册依赖
        $this->container->set(ActorRegistry::class, $this->registry);
        $this->container->set(MessageRouter::class, $this->router);
        $this->container->set(MailboxFactory::class, $this->mailboxFactory);
        $this->container->set(ActorContext::class, function() {
            return new ActorContext($this->container, $this->registry, $this->router);
        });
    }

    public function testActorSystemInitialization(): void
    {
        $status = $this->actorSystem->getStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('started', $status);
        $this->assertArrayHasKey('actors', $status);
        $this->assertArrayHasKey('worker_processes', $status);
        $this->assertArrayHasKey('memory_usage', $status);
        $this->assertArrayHasKey('peak_memory', $status);
    }

    public function testCreateAndManageActors(): void
    {
        // 创建Actor
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'integration-player');
        $roomPath = $this->actorSystem->actorOf(RoomActor::class, 'integration-room');

        $this->assertNotEmpty($playerPath);
        $this->assertNotEmpty($roomPath);
        $this->assertStringContainsString('/user/integration-player', $playerPath);
        $this->assertStringContainsString('/user/integration-room', $roomPath);

        // 验证Actor存在
        $player = $this->registry->get($playerPath);
        $room = $this->registry->get($roomPath);

        $this->assertInstanceOf(PlayerActor::class, $player);
        $this->assertInstanceOf(RoomActor::class, $room);
    }

    public function testMessageRouting(): void
    {
        // 创建玩家Actor
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'routing-test-player');
        
        // 创建并发送消息
        $loginMessage = new Message('player.login', [
            'username' => 'test_user',
            'level' => 1,
        ], $playerPath);

        $this->router->route($loginMessage);

        // 验证消息被处理（需要从邮箱中处理）
        $mailbox = $this->mailboxFactory->getMailbox($playerPath);
        $this->assertFalse($mailbox->isEmpty());

        // 模拟处理消息
        $processedMessage = $mailbox->dequeue();
        $this->assertNotNull($processedMessage);
        $this->assertEquals('player.login', $processedMessage->getType());
    }

    public function testGameFlow(): void
    {
        // 创建房间和玩家
        $roomPath = $this->actorSystem->actorOf(RoomActor::class, 'game-flow-room', [
            'max_players' => 2,
            'min_players' => 2,
        ]);
        
        $player1Path = $this->actorSystem->actorOf(PlayerActor::class, 'game-flow-player1');
        $player2Path = $this->actorSystem->actorOf(PlayerActor::class, 'game-flow-player2');

        // 获取Actor实例
        $room = $this->registry->get($roomPath);
        $player1 = $this->registry->get($player1Path);
        $player2 = $this->registry->get($player2Path);

        // 玩家登录
        $player1->receive(new Message('player.login', ['username' => 'player1']));
        $player2->receive(new Message('player.login', ['username' => 'player2']));

        // 玩家1加入房间
        $joinResult1 = $room->receive(new Message('room.join_request', [
            'player_id' => $player1->getId(),
            'player_path' => $player1Path,
            'player_data' => ['username' => 'player1'],
        ]));
        $this->assertTrue($joinResult1['success']);

        // 玩家2加入房间
        $joinResult2 = $room->receive(new Message('room.join_request', [
            'player_id' => $player2->getId(),
            'player_path' => $player2Path,
            'player_data' => ['username' => 'player2'],
        ]));
        $this->assertTrue($joinResult2['success']);

        // 检查房间状态
        $roomInfo = $room->receive(new Message('room.get_info', []));
        $this->assertEquals(2, $roomInfo['data']['player_count']);

        // 开始游戏
        $startResult = $room->receive(new Message('room.start_game', [
            'game_mode' => 'test_mode',
        ]));
        $this->assertTrue($startResult['success']);
    }

    public function testMultipleActorsInteraction(): void
    {
        $actorCount = 5;
        $playerPaths = [];

        // 创建多个玩家Actor
        for ($i = 1; $i <= $actorCount; $i++) {
            $playerPath = $this->actorSystem->actorOf(PlayerActor::class, "multi-player-{$i}");
            $playerPaths[] = $playerPath;
        }

        // 创建房间
        $roomPath = $this->actorSystem->actorOf(RoomActor::class, 'multi-room', [
            'max_players' => $actorCount,
            'min_players' => 2,
        ]);

        $room = $this->registry->get($roomPath);

        // 所有玩家加入房间
        foreach ($playerPaths as $index => $playerPath) {
            $player = $this->registry->get($playerPath);
            
            $joinResult = $room->receive(new Message('room.join_request', [
                'player_id' => $player->getId(),
                'player_path' => $playerPath,
                'player_data' => ['username' => "player" . ($index + 1)],
            ]));
            
            $this->assertTrue($joinResult['success']);
        }

        // 验证所有玩家都在房间中
        $roomInfo = $room->receive(new Message('room.get_info', []));
        $this->assertEquals($actorCount, $roomInfo['data']['player_count']);
    }

    public function testActorFailureAndRecovery(): void
    {
        // 创建Actor
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'failure-test-player');
        $player = $this->registry->get($playerPath);
        $originalId = $player->getId();

        // 模拟Actor重启
        $exception = new \RuntimeException('Test failure');
        $this->registry->restart($playerPath, $exception);

        // 验证Actor仍然存在
        $restartedPlayer = $this->registry->get($playerPath);
        $this->assertNotNull($restartedPlayer);
        $this->assertEquals($originalId, $restartedPlayer->getId());
    }

    public function testMailboxCapacity(): void
    {
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'mailbox-test-player');
        $mailbox = $this->mailboxFactory->getMailbox($playerPath);

        $capacity = 100; // 根据配置
        
        // 填充邮箱到容量
        for ($i = 0; $i < $capacity; $i++) {
            $message = new Message("test_{$i}", ['index' => $i]);
            $mailbox->enqueue($message);
        }

        $this->assertEquals($capacity, $mailbox->size());
        $this->assertFalse($mailbox->isEmpty());

        // 清空邮箱
        $mailbox->clear();
        $this->assertTrue($mailbox->isEmpty());
        $this->assertEquals(0, $mailbox->size());
    }

    public function testSystemStats(): void
    {
        // 创建一些Actor
        for ($i = 1; $i <= 3; $i++) {
            $this->actorSystem->actorOf(PlayerActor::class, "stats-player-{$i}");
        }

        $status = $this->actorSystem->getStatus();
        
        $this->assertGreaterThanOrEqual(3, $status['actors']);
        $this->assertIsInt($status['memory_usage']);
        $this->assertIsInt($status['peak_memory']);
        $this->assertGreaterThan(0, $status['memory_usage']);
    }

    /**
     * @group performance
     */
    public function testConcurrentMessageProcessing(): void
    {
        $playerCount = 10;
        $messageCount = 50;
        $playerPaths = [];

        // 创建多个玩家
        for ($i = 1; $i <= $playerCount; $i++) {
            $playerPaths[] = $this->actorSystem->actorOf(PlayerActor::class, "concurrent-player-{$i}");
        }

        $start = microtime(true);

        // 并发发送消息
        foreach ($playerPaths as $playerPath) {
            for ($j = 0; $j < $messageCount; $j++) {
                $message = new Message('player.update_info', [
                    'index' => $j,
                    'timestamp' => microtime(true),
                ], $playerPath);
                
                $this->router->route($message);
            }
        }

        $end = microtime(true);
        $duration = $end - $start;

        // 性能断言：处理500条消息（10个Actor * 50条消息）应该在合理时间内完成
        $totalMessages = $playerCount * $messageCount;
        $this->assertLessThan(1.0, $duration, "处理{$totalMessages}条消息耗时{$duration}秒，超过预期");

        // 验证消息都被路由到邮箱
        foreach ($playerPaths as $playerPath) {
            $mailbox = $this->mailboxFactory->getMailbox($playerPath);
            $this->assertEquals($messageCount, $mailbox->size());
        }
    }

    public function testActorStatePersistence(): void
    {
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'state-test-player');
        $player = $this->registry->get($playerPath);

        // 设置玩家状态
        $player->receive(new Message('player.login', [
            'username' => 'stateful_user',
            'level' => 10,
            'gold' => 1000,
        ]));

        $player->receive(new Message('player.update_info', [
            'experience' => 2500,
            'achievements' => ['first_win', 'level_10'],
        ]));

        // 获取状态
        $infoResult = $player->receive(new Message('player.get_info', []));
        $playerData = $infoResult['data']['player_data'];

        $this->assertEquals('stateful_user', $playerData['username']);
        $this->assertEquals(10, $playerData['level']);
        $this->assertEquals(1000, $playerData['gold']);
        $this->assertEquals(2500, $playerData['experience']);
        $this->assertContains('first_win', $playerData['achievements']);
    }
} 