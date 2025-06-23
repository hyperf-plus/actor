<?php
declare(strict_types=1);

namespace HPlus\Actor\System;

use HPlus\Actor\Message\MessageInterface;
use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Router\MessageRouter;
use Psr\Container\ContainerInterface;

/**
 * Actor上下文
 * 提供Actor运行时环境和服务
 */
class ActorContext
{
    private ContainerInterface $container;
    private ActorRegistry $registry;
    private MessageRouter $router;

    public function __construct(
        ContainerInterface $container,
        ActorRegistry $registry,
        MessageRouter $router
    ) {
        $this->container = $container;
        $this->registry = $registry;
        $this->router = $router;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getRegistry(): ActorRegistry
    {
        return $this->registry;
    }

    public function getRouter(): MessageRouter
    {
        return $this->router;
    }

    /**
     * 发送消息
     */
    public function tell(string $actorPath, MessageInterface $message): void
    {
        $this->router->route($message);
    }

    /**
     * 发送消息并等待回复
     */
    public function ask(string $actorPath, MessageInterface $message, int $timeout = 5): mixed
    {
        return $this->router->ask($actorPath, $message, $timeout);
    }

    /**
     * 创建子Actor
     */
    public function actorOf(string $actorClass, string $name, array $args = []): string
    {
        return $this->registry->create($actorClass, $name, $args);
    }

    /**
     * 停止Actor
     */
    public function stop(string $actorPath): void
    {
        $this->registry->stop($actorPath);
    }
} 