<?php
declare(strict_types=1);

namespace HPlus\Actor\Router;

use HPlus\Actor\Message\MessageInterface;
use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\Mailbox\MailboxFactory;
use Hyperf\Coroutine\Channel;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * 消息路由器
 * 负责消息的路由和传递
 */
class MessageRouter
{
    private ContainerInterface $container;
    private ActorRegistry $registry;
    private MailboxFactory $mailboxFactory;
    private LoggerInterface $logger;
    private array $pendingReplies = [];

    public function __construct(
        ContainerInterface $container,
        ActorRegistry $registry,
        MailboxFactory $mailboxFactory
    ) {
        $this->container = $container;
        $this->registry = $registry;
        $this->mailboxFactory = $mailboxFactory;
        $this->logger = $container->get(LoggerFactory::class)->get('actor');
    }

    /**
     * 路由消息
     */
    public function route(MessageInterface $message): void
    {
        $receiverPath = $message->getReceiver();
        $actor = $this->registry->get($receiverPath);
        
        if (!$actor) {
            $this->logger->warning("Actor not found: {$receiverPath}");
            return;
        }

        try {
            // 获取Actor的邮箱
            $mailbox = $this->mailboxFactory->getMailbox($receiverPath);
            
            // 将消息放入邮箱
            $mailbox->enqueue($message);
            
            $this->logger->debug("Message routed to {$receiverPath}: {$message->getType()}");
        } catch (\Throwable $e) {
            $this->logger->error("Failed to route message: " . $e->getMessage());
        }
    }

    /**
     * 发送消息并等待回复
     */
    public function ask(string $actorPath, MessageInterface $message, int $timeout = 5): mixed
    {
        $replyChannel = new Channel(1);
        $replyId = uniqid('reply_', true);
        
        // 记录待回复的消息
        $this->pendingReplies[$replyId] = $replyChannel;
        
        // 设置回复地址
        $message = clone $message;
        $message = new \HPlus\Actor\Message\Message(
            $message->getType(),
            $message->getPayload(),
            $actorPath,
            $message->getSender(),
            $message->getPriority(),
            true,
            $replyId
        );

        // 发送消息
        $this->route($message);

        try {
            // 等待回复
            $reply = $replyChannel->pop($timeout);
            unset($this->pendingReplies[$replyId]);
            
            return $reply;
        } catch (\Throwable $e) {
            unset($this->pendingReplies[$replyId]);
            throw new \RuntimeException("Ask timeout or failed: " . $e->getMessage());
        }
    }

    /**
     * 发送回复
     */
    public function reply(string $replyId, mixed $response): void
    {
        if (isset($this->pendingReplies[$replyId])) {
            $channel = $this->pendingReplies[$replyId];
            $channel->push($response);
        }
    }

    /**
     * 批量路由消息
     */
    public function routeBatch(array $messages): void
    {
        foreach ($messages as $message) {
            $this->route($message);
        }
    }
} 