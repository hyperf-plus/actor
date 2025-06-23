<?php
declare(strict_types=1);

namespace HPlus\Actor\Command;

use HPlus\Actor\System\ActorSystem;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Actor统计命令
 * 显示Actor系统的统计信息
 */
#[Command]
class ActorStatsCommand extends HyperfCommand
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('actor:stats');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Display Actor system statistics');
        $this->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch mode - continuously display stats');
        $this->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Update interval in seconds for watch mode', 5);
    }

    public function handle()
    {
        $actorSystem = $this->container->get(ActorSystem::class);
        $watch = $this->input->getOption('watch');
        $interval = (int) $this->input->getOption('interval');

        if ($watch) {
            $this->watchStats($actorSystem, $interval);
        } else {
            $this->displayStats($actorSystem);
        }
    }

    /**
     * 显示统计信息
     */
    private function displayStats(ActorSystem $actorSystem): void
    {
        $status = $actorSystem->getStatus();
        
        $this->line('<info>Actor System Statistics</info>');
        $this->line('========================');
        $this->line('Status: ' . ($status['started'] ? '<info>Running</info>' : '<error>Stopped</error>'));
        $this->line('Actors: <comment>' . $status['actors'] . '</comment>');
        $this->line('Worker Processes: <comment>' . $status['worker_processes'] . '</comment>');
        $this->line('Memory Usage: <comment>' . $this->formatBytes($status['memory_usage']) . '</comment>');
        $this->line('Peak Memory: <comment>' . $this->formatBytes($status['peak_memory']) . '</comment>');
        
        // 显示详细的Actor信息
        $this->displayActorDetails($actorSystem);
        
        // 显示邮箱统计
        $this->displayMailboxStats($actorSystem);
    }

    /**
     * 监视模式
     */
    private function watchStats(ActorSystem $actorSystem, int $interval): void
    {
        $this->info("Watching Actor system stats (updating every {$interval}s). Press Ctrl+C to stop.");
        
        while (true) {
            // 清屏
            system('clear');
            
            $this->displayStats($actorSystem);
            $this->line('');
            $this->line('Last updated: ' . date('Y-m-d H:i:s'));
            
            sleep($interval);
        }
    }

    /**
     * 显示Actor详细信息
     */
    private function displayActorDetails(ActorSystem $actorSystem): void
    {
        $registry = $actorSystem->getRegistry();
        $actors = $registry->getAll();
        
        if (empty($actors)) {
            $this->line('No actors running.');
            return;
        }

        $this->line('');
        $this->line('<info>Actor Details</info>');
        $this->line('==============');
        
        $table = $this->output->createTable();
        $table->setHeaders(['ID', 'Path', 'Class', 'State']);
        
        foreach ($actors as $actor) {
            $table->addRow([
                $actor->getId(),
                $actor->getPath(),
                get_class($actor),
                json_encode($actor->getState(), JSON_UNESCAPED_UNICODE),
            ]);
        }
        
        $table->render();
    }

    /**
     * 显示邮箱统计
     */
    private function displayMailboxStats(ActorSystem $actorSystem): void
    {
        $mailboxFactory = $actorSystem->getMailboxFactory();
        $mailboxes = $mailboxFactory->getAllMailboxes();
        
        if (empty($mailboxes)) {
            return;
        }

        $this->line('');
        $this->line('<info>Mailbox Statistics</info>');
        $this->line('==================');
        
        $table = $this->output->createTable();
        $table->setHeaders(['Actor Path', 'Queue Size', 'Status']);
        
        foreach ($mailboxes as $actorPath => $mailbox) {
            $table->addRow([
                $actorPath,
                $mailbox->size(),
                $mailbox->isEmpty() ? 'Empty' : 'Has Messages',
            ]);
        }
        
        $table->render();
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
} 