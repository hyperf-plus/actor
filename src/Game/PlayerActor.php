<?php
declare(strict_types=1);

namespace HPlus\Actor\Game;

use HPlus\Actor\AbstractActor;
use HPlus\Actor\Message\MessageInterface;
use HPlus\Actor\Message\Message;

/**
 * 玩家Actor
 * 处理单个玩家的游戏逻辑
 */
class PlayerActor extends AbstractActor
{
    private array $playerData = [];
    private string $roomPath = '';
    private string $status = 'online'; // online, offline, playing, waiting

    public function receive(MessageInterface $message): mixed
    {
        $type = $message->getType();
        $payload = $message->getPayload();

        return match ($type) {
            'player.login' => $this->handleLogin($payload),
            'player.logout' => $this->handleLogout($payload),
            'player.join_room' => $this->handleJoinRoom($payload),
            'player.leave_room' => $this->handleLeaveRoom($payload),
            'player.game_action' => $this->handleGameAction($payload),
            'player.get_info' => $this->handleGetInfo($payload),
            'player.update_info' => $this->handleUpdateInfo($payload),
            'room.message' => $this->handleRoomMessage($payload),
            'game.start' => $this->handleGameStart($payload),
            'game.end' => $this->handleGameEnd($payload),
            default => $this->handleUnknownMessage($message),
        };
    }

    /**
     * 处理玩家登录
     */
    private function handleLogin(array $payload): array
    {
        $this->playerData = array_merge($this->playerData, $payload);
        $this->status = 'online';
        
        $this->logger->info("Player {$this->getId()} logged in");
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'player_id' => $this->getId(),
            'status' => $this->status,
        ];
    }

    /**
     * 处理玩家登出
     */
    private function handleLogout(array $payload): array
    {
        // 如果在房间中，先离开房间
        if ($this->roomPath) {
            $this->leaveCurrentRoom();
        }

        $this->status = 'offline';
        $this->logger->info("Player {$this->getId()} logged out");
        
        return [
            'success' => true,
            'message' => 'Logout successful',
        ];
    }

    /**
     * 处理加入房间
     */
    private function handleJoinRoom(array $payload): array
    {
        $roomPath = $payload['room_path'] ?? '';
        
        if (!$roomPath) {
            return ['success' => false, 'message' => 'Room path required'];
        }

        // 如果已在其他房间，先离开
        if ($this->roomPath && $this->roomPath !== $roomPath) {
            $this->leaveCurrentRoom();
        }

        try {
            // 通知房间Actor加入请求
            $response = $this->ask($roomPath, new Message(
                'room.join_request',
                [
                    'player_id' => $this->getId(),
                    'player_path' => $this->getPath(),
                    'player_data' => $this->playerData,
                ]
            ));

            if ($response['success'] ?? false) {
                $this->roomPath = $roomPath;
                $this->status = 'waiting';
                
                return [
                    'success' => true,
                    'message' => 'Joined room successfully',
                    'room_path' => $roomPath,
                ];
            }

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to join room: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to join room'];
        }
    }

    /**
     * 处理离开房间
     */
    private function handleLeaveRoom(array $payload): array
    {
        if (!$this->roomPath) {
            return ['success' => false, 'message' => 'Not in any room'];
        }

        $this->leaveCurrentRoom();
        
        return [
            'success' => true,
            'message' => 'Left room successfully',
        ];
    }

    /**
     * 处理游戏动作
     */
    private function handleGameAction(array $payload): array
    {
        if (!$this->roomPath) {
            return ['success' => false, 'message' => 'Not in any room'];
        }

        if ($this->status !== 'playing') {
            return ['success' => false, 'message' => 'Game not started'];
        }

        try {
            // 转发游戏动作到房间
            $this->tell($this->roomPath, new Message(
                'game.player_action',
                [
                    'player_id' => $this->getId(),
                    'player_path' => $this->getPath(),
                    'action' => $payload,
                ]
            ));

            return ['success' => true, 'message' => 'Action sent'];
        } catch (\Throwable $e) {
            $this->logger->error("Failed to send game action: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send action'];
        }
    }

    /**
     * 处理获取玩家信息
     */
    private function handleGetInfo(array $payload): array
    {
        return [
            'success' => true,
            'data' => [
                'player_id' => $this->getId(),
                'player_path' => $this->getPath(),
                'status' => $this->status,
                'room_path' => $this->roomPath,
                'player_data' => $this->playerData,
            ],
        ];
    }

    /**
     * 处理更新玩家信息
     */
    private function handleUpdateInfo(array $payload): array
    {
        $this->playerData = array_merge($this->playerData, $payload);
        
        return [
            'success' => true,
            'message' => 'Player info updated',
            'player_data' => $this->playerData,
        ];
    }

    /**
     * 处理房间消息
     */
    private function handleRoomMessage(array $payload): array
    {
        // 这里可以处理来自房间的各种消息
        $this->logger->debug("Received room message: " . json_encode($payload));
        
        return ['success' => true, 'message' => 'Message received'];
    }

    /**
     * 处理游戏开始
     */
    private function handleGameStart(array $payload): array
    {
        $this->status = 'playing';
        $this->logger->info("Player {$this->getId()} game started");
        
        return [
            'success' => true,
            'message' => 'Game started',
            'game_data' => $payload,
        ];
    }

    /**
     * 处理游戏结束
     */
    private function handleGameEnd(array $payload): array
    {
        $this->status = 'waiting';
        $this->logger->info("Player {$this->getId()} game ended");
        
        return [
            'success' => true,
            'message' => 'Game ended',
            'result' => $payload,
        ];
    }

    /**
     * 处理未知消息
     */
    private function handleUnknownMessage(MessageInterface $message): array
    {
        $this->logger->warning("Unknown message type: " . $message->getType());
        
        return [
            'success' => false,
            'message' => 'Unknown message type: ' . $message->getType(),
        ];
    }

    /**
     * 离开当前房间
     */
    private function leaveCurrentRoom(): void
    {
        if ($this->roomPath) {
            try {
                $this->tell($this->roomPath, new Message(
                    'room.leave_request',
                    [
                        'player_id' => $this->getId(),
                        'player_path' => $this->getPath(),
                    ]
                ));
                
                $this->roomPath = '';
                $this->status = 'online';
            } catch (\Throwable $e) {
                $this->logger->error("Failed to leave room: " . $e->getMessage());
            }
        }
    }

    public function preStart(): void
    {
        parent::preStart();
        $this->status = 'online';
    }

    public function postStop(): void
    {
        $this->leaveCurrentRoom();
        parent::postStop();
    }
} 