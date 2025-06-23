<?php
declare(strict_types=1);

namespace HPlus\Actor\Game;

use HPlus\Actor\AbstractActor;
use HPlus\Actor\Message\MessageInterface;
use HPlus\Actor\Message\Message;
use HPlus\Actor\System\ActorContext;

/**
 * 房间Actor
 * 管理游戏房间和玩家
 */
class RoomActor extends AbstractActor
{
    private array $roomConfig = [];
    private array $players = []; // player_id => player_data
    private array $playerPaths = []; // player_id => actor_path
    private string $roomStatus = 'waiting'; // waiting, playing, closed
    private array $gameState = [];
    private int $maxPlayers = 4;
    private int $minPlayers = 2;

    public function __construct(string $id, string $path, ActorContext $context, array $config = [])
    {
        parent::__construct($id, $path, $context);
        $this->roomConfig = $config;
        $this->maxPlayers = $config['max_players'] ?? 4;
        $this->minPlayers = $config['min_players'] ?? 2;
    }

    public function receive(MessageInterface $message): mixed
    {
        $type = $message->getType();
        $payload = $message->getPayload();

        return match ($type) {
            'room.join_request' => $this->handleJoinRequest($payload),
            'room.leave_request' => $this->handleLeaveRequest($payload),
            'room.start_game' => $this->handleStartGame($payload),
            'room.end_game' => $this->handleEndGame($payload),
            'room.get_info' => $this->handleGetInfo($payload),
            'room.broadcast' => $this->handleBroadcast($payload),
            'game.player_action' => $this->handlePlayerAction($payload),
            'game.update_state' => $this->handleUpdateGameState($payload),
            default => $this->handleUnknownMessage($message),
        };
    }

    private function handleJoinRequest(array $payload): array
    {
        $playerId = $payload['player_id'] ?? '';
        $playerPath = $payload['player_path'] ?? '';
        $playerData = $payload['player_data'] ?? [];

        if (!$playerId || !$playerPath) {
            return ['success' => false, 'message' => 'Invalid player data'];
        }

        if ($this->roomStatus === 'closed') {
            return ['success' => false, 'message' => 'Room is closed'];
        }

        if (count($this->players) >= $this->maxPlayers) {
            return ['success' => false, 'message' => 'Room is full'];
        }

        // 添加玩家
        $this->players[$playerId] = $playerData;
        $this->playerPaths[$playerId] = $playerPath;

        $this->logger->info("Player {$playerId} joined room {$this->getPath()}");

        return [
            'success' => true,
            'message' => 'Joined room successfully',
            'room_info' => $this->getRoomInfo(),
        ];
    }

    private function handleLeaveRequest(array $payload): array
    {
        $playerId = $payload['player_id'] ?? '';

        if (!isset($this->players[$playerId])) {
            return ['success' => false, 'message' => 'Player not in room'];
        }

        // 移除玩家
        unset($this->players[$playerId]);
        unset($this->playerPaths[$playerId]);

        return ['success' => true, 'message' => 'Left room successfully'];
    }

    private function handleStartGame(array $payload): array
    {
        if (count($this->players) < $this->minPlayers) {
            return ['success' => false, 'message' => 'Not enough players'];
        }

        $this->startGame($payload);
        return ['success' => true, 'message' => 'Game started'];
    }

    private function handleEndGame(array $payload): array
    {
        $this->endGame($payload['reason'] ?? 'Game ended');
        return ['success' => true, 'message' => 'Game ended'];
    }

    private function handleGetInfo(array $payload): array
    {
        return ['success' => true, 'data' => $this->getRoomInfo()];
    }

    private function handleBroadcast(array $payload): array
    {
        $this->broadcastToPlayers('room.message', $payload);
        return ['success' => true, 'message' => 'Message broadcasted'];
    }

    private function handlePlayerAction(array $payload): array
    {
        // 处理玩家游戏动作
        return ['success' => true, 'message' => 'Action processed'];
    }

    private function handleUpdateGameState(array $payload): array
    {
        $this->gameState = array_merge($this->gameState, $payload);
        return ['success' => true, 'message' => 'Game state updated'];
    }

    private function handleUnknownMessage(MessageInterface $message): array
    {
        return ['success' => false, 'message' => 'Unknown message type'];
    }

    private function startGame(array $config = []): void
    {
        $this->roomStatus = 'playing';
        $this->gameState = [
            'started_at' => time(),
            'players' => array_keys($this->players),
            'config' => $config,
        ];

        $this->broadcastToPlayers('game.start', [
            'game_state' => $this->gameState,
        ]);
    }

    private function endGame(string $reason = ''): void
    {
        $this->roomStatus = 'waiting';
        $this->broadcastToPlayers('game.end', [
            'reason' => $reason,
        ]);
        $this->gameState = [];
    }

    private function broadcastToPlayers(string $messageType, array $payload): void
    {
        foreach ($this->playerPaths as $playerId => $playerPath) {
            try {
                $this->tell($playerPath, new Message($messageType, $payload));
            } catch (\Throwable $e) {
                $this->logger->error("Failed to send message to player {$playerId}: " . $e->getMessage());
            }
        }
    }

    private function getRoomInfo(): array
    {
        return [
            'room_id' => $this->getId(),
            'room_path' => $this->getPath(),
            'status' => $this->roomStatus,
            'players' => $this->players,
            'player_count' => count($this->players),
            'max_players' => $this->maxPlayers,
            'min_players' => $this->minPlayers,
        ];
    }
} 