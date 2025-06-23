<?php
declare(strict_types=1);

namespace HPlus\Actor\Message;

/**
 * 消息接口
 * 定义Actor间传递的消息格式
 */
interface MessageInterface
{
    /**
     * 获取消息ID
     */
    public function getId(): string;

    /**
     * 获取消息类型
     */
    public function getType(): string;

    /**
     * 获取消息内容
     */
    public function getPayload(): array;

    /**
     * 获取发送者
     */
    public function getSender(): ?string;

    /**
     * 获取接收者
     */
    public function getReceiver(): string;

    /**
     * 获取消息时间戳
     */
    public function getTimestamp(): int;

    /**
     * 获取消息优先级
     */
    public function getPriority(): int;

    /**
     * 是否需要回复
     */
    public function needsReply(): bool;

    /**
     * 获取回复地址
     */
    public function getReplyTo(): ?string;

    /**
     * 转换为数组
     */
    public function toArray(): array;
} 