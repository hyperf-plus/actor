<?php
declare(strict_types=1);

namespace HPlus\Actor\Message;

/**
 * 标准消息实现
 */
class Message implements MessageInterface
{
    private string $id;
    private string $type;
    private array $payload;
    private ?string $sender;
    private string $receiver;
    private int $timestamp;
    private int $priority;
    private bool $needsReply;
    private ?string $replyTo;

    public function __construct(
        string $type,
        array $payload = [],
        string $receiver = '',
        ?string $sender = null,
        int $priority = 0,
        bool $needsReply = false,
        ?string $replyTo = null
    ) {
        $this->id = $this->generateId();
        $this->type = $type;
        $this->payload = $payload;
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->timestamp = time();
        $this->priority = $priority;
        $this->needsReply = $needsReply;
        $this->replyTo = $replyTo;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function getReceiver(): string
    {
        return $this->receiver;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function needsReply(): bool
    {
        return $this->needsReply;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->payload,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'timestamp' => $this->timestamp,
            'priority' => $this->priority,
            'needsReply' => $this->needsReply,
            'replyTo' => $this->replyTo,
        ];
    }

    private function generateId(): string
    {
        return uniqid('msg_', true);
    }
} 