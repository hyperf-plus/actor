<?php
declare(strict_types=1);

namespace HPlus\Actor;

use HPlus\Actor\Command\ActorStatsCommand;
use HPlus\Actor\Listener\ActorSystemBootListener;
use HPlus\Actor\Process\ActorSupervisorProcess;
use HPlus\Actor\System\ActorSystem;
use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Router\MessageRouter;
use HPlus\Actor\Mailbox\MailboxFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                // 核心系统
                ActorSystem::class => ActorSystem::class,
                ActorRegistry::class => ActorRegistry::class,
                MessageRouter::class => MessageRouter::class,
                MailboxFactory::class => MailboxFactory::class,
            ],
            'processes' => [
                // Actor监督进程
                ActorSupervisorProcess::class,
            ],
            'listeners' => [
                // 系统启动监听器
                ActorSystemBootListener::class,
            ],
            'commands' => [
                // 管理命令
                ActorStatsCommand::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'actor-config',
                    'description' => 'Actor system configuration file',
                    'source' => __DIR__ . '/../publish/actor.php',
                    'destination' => BASE_PATH . '/config/autoload/actor.php',
                ],
            ],
        ];
    }
} 