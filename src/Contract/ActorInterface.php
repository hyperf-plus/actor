<?php
declare(strict_types=1);

namespace HPlus\Actor\Contract;

use HPlus\Actor\Message\MessageInterface;

/**
 * Actor接口
 * 定义Actor的基本行为
 */
interface ActorInterface
{
    /**
     * 获取Actor唯一标识
     */
    public function getId(): string;

    /**
     * 获取Actor路径
     */
    public function getPath(): string;

    /**
     * 处理消息
     */
    public function receive(MessageInterface $message): mixed;

    /**
     * Actor启动时调用
     */
    public function preStart(): void;

    /**
     * Actor停止时调用
     */
    public function postStop(): void;

    /**
     * Actor重启时调用
     */
    public function preRestart(\Throwable $reason): void;

    /**
     * Actor重启后调用
     */
    public function postRestart(\Throwable $reason): void;

    /**
     * 获取Actor状态
     */
    public function getState(): array;

    /**
     * 设置Actor状态
     */
    public function setState(array $state): void;
} 