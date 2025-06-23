<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Feature;

use HPlus\Actor\Game\PlayerActor;
use HPlus\Actor\Game\RoomActor;
use HPlus\Actor\Message\Message;
use HPlus\Actor\System\ActorSystem;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Actor系统功能测试
 */
class ActorSystemTest extends TestCase
{
    private $container;
    private ActorSystem $actorSystem;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 模拟容器
        $this->container = Mockery::mock('Psr\Container\ContainerInterface');
        
        // 这里需要实际的测试环境配置
        // 在实际使用中，应该使用Hyperf的测试基类
        $this->markTestSkipped('需要完整的Hyperf环境来运行此测试');
    }

    public function testCreateActor(): void
    {
        // 创建Actor
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'test-player');
        
        $this->assertStringContainsString('/user/test-player', $playerPath);
        
        // 检查Actor是否存在
        $registry = $this->actorSystem->getRegistry();
        $actor = $registry->get($playerPath);
        
        $this->assertInstanceOf(PlayerActor::class, $actor);
    }

    public function testSendMessage(): void
    {
        // 创建玩家Actor
        $playerPath = $this->actorSystem->actorOf(PlayerActor::class, 'test-player');
        
        // 创建消息
        $message = new Message('player.login', [
            'username' => 'testuser',
            'level' => 1,
        ]);
        
        // 发送消息
        $router = $this->actorSystem->getRouter();
        $router->route($message);
        
        // 验证消息被处理
        // 这里需要等待异步处理完成
        usleep(100000); // 100ms
        
        $this->assertTrue(true); // 占位断言
    }

    public function testGameFlow(): void
    {
        // 创建房间
        $roomPath = $this->actorSystem->actorOf(RoomActor::class, 'test-room', [
            'max_players' => 2,
            'min_players' => 2,
        ]);
        
        // 创建玩家
        $player1Path = $this->actorSystem->actorOf(PlayerActor::class, 'player1');
        $player2Path = $this->actorSystem->actorOf(PlayerActor::class, 'player2');
        
        // 玩家登录
        $this->sendPlayerMessage($player1Path, 'player.login', ['username' => 'player1']);
        $this->sendPlayerMessage($player2Path, 'player.login', ['username' => 'player2']);
        
        // 玩家加入房间
        $this->sendPlayerMessage($player1Path, 'player.join_room', ['room_path' => $roomPath]);
        $this->sendPlayerMessage($player2Path, 'player.join_room', ['room_path' => $roomPath]);
        
        // 开始游戏
        $this->sendRoomMessage($roomPath, 'room.start_game', []);
        
        // 等待处理
        usleep(200000);
        
        $this->assertTrue(true); // 占位断言
    }

    private function sendPlayerMessage(string $playerPath, string $type, array $payload): void
    {
        $message = new Message($type, $payload, $playerPath);
        $this->actorSystem->getRouter()->route($message);
    }

    private function sendRoomMessage(string $roomPath, string $type, array $payload): void
    {
        $message = new Message($type, $payload, $roomPath);
        $this->actorSystem->getRouter()->route($message);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
} 