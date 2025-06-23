<?php
declare(strict_types=1);

namespace HPlus\Actor\Mailbox;

use HPlus\Actor\Message\MessageInterface;
use Swoole\Coroutine\Channel;

/**
 * 标准邮箱实现
 * 基于Swoole协程Channel实现
 */
class Mailbox implements MailboxInterface
{
    private Channel $channel;
    private int $capacity;

    public function __construct(int $capacity = 1000)
    {
        $this->capacity = $capacity;
        $this->channel = new Channel($capacity);
    }

    public function enqueue(MessageInterface $message): void
    {
        if (!$this->channel->push($message, 0.1)) {
            throw new \RuntimeException('Mailbox is full or closed');
        }
    }

    public function dequeue(): ?MessageInterface
    {
        $message = $this->channel->pop(0.1);
        return $message instanceof MessageInterface ? $message : null;
    }

    public function isEmpty(): bool
    {
        return $this->channel->isEmpty();
    }

    public function size(): int
    {
        return $this->channel->length();
    }

    public function clear(): void
    {
        while (!$this->isEmpty()) {
            $this->dequeue();
        }
    }

    public function close(): void
    {
        $this->channel->close();
    }
} 