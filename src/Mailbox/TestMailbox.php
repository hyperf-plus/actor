<?php
declare(strict_types=1);

namespace HPlus\Actor\Mailbox;

use HPlus\Actor\Message\MessageInterface;

/**
 * 测试专用邮箱实现
 * 使用普通数组而不是Swoole Channel，适用于测试环境
 */
class TestMailbox implements MailboxInterface
{
    private array $messages = [];
    private int $capacity;

    public function __construct(int $capacity = 1000)
    {
        $this->capacity = $capacity;
    }

    public function enqueue(MessageInterface $message): void
    {
        if (count($this->messages) >= $this->capacity) {
            throw new \RuntimeException('Mailbox is full');
        }
        $this->messages[] = $message;
    }

    public function dequeue(): ?MessageInterface
    {
        if (empty($this->messages)) {
            return null;
        }
        return array_shift($this->messages);
    }

    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    public function size(): int
    {
        return count($this->messages);
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    public function close(): void
    {
        $this->clear();
    }
} 