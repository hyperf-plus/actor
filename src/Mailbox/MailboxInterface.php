<?php
declare(strict_types=1);

namespace HPlus\Actor\Mailbox;

use HPlus\Actor\Message\MessageInterface;

/**
 * 邮箱接口
 * 用于Actor消息队列管理
 */
interface MailboxInterface
{
    /**
     * 将消息放入队列
     */
    public function enqueue(MessageInterface $message): void;

    /**
     * 从队列中取出消息
     */
    public function dequeue(): ?MessageInterface;

    /**
     * 检查队列是否为空
     */
    public function isEmpty(): bool;

    /**
     * 获取队列长度
     */
    public function size(): int;

    /**
     * 清空队列
     */
    public function clear(): void;
} 