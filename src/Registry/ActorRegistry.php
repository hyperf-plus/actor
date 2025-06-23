<?php
declare(strict_types=1);

namespace HPlus\Actor\Registry;

use HPlus\Actor\Contract\ActorInterface;
use HPlus\Actor\System\ActorContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Actor注册表
 * 管理Actor的生命周期
 */
class ActorRegistry
{
    private ContainerInterface $container;
    private LoggerInterface $logger;
    private array $actors = [];
    private array $actorPaths = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('actor');
    }

    /**
     * 创建Actor
     */
    public function create(string $actorClass, string $name, array $args = []): string
    {
        $id = $this->generateId();
        $path = "/user/{$name}";
        
        // 检查路径是否已存在
        if (isset($this->actorPaths[$path])) {
            throw new \RuntimeException("Actor path {$path} already exists");
        }

        try {
            // 创建Actor上下文
            $context = $this->container->get(ActorContext::class);
            
            // 实例化Actor
            $actor = new $actorClass($id, $path, $context, ...$args);
            
            if (!$actor instanceof ActorInterface) {
                throw new \InvalidArgumentException("Actor must implement ActorInterface");
            }

            // 注册Actor
            $this->actors[$id] = $actor;
            $this->actorPaths[$path] = $id;

            // 启动Actor
            $actor->preStart();

            $this->logger->info("Actor created: {$path} (ID: {$id})");

            return $path;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to create actor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取Actor
     */
    public function get(string $path): ?ActorInterface
    {
        $id = $this->actorPaths[$path] ?? null;
        return $id ? ($this->actors[$id] ?? null) : null;
    }

    /**
     * 停止Actor
     */
    public function stop(string $path): void
    {
        $actor = $this->get($path);
        if ($actor) {
            $actor->postStop();
            
            $id = $this->actorPaths[$path];
            unset($this->actors[$id]);
            unset($this->actorPaths[$path]);
            
            $this->logger->info("Actor stopped: {$path}");
        }
    }

    /**
     * 重启Actor
     */
    public function restart(string $path, \Throwable $reason): void
    {
        $actor = $this->get($path);
        if ($actor) {
            $actor->preRestart($reason);
            $actor->postRestart($reason);
            
            $this->logger->info("Actor restarted: {$path}");
        }
    }

    /**
     * 获取所有Actor
     */
    public function getAll(): array
    {
        return $this->actors;
    }

    /**
     * 获取Actor数量
     */
    public function count(): int
    {
        return count($this->actors);
    }

    /**
     * 生成唯一ID
     */
    private function generateId(): string
    {
        return uniqid('actor_', true);
    }
} 