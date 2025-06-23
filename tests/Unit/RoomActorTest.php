<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Unit;

use HPlus\Actor\Game\RoomActor;
use HPlus\Actor\Message\Message;
use HPlus\Actor\System\ActorContext;

require_once __DIR__ . '/../bootstrap.php';

/**
 * 房间Actor测试
 */
class RoomActorTest extends \TestCase
{
    private RoomActor $roomActor;
    private ActorContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建模拟的上下文
        $this->context = \Mockery::mock(ActorContext::class);
        $this->context->shouldReceive('getContainer')->andReturn($this->container);
        
        $this->roomActor = new RoomActor('room_123', '/user/room1', $this->context, [
            'max_players' => 4,
            'min_players' => 2,
        ]);
    }

    public function testRoomInitialization(): void
    {
        $infoMessage = new Message('room.get_info', []);
        $result = $this->roomActor->receive($infoMessage);

        $this->assertTrue($result['success']);
        $roomInfo = $result['data'];
        $this->assertEquals('room_123', $roomInfo['room_id']);
        $this->assertEquals('/user/room1', $roomInfo['room_path']);
        $this->assertEquals('waiting', $roomInfo['status']);
        $this->assertEquals(4, $roomInfo['max_players']);
        $this->assertEquals(2, $roomInfo['min_players']);
        $this->assertEquals(0, $roomInfo['player_count']);
    }

    public function testPlayerJoinRoom(): void
    {
        $joinMessage = new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'testuser1', 'level' => 5],
        ]);

        $result = $this->roomActor->receive($joinMessage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Joined room successfully', $result['message']);
        $this->assertArrayHasKey('room_info', $result);
        $this->assertEquals(1, $result['room_info']['player_count']);
    }

    public function testPlayerJoinWithInvalidData(): void
    {
        $joinMessage = new Message('room.join_request', [
            'player_id' => '', // 空的player_id
            'player_path' => '/user/player1',
        ]);

        $result = $this->roomActor->receive($joinMessage);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid player data', $result['message']);
    }

    public function testMultiplePlayersJoin(): void
    {
        // 第一个玩家加入
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));

        // 第二个玩家加入
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player2',
            'player_path' => '/user/player2',
            'player_data' => ['username' => 'user2'],
        ]));

        // 检查房间状态
        $infoResult = $this->roomActor->receive(new Message('room.get_info', []));
        $this->assertEquals(2, $infoResult['data']['player_count']);
    }

    public function testRoomFull(): void
    {
        // 填满房间（4个玩家）
        for ($i = 1; $i <= 4; $i++) {
            $this->roomActor->receive(new Message('room.join_request', [
                'player_id' => "player{$i}",
                'player_path' => "/user/player{$i}",
                'player_data' => ['username' => "user{$i}"],
            ]));
        }

        // 尝试加入第5个玩家
        $result = $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player5',
            'player_path' => '/user/player5',
            'player_data' => ['username' => 'user5'],
        ]));

        $this->assertFalse($result['success']);
        $this->assertEquals('Room is full', $result['message']);
    }

    public function testPlayerLeaveRoom(): void
    {
        // 先加入一个玩家
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));

        // 玩家离开
        $leaveResult = $this->roomActor->receive(new Message('room.leave_request', [
            'player_id' => 'player1',
        ]));

        $this->assertTrue($leaveResult['success']);
        $this->assertEquals('Left room successfully', $leaveResult['message']);

        // 检查房间状态
        $infoResult = $this->roomActor->receive(new Message('room.get_info', []));
        $this->assertEquals(0, $infoResult['data']['player_count']);
    }

    public function testLeaveNonexistentPlayer(): void
    {
        $leaveResult = $this->roomActor->receive(new Message('room.leave_request', [
            'player_id' => 'nonexistent_player',
        ]));

        $this->assertFalse($leaveResult['success']);
        $this->assertEquals('Player not in room', $leaveResult['message']);
    }

    public function testStartGameWithEnoughPlayers(): void
    {
        // 添加足够的玩家
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player2',
            'player_path' => '/user/player2',
            'player_data' => ['username' => 'user2'],
        ]));

        // 模拟告诉玩家消息
        $this->context->shouldReceive('tell')->times(2);

        $startResult = $this->roomActor->receive(new Message('room.start_game', [
            'game_mode' => 'classic',
        ]));

        $this->assertTrue($startResult['success']);
        $this->assertEquals('Game started', $startResult['message']);
    }

    public function testStartGameWithoutEnoughPlayers(): void
    {
        // 只有一个玩家（少于最小要求2个）
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));

        $startResult = $this->roomActor->receive(new Message('room.start_game', []));

        $this->assertFalse($startResult['success']);
        $this->assertEquals('Not enough players', $startResult['message']);
    }

    public function testEndGame(): void
    {
        // 先开始游戏
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player2',
            'player_path' => '/user/player2',
            'player_data' => ['username' => 'user2'],
        ]));

        $this->context->shouldReceive('tell')->times(4); // 2次开始，2次结束

        $this->roomActor->receive(new Message('room.start_game', []));

        // 结束游戏
        $endResult = $this->roomActor->receive(new Message('room.end_game', [
            'reason' => 'Game completed',
            'winner' => 'player1',
        ]));

        $this->assertTrue($endResult['success']);
        $this->assertEquals('Game ended', $endResult['message']);
    }

    public function testBroadcastMessage(): void
    {
        // 添加玩家
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));

        // 模拟广播
        $this->context->shouldReceive('tell')->once();

        $broadcastResult = $this->roomActor->receive(new Message('room.broadcast', [
            'message' => 'Hello everyone!',
        ]));

        $this->assertTrue($broadcastResult['success']);
        $this->assertEquals('Message broadcasted', $broadcastResult['message']);
    }

    public function testPlayerGameAction(): void
    {
        // 设置游戏中状态
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'player_data' => ['username' => 'user1'],
        ]));
        $this->roomActor->receive(new Message('room.join_request', [
            'player_id' => 'player2',
            'player_path' => '/user/player2',
            'player_data' => ['username' => 'user2'],
        ]));

        $this->context->shouldReceive('tell')->times(4); // 开始游戏 + 动作广播

        $this->roomActor->receive(new Message('room.start_game', []));

        // 处理玩家动作
        $actionResult = $this->roomActor->receive(new Message('game.player_action', [
            'player_id' => 'player1',
            'player_path' => '/user/player1',
            'action' => ['type' => 'move', 'x' => 100, 'y' => 200],
        ]));

        $this->assertTrue($actionResult['success']);
        $this->assertEquals('Action processed', $actionResult['message']);
    }

    public function testUnknownMessage(): void
    {
        $result = $this->roomActor->receive(new Message('unknown.message', []));

        $this->assertFalse($result['success']);
        $this->assertEquals('Unknown message type', $result['message']);
    }

    public function testRoomConfiguration(): void
    {
        // 创建自定义配置的房间
        $customRoom = new RoomActor('room_custom', '/user/custom_room', $this->context, [
            'max_players' => 8,
            'min_players' => 4,
            'game_type' => 'tournament',
        ]);

        $infoResult = $customRoom->receive(new Message('room.get_info', []));
        $roomInfo = $infoResult['data'];

        $this->assertEquals(8, $roomInfo['max_players']);
        $this->assertEquals(4, $roomInfo['min_players']);
    }
} 