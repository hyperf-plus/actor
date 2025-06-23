<?php
declare(strict_types=1);

namespace HPlus\Actor\Examples;

use HPlus\Actor\Game\PlayerActor;
use HPlus\Actor\Game\RoomActor;
use HPlus\Actor\Message\Message;
use HPlus\Actor\System\ActorSystem;
use Psr\Container\ContainerInterface;

/**
 * 游戏使用示例
 * 演示如何使用Actor系统构建游戏
 */
class GameExample
{
    private ContainerInterface $container;
    private ActorSystem $actorSystem;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->actorSystem = $container->get(ActorSystem::class);
    }

    /**
     * 运行游戏示例
     */
    public function run(): void
    {
        echo "🎮 启动游戏示例...\n";

        // 1. 创建游戏房间
        $roomPath = $this->createGameRoom();
        echo "🏠 创建游戏房间：{$roomPath}\n";

        // 2. 创建玩家
        $playerPaths = $this->createPlayers();
        echo "👥 创建玩家：" . implode(', ', $playerPaths) . "\n";

        // 3. 玩家登录
        $this->loginPlayers($playerPaths);
        echo "🔐 玩家登录完成\n";

        // 4. 玩家加入房间
        $this->joinRoom($playerPaths, $roomPath);
        echo "🚪 玩家加入房间完成\n";

        // 5. 开始游戏
        $this->startGame($roomPath);
        echo "🎯 游戏开始\n";

        // 6. 模拟游戏动作
        $this->simulateGameActions($playerPaths);
        echo "🎮 模拟游戏动作\n";

        // 7. 结束游戏
        $this->endGame($roomPath);
        echo "🏁 游戏结束\n";

        echo "✅ 游戏示例完成！\n";
    }

    /**
     * 创建游戏房间
     */
    private function createGameRoom(): string
    {
        return $this->actorSystem->actorOf(RoomActor::class, 'game-room-1', [
            'max_players' => 4,
            'min_players' => 2,
            'auto_start' => false,
            'game_type' => 'example',
        ]);
    }

    /**
     * 创建玩家
     */
    private function createPlayers(): array
    {
        $players = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $playerPath = $this->actorSystem->actorOf(PlayerActor::class, "player-{$i}");
            $players[] = $playerPath;
        }
        
        return $players;
    }

    /**
     * 玩家登录
     */
    private function loginPlayers(array $playerPaths): void
    {
        foreach ($playerPaths as $index => $playerPath) {
            $playerId = $index + 1;
            
            $loginMessage = new Message('player.login', [
                'username' => "player{$playerId}",
                'level' => rand(1, 50),
                'score' => rand(0, 1000),
            ]);
            
            $this->sendMessage($playerPath, $loginMessage);
        }
    }

    /**
     * 玩家加入房间
     */
    private function joinRoom(array $playerPaths, string $roomPath): void
    {
        foreach ($playerPaths as $playerPath) {
            $joinMessage = new Message('player.join_room', [
                'room_path' => $roomPath,
            ]);
            
            $this->sendMessage($playerPath, $joinMessage);
        }
    }

    /**
     * 开始游戏
     */
    private function startGame(string $roomPath): void
    {
        $startMessage = new Message('room.start_game', [
            'game_mode' => 'classic',
            'duration' => 300, // 5分钟
        ]);
        
        $this->sendMessage($roomPath, $startMessage);
    }

    /**
     * 模拟游戏动作
     */
    private function simulateGameActions(array $playerPaths): void
    {
        $actions = [
            'move' => ['x' => 100, 'y' => 200],
            'attack' => ['target' => 'enemy1', 'damage' => 50],
            'use_skill' => ['skill_id' => 'fireball', 'target' => 'enemy2'],
            'collect_item' => ['item_id' => 'potion', 'quantity' => 1],
        ];

        foreach ($playerPaths as $playerPath) {
            $actionType = array_rand($actions);
            $actionData = $actions[$actionType];
            
            $actionMessage = new Message('player.game_action', [
                'action_type' => $actionType,
                'action_data' => $actionData,
                'timestamp' => time(),
            ]);
            
            $this->sendMessage($playerPath, $actionMessage);
        }
    }

    /**
     * 结束游戏
     */
    private function endGame(string $roomPath): void
    {
        $endMessage = new Message('room.end_game', [
            'reason' => 'Game completed',
            'winner' => 'player-1',
            'scores' => [
                'player-1' => 100,
                'player-2' => 80,
                'player-3' => 60,
            ],
        ]);
        
        $this->sendMessage($roomPath, $endMessage);
    }

    /**
     * 发送消息
     */
    private function sendMessage(string $actorPath, Message $message): void
    {
        try {
            $router = $this->actorSystem->getRouter();
            $message = new Message(
                $message->getType(),
                $message->getPayload(),
                $actorPath,
                'system'
            );
            $router->route($message);
        } catch (\Throwable $e) {
            echo "❌ 发送消息失败：{$e->getMessage()}\n";
        }
    }

    /**
     * 获取Actor状态
     */
    public function getActorStatus(string $actorPath): array
    {
        $registry = $this->actorSystem->getRegistry();
        $actor = $registry->get($actorPath);
        
        if (!$actor) {
            return ['error' => 'Actor not found'];
        }

        return [
            'id' => $actor->getId(),
            'path' => $actor->getPath(),
            'state' => $actor->getState(),
            'class' => get_class($actor),
        ];
    }

    /**
     * 显示系统统计
     */
    public function showStats(): void
    {
        $status = $this->actorSystem->getStatus();
        
        echo "\n📊 Actor系统统计:\n";
        echo "状态: " . ($status['started'] ? "运行中" : "已停止") . "\n";
        echo "Actor数量: {$status['actors']}\n";
        echo "工作进程: {$status['worker_processes']}\n";
        echo "内存使用: " . round($status['memory_usage'] / 1024 / 1024, 2) . " MB\n";
        echo "峰值内存: " . round($status['peak_memory'] / 1024 / 1024, 2) . " MB\n";
    }
} 