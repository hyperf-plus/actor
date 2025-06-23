<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Unit;

use HPlus\Actor\Game\PlayerActor;
use HPlus\Actor\Message\Message;
use HPlus\Actor\System\ActorContext;

require_once __DIR__ . '/../bootstrap.php';

/**
 * 玩家Actor测试
 */
class PlayerActorTest extends \TestCase
{
    private PlayerActor $playerActor;
    private ActorContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建模拟的上下文
        $this->context = \Mockery::mock(ActorContext::class);
        $this->context->shouldReceive('getContainer')->andReturn($this->container);
        
        $this->playerActor = new PlayerActor('player_123', '/user/player1', $this->context);
    }

    public function testPlayerLogin(): void
    {
        $loginMessage = new Message('player.login', [
            'username' => 'testuser',
            'level' => 10,
            'score' => 1000,
        ]);

        $result = $this->playerActor->receive($loginMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Login successful', $result['message']);
        $this->assertEquals('player_123', $result['player_id']);
        $this->assertEquals('online', $result['status']);
    }

    public function testPlayerLogout(): void
    {
        // 先登录
        $this->playerActor->receive(new Message('player.login', ['username' => 'testuser']));

        // 再登出
        $logoutMessage = new Message('player.logout', []);
        $result = $this->playerActor->receive($logoutMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Logout successful', $result['message']);
    }

    public function testGetPlayerInfo(): void
    {
        // 先登录设置一些数据
        $this->playerActor->receive(new Message('player.login', [
            'username' => 'testuser',
            'level' => 5,
        ]));

        $infoMessage = new Message('player.get_info', []);
        $result = $this->playerActor->receive($infoMessage);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('player_123', $result['data']['player_id']);
        $this->assertEquals('/user/player1', $result['data']['player_path']);
        $this->assertEquals('online', $result['data']['status']);
        $this->assertArrayHasKey('player_data', $result['data']);
    }

    public function testUpdatePlayerInfo(): void
    {
        $updateMessage = new Message('player.update_info', [
            'level' => 15,
            'experience' => 2500,
        ]);

        $result = $this->playerActor->receive($updateMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Player info updated', $result['message']);
        $this->assertArrayHasKey('player_data', $result);
        $this->assertEquals(15, $result['player_data']['level']);
        $this->assertEquals(2500, $result['player_data']['experience']);
    }

    public function testJoinRoomWithoutRoomPath(): void
    {
        $joinMessage = new Message('player.join_room', []);
        $result = $this->playerActor->receive($joinMessage);

        $this->assertFalse($result['success']);
        $this->assertEquals('Room path required', $result['message']);
    }

    public function testJoinRoom(): void
    {
        // 模拟房间响应
        $this->context->shouldReceive('ask')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Joined successfully']);

        $joinMessage = new Message('player.join_room', [
            'room_path' => '/user/room1',
        ]);

        $result = $this->playerActor->receive($joinMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Joined room successfully', $result['message']);
        $this->assertEquals('/user/room1', $result['room_path']);
    }

    public function testJoinRoomFailure(): void
    {
        // 模拟房间拒绝
        $this->context->shouldReceive('ask')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Room is full']);

        $joinMessage = new Message('player.join_room', [
            'room_path' => '/user/room1',
        ]);

        $result = $this->playerActor->receive($joinMessage);

        $this->assertFalse($result['success']);
        $this->assertEquals('Room is full', $result['message']);
    }

    public function testLeaveRoomWhenNotInRoom(): void
    {
        $leaveMessage = new Message('player.leave_room', []);
        $result = $this->playerActor->receive($leaveMessage);

        $this->assertFalse($result['success']);
        $this->assertEquals('Not in any room', $result['message']);
    }

    public function testGameActionWithoutRoom(): void
    {
        $actionMessage = new Message('player.game_action', [
            'action_type' => 'move',
            'x' => 100,
            'y' => 200,
        ]);

        $result = $this->playerActor->receive($actionMessage);

        $this->assertFalse($result['success']);
        $this->assertEquals('Not in any room', $result['message']);
    }

    public function testGameActionNotPlaying(): void
    {
        // 模拟加入房间但游戏未开始
        $this->context->shouldReceive('ask')->once()->andReturn(['success' => true]);
        $this->playerActor->receive(new Message('player.join_room', ['room_path' => '/user/room1']));

        $actionMessage = new Message('player.game_action', [
            'action_type' => 'move',
            'x' => 100,
            'y' => 200,
        ]);

        $result = $this->playerActor->receive($actionMessage);

        $this->assertFalse($result['success']);
        $this->assertEquals('Game not started', $result['message']);
    }

    public function testGameStart(): void
    {
        $startMessage = new Message('game.start', [
            'game_id' => 'game_123',
            'players' => ['player1', 'player2'],
        ]);

        $result = $this->playerActor->receive($startMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Game started', $result['message']);
        $this->assertArrayHasKey('game_data', $result);
    }

    public function testGameEnd(): void
    {
        // 先开始游戏
        $this->playerActor->receive(new Message('game.start', []));

        $endMessage = new Message('game.end', [
            'winner' => 'player2',
            'score' => 100,
        ]);

        $result = $this->playerActor->receive($endMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Game ended', $result['message']);
        $this->assertArrayHasKey('result', $result);
    }

    public function testRoomMessage(): void
    {
        $roomMessage = new Message('room.message', [
            'type' => 'notification',
            'content' => 'Welcome to the room',
        ]);

        $result = $this->playerActor->receive($roomMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Message received', $result['message']);
    }

    public function testUnknownMessage(): void
    {
        $unknownMessage = new Message('unknown.type', []);
        $result = $this->playerActor->receive($unknownMessage);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unknown message type', $result['message']);
    }

    public function testPlayerStateManagement(): void
    {
        // 初始状态
        $infoResult = $this->playerActor->receive(new Message('player.get_info', []));
        $this->assertEquals('online', $infoResult['data']['status']);

        // 登录后状态
        $this->playerActor->receive(new Message('player.login', ['username' => 'test']));
        $infoResult = $this->playerActor->receive(new Message('player.get_info', []));
        $this->assertEquals('online', $infoResult['data']['status']);

        // 游戏开始后状态
        $this->playerActor->receive(new Message('game.start', []));
        $infoResult = $this->playerActor->receive(new Message('player.get_info', []));
        $this->assertEquals('playing', $infoResult['data']['status']);

        // 游戏结束后状态
        $this->playerActor->receive(new Message('game.end', []));
        $infoResult = $this->playerActor->receive(new Message('player.get_info', []));
        $this->assertEquals('waiting', $infoResult['data']['status']);
    }
} 