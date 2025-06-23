<?php
declare(strict_types=1);

namespace HPlus\Actor\Listener;

use HPlus\Actor\System\ActorSystem;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Actor系统启动监听器
 * 在应用启动时初始化Actor系统
 */
class ActorSystemBootListener implements ListenerInterface
{
    private ContainerInterface $container;
    private LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('actor');
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof BootApplication) {
            $this->initializeActorSystem();
        }
    }

    /**
     * 初始化Actor系统
     */
    private function initializeActorSystem(): void
    {
        try {
            $this->logger->info('Initializing Actor System...');
            
            // 获取Actor系统实例
            $actorSystem = $this->container->get(ActorSystem::class);
            
            // 这里可以预创建一些系统级Actor
            $this->createSystemActors($actorSystem);
            
            $this->logger->info('Actor System initialized successfully');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to initialize Actor System: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 创建系统级Actor
     */
    private function createSystemActors(ActorSystem $actorSystem): void
    {
        // 这里可以创建一些系统级别的Actor
        // 例如：房间管理器、玩家管理器等
        
        // 示例：创建一个系统管理Actor
        // $actorSystem->actorOf(SystemManagerActor::class, 'system-manager');
        
        $this->logger->debug('System actors created');
    }
} 