<?php
declare(strict_types=1);

namespace HPlus\Actor\Mailbox;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * 邮箱工厂
 * 负责创建和管理Actor邮箱
 */
class MailboxFactory
{
    private ContainerInterface $container;
    private ConfigInterface $config;
    private array $mailboxes = [];

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * 获取Actor邮箱
     */
    public function getMailbox(string $actorPath): MailboxInterface
    {
        if (!isset($this->mailboxes[$actorPath])) {
            $this->mailboxes[$actorPath] = $this->createMailbox($actorPath);
        }

        return $this->mailboxes[$actorPath];
    }

    /**
     * 创建邮箱
     */
    private function createMailbox(string $actorPath): MailboxInterface
    {
        $capacity = $this->config->get('actor.mailbox.capacity', 1000);
        return new Mailbox($capacity);
    }

    /**
     * 移除邮箱
     */
    public function removeMailbox(string $actorPath): void
    {
        if (isset($this->mailboxes[$actorPath])) {
            $mailbox = $this->mailboxes[$actorPath];
            if ($mailbox instanceof Mailbox) {
                $mailbox->close();
            }
            unset($this->mailboxes[$actorPath]);
        }
    }

    /**
     * 获取所有邮箱
     */
    public function getAllMailboxes(): array
    {
        return $this->mailboxes;
    }
} 