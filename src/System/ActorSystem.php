<?php
declare(strict_types=1);

namespace HPlus\Actor\System;

use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Router\MessageRouter;
use HPlus\Actor\Mailbox\MailboxFactory;
use HPlus\Actor\Process\ActorWorkerProcess;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\ProcessManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Actor系统
 * 统一管理Actor系统的所有组件
 */
class ActorSystem
{
    private ContainerInterface $container;
    private ConfigInterface $config;
    private LoggerInterface $logger;
    private ActorRegistry $registry;
    private MessageRouter $router;
    private MailboxFactory $mailboxFactory;
    private ActorContext $context;
    private bool $started = false;
    private array $workerProcesses = [];

    public function __construct(
        ContainerInterface $container,
        ConfigInterface $config,
        ActorRegistry $registry,
        MessageRouter $router,
        MailboxFactory $mailboxFactory
    ) {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $container->get(LoggerFactory::class)->get('actor');
        $this->registry = $registry;
        $this->router = $router;
        $this->mailboxFactory = $mailboxFactory;
        $this->context = new ActorContext($container, $registry, $router);
    }

    /**
     * 启动Actor系统
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->logger->info('Starting Actor System...');

        // 启动工作进程
        $this->startWorkerProcesses();

        $this->started = true;
        $this->logger->info('Actor System started successfully');
    }

    /**
     * 停止Actor系统
     */
    public function stop(): void
    {
        if (!$this->started) {
            return;
        }

        $this->logger->info('Stopping Actor System...');

        // 停止所有Actor
        $this->stopAllActors();

        // 停止工作进程
        $this->stopWorkerProcesses();

        $this->started = false;
        $this->logger->info('Actor System stopped');
    }

    /**
     * 创建Actor
     */
    public function actorOf(string $actorClass, string $name, array $args = []): string
    {
        return $this->registry->create($actorClass, $name, $args);
    }

    /**
     * 获取Actor注册表
     */
    public function getRegistry(): ActorRegistry
    {
        return $this->registry;
    }

    /**
     * 获取消息路由器
     */
    public function getRouter(): MessageRouter
    {
        return $this->router;
    }

    /**
     * 获取邮箱工厂
     */
    public function getMailboxFactory(): MailboxFactory
    {
        return $this->mailboxFactory;
    }

    /**
     * 获取Actor上下文
     */
    public function getContext(): ActorContext
    {
        return $this->context;
    }

    /**
     * 获取系统状态
     */
    public function getStatus(): array
    {
        return [
            'started' => $this->started,
            'actors' => $this->registry->count(),
            'worker_processes' => count($this->workerProcesses),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 启动工作进程
     */
    private function startWorkerProcesses(): void
    {
        $workerCount = $this->config->get('actor.worker_processes', 4);
        
        for ($i = 0; $i < $workerCount; $i++) {
            $process = new ActorWorkerProcess($this->container, $i);
            $this->workerProcesses[] = $process;
            ProcessManager::register($process);
        }
    }

    /**
     * 停止工作进程
     */
    private function stopWorkerProcesses(): void
    {
        foreach ($this->workerProcesses as $process) {
            if ($process instanceof ActorWorkerProcess) {
                $process->stop();
            }
        }
        $this->workerProcesses = [];
    }

    /**
     * 停止所有Actor
     */
    private function stopAllActors(): void
    {
        $actors = $this->registry->getAll();
        foreach ($actors as $actor) {
            $this->registry->stop($actor->getPath());
        }
    }
} 