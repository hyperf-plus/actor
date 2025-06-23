<?php
declare(strict_types=1);

namespace HPlus\Actor;

use HPlus\Actor\Contract\ActorInterface;
use HPlus\Actor\Message\MessageInterface;
use HPlus\Actor\System\ActorContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Actor抽象基类
 * 提供Actor的基础功能实现
 */
abstract class AbstractActor implements ActorInterface
{
    protected string $id;
    protected string $path;
    protected array $state = [];
    protected ActorContext $context;
    protected LoggerInterface $logger;

    public function __construct(string $id, string $path, ActorContext $context)
    {
        $this->id = $id;
        $this->path = $path;
        $this->context = $context;
        $this->logger = $context->getContainer()->get(LoggerFactory::class)->get('actor');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function setState(array $state): void
    {
        $this->state = $state;
    }

    public function preStart(): void
    {
        $this->logger->info("Actor {$this->path} starting");
    }

    public function postStop(): void
    {
        $this->logger->info("Actor {$this->path} stopped");
    }

    public function preRestart(\Throwable $reason): void
    {
        $this->logger->warning("Actor {$this->path} restarting due to: " . $reason->getMessage());
    }

    public function postRestart(\Throwable $reason): void
    {
        $this->logger->info("Actor {$this->path} restarted");
    }

    /**
     * 发送消息到其他Actor
     */
    protected function tell(string $actorPath, MessageInterface $message): void
    {
        $this->context->tell($actorPath, $message);
    }

    /**
     * 发送消息并等待回复
     */
    protected function ask(string $actorPath, MessageInterface $message, int $timeout = 5): mixed
    {
        return $this->context->ask($actorPath, $message, $timeout);
    }

    /**
     * 获取Actor上下文
     */
    protected function getContext(): ActorContext
    {
        return $this->context;
    }

    /**
     * 子类需要实现具体的消息处理逻辑
     */
    abstract public function receive(MessageInterface $message): mixed;
} 