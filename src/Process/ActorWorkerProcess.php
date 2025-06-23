<?php
declare(strict_types=1);

namespace HPlus\Actor\Process;

use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Router\MessageRouter;
use HPlus\Actor\Mailbox\MailboxFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Actor工作进程
 * 负责处理Actor消息
 */
#[Process(name: "actor-worker")]
class ActorWorkerProcess extends AbstractProcess
{
    public string $name = 'actor-worker';
    public int $nums = 1;
    public bool $redirectStdinStdout = false;
    public int $pipeType = 2;
    public bool $enableCoroutine = true;

    private LoggerInterface $logger;
    private ActorRegistry $registry;
    private MessageRouter $router;
    private MailboxFactory $mailboxFactory;
    private ConfigInterface $config;
    private int $workerId;
    private bool $running = false;

    public function __construct(ContainerInterface $container, int $workerId = 0)
    {
        parent::__construct($container);
        $this->workerId = $workerId;
        $this->name = "actor-worker-{$workerId}";
        $this->logger = $container->get(LoggerFactory::class)->get('actor');
        $this->registry = $container->get(ActorRegistry::class);
        $this->router = $container->get(MessageRouter::class);
        $this->mailboxFactory = $container->get(MailboxFactory::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function handle(): void
    {
        $this->running = true;
        $this->logger->info("Actor worker process {$this->workerId} started");

        // 启动消息处理循环
        Coroutine::create(function () {
            $this->messageProcessingLoop();
        });

        // 启动健康检查
        Coroutine::create(function () {
            $this->healthCheckLoop();
        });

        // 保持进程运行
        while ($this->running) {
            Coroutine::sleep(1);
        }

        $this->logger->info("Actor worker process {$this->workerId} stopped");
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 消息处理循环
     */
    private function messageProcessingLoop(): void
    {
        while ($this->running) {
            try {
                $processed = $this->processMessages();
                
                // 如果没有处理任何消息，短暂休眠避免CPU占用过高
                if ($processed === 0) {
                    Coroutine::sleep(0.01);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error in message processing loop: " . $e->getMessage());
                Coroutine::sleep(0.1);
            }
        }
    }

    /**
     * 处理消息
     */
    private function processMessages(): int
    {
        $processed = 0;
        $batchSize = $this->config->get('actor.batch_size', 100);
        
        $mailboxes = $this->mailboxFactory->getAllMailboxes();
        
        foreach ($mailboxes as $actorPath => $mailbox) {
            $batchCount = 0;
            
            while (!$mailbox->isEmpty() && $batchCount < $batchSize) {
                $message = $mailbox->dequeue();
                if ($message) {
                    $this->processMessage($actorPath, $message);
                    $processed++;
                    $batchCount++;
                }
            }
        }
        
        return $processed;
    }

    /**
     * 处理单个消息
     */
    private function processMessage(string $actorPath, $message): void
    {
        try {
            $actor = $this->registry->get($actorPath);
            if ($actor) {
                $result = $actor->receive($message);
                
                // 如果消息需要回复
                if ($message->needsReply() && $message->getReplyTo()) {
                    $this->router->reply($message->getReplyTo(), $result);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error processing message for {$actorPath}: " . $e->getMessage());
            
            // 尝试重启Actor
            $this->registry->restart($actorPath, $e);
        }
    }

    /**
     * 健康检查循环
     */
    private function healthCheckLoop(): void
    {
        $interval = $this->config->get('actor.health_check_interval', 30);
        
        while ($this->running) {
            try {
                $this->performHealthCheck();
                Coroutine::sleep($interval);
            } catch (\Throwable $e) {
                $this->logger->error("Error in health check: " . $e->getMessage());
                Coroutine::sleep(5);
            }
        }
    }

    /**
     * 执行健康检查
     */
    private function performHealthCheck(): void
    {
        $memoryUsage = memory_get_usage(true);
        $maxMemory = $this->config->get('actor.max_memory', 512 * 1024 * 1024); // 512MB
        
        if ($memoryUsage > $maxMemory) {
            $this->logger->warning("Worker {$this->workerId} memory usage high: " . 
                round($memoryUsage / 1024 / 1024, 2) . 'MB');
        }
        
        $this->logger->debug("Worker {$this->workerId} health check: " . 
            round($memoryUsage / 1024 / 1024, 2) . 'MB memory used');
    }
} 