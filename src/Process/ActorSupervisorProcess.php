<?php
declare(strict_types=1);

namespace HPlus\Actor\Process;

use HPlus\Actor\System\ActorSystem;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Actor监督进程
 * 监督和管理Actor系统
 */
#[Process(name: "actor-supervisor")]
class ActorSupervisorProcess extends AbstractProcess
{
    public string $name = 'actor-supervisor';
    public int $nums = 1;
    public bool $redirectStdinStdout = false;
    public int $pipeType = 2;
    public bool $enableCoroutine = true;

    private LoggerInterface $logger;
    private ActorSystem $actorSystem;
    private ConfigInterface $config;
    private bool $running = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->logger = $container->get(LoggerFactory::class)->get('actor');
        $this->actorSystem = $container->get(ActorSystem::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function handle(): void
    {
        $this->running = true;
        $this->logger->info('Actor supervisor process started');

        // 启动Actor系统
        $this->actorSystem->start();

        // 启动监督循环
        Coroutine::create(function () {
            $this->supervisionLoop();
        });

        // 启动统计收集
        Coroutine::create(function () {
            $this->statsCollectionLoop();
        });

        // 保持进程运行
        while ($this->running) {
            Coroutine::sleep(1);
        }

        // 停止Actor系统
        $this->actorSystem->stop();
        $this->logger->info('Actor supervisor process stopped');
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 监督循环
     */
    private function supervisionLoop(): void
    {
        $interval = $this->config->get('actor.supervision_interval', 10);

        while ($this->running) {
            try {
                $this->performSupervision();
                Coroutine::sleep($interval);
            } catch (\Throwable $e) {
                $this->logger->error('Error in supervision loop: ' . $e->getMessage());
                Coroutine::sleep(1);
            }
        }
    }

    /**
     * 统计收集循环
     */
    private function statsCollectionLoop(): void
    {
        $interval = $this->config->get('actor.stats_interval', 60);

        while ($this->running) {
            try {
                $this->collectStats();
                Coroutine::sleep($interval);
            } catch (\Throwable $e) {
                $this->logger->error('Error in stats collection: ' . $e->getMessage());
                Coroutine::sleep(5);
            }
        }
    }

    /**
     * 执行监督检查
     */
    private function performSupervision(): void
    {
        $status = $this->actorSystem->getStatus();
        
        // 检查系统状态
        if (!$status['started']) {
            $this->logger->warning('Actor system is not started, attempting to restart');
            $this->actorSystem->start();
            return;
        }

        // 检查内存使用
        $memoryUsage = $status['memory_usage'];
        $maxMemory = $this->config->get('actor.max_system_memory', 1024 * 1024 * 1024); // 1GB
        
        if ($memoryUsage > $maxMemory) {
            $this->logger->warning('High memory usage detected: ' . 
                round($memoryUsage / 1024 / 1024, 2) . 'MB');
            
            // 可以在这里实现内存清理逻辑
            $this->performMemoryCleanup();
        }

        // 记录正常状态
        $this->logger->debug('Supervision check completed', $status);
    }

    /**
     * 收集统计信息
     */
    private function collectStats(): void
    {
        $status = $this->actorSystem->getStatus();
        
        $stats = [
            'timestamp' => time(),
            'actors_count' => $status['actors'],
            'worker_processes' => $status['worker_processes'],
            'memory_usage' => $status['memory_usage'],
            'peak_memory' => $status['peak_memory'],
            'system_load' => sys_getloadavg(),
        ];

        // 记录统计信息
        $this->logger->info('Actor system stats', $stats);

        // 可以在这里将统计信息发送到监控系统
        $this->sendStatsToMonitoring($stats);
    }

    /**
     * 执行内存清理
     */
    private function performMemoryCleanup(): void
    {
        // 强制垃圾回收
        gc_collect_cycles();
        
        $this->logger->info('Memory cleanup performed');
    }

    /**
     * 发送统计信息到监控系统
     */
    private function sendStatsToMonitoring(array $stats): void
    {
        // 这里可以实现具体的监控系统集成
        // 例如发送到 Prometheus、InfluxDB 等
    }
} 