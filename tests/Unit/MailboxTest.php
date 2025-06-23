<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Unit;

use HPlus\Actor\Mailbox\Mailbox;
use HPlus\Actor\Mailbox\MailboxInterface;
use HPlus\Actor\Mailbox\MailboxFactory;
use HPlus\Actor\Message\Message;

require_once __DIR__ . '/../bootstrap.php';

/**
 * 邮箱系统测试
 */
class MailboxTest extends \TestCase
{
    public function testMailboxCreation(): void
    {
        $mailbox = new Mailbox(10);
        
        $this->assertInstanceOf(MailboxInterface::class, $mailbox);
        $this->assertTrue($mailbox->isEmpty());
        $this->assertEquals(0, $mailbox->size());
    }

    public function testEnqueueDequeue(): void
    {
        $mailbox = new Mailbox(10);
        $message = new Message('test', ['data' => 'value']);

        // 测试入队
        $mailbox->enqueue($message);
        $this->assertFalse($mailbox->isEmpty());
        $this->assertEquals(1, $mailbox->size());

        // 测试出队
        $dequeued = $mailbox->dequeue();
        $this->assertEquals($message, $dequeued);
        $this->assertTrue($mailbox->isEmpty());
        $this->assertEquals(0, $mailbox->size());
    }

    public function testMultipleMessages(): void
    {
        $mailbox = new Mailbox(10);
        $messages = [];

        // 入队多个消息
        for ($i = 0; $i < 5; $i++) {
            $message = new Message("test{$i}", ['index' => $i]);
            $messages[] = $message;
            $mailbox->enqueue($message);
        }

        $this->assertEquals(5, $mailbox->size());

        // 按顺序出队
        for ($i = 0; $i < 5; $i++) {
            $dequeued = $mailbox->dequeue();
            $this->assertEquals($messages[$i], $dequeued);
        }

        $this->assertTrue($mailbox->isEmpty());
    }

    public function testEmptyDequeue(): void
    {
        $mailbox = new Mailbox(10);
        
        $result = $mailbox->dequeue();
        $this->assertNull($result);
    }

    public function testClear(): void
    {
        $mailbox = new Mailbox(10);

        // 添加消息
        for ($i = 0; $i < 3; $i++) {
            $mailbox->enqueue(new Message("test{$i}", []));
        }

        $this->assertEquals(3, $mailbox->size());

        // 清空
        $mailbox->clear();
        $this->assertTrue($mailbox->isEmpty());
        $this->assertEquals(0, $mailbox->size());
    }

    public function testMailboxFactory(): void
    {
        $factory = new MailboxFactory($this->container, $this->container->get(\Hyperf\Contract\ConfigInterface::class));

        $mailbox1 = $factory->getMailbox('/user/actor1');
        $mailbox2 = $factory->getMailbox('/user/actor2');
        $mailbox3 = $factory->getMailbox('/user/actor1'); // 重复获取

        $this->assertInstanceOf(MailboxInterface::class, $mailbox1);
        $this->assertInstanceOf(MailboxInterface::class, $mailbox2);
        $this->assertSame($mailbox1, $mailbox3); // 应该返回相同实例
        $this->assertNotSame($mailbox1, $mailbox2); // 不同Actor应该有不同邮箱
    }

    public function testFactoryRemoveMailbox(): void
    {
        $factory = new MailboxFactory($this->container, $this->container->get(\Hyperf\Contract\ConfigInterface::class));

        $mailbox = $factory->getMailbox('/user/test');
        $this->assertInstanceOf(MailboxInterface::class, $mailbox);

        $factory->removeMailbox('/user/test');
        
        // 重新获取应该创建新的邮箱
        $newMailbox = $factory->getMailbox('/user/test');
        $this->assertNotSame($mailbox, $newMailbox);
    }

    /**
     * @group performance
     */
    public function testMailboxPerformance(): void
    {
        $mailbox = new Mailbox(1000);
        $messageCount = 1000;
        
        $start = microtime(true);
        
        // 批量入队
        for ($i = 0; $i < $messageCount; $i++) {
            $mailbox->enqueue(new Message("perf_test_{$i}", ['index' => $i]));
        }
        
        // 批量出队
        for ($i = 0; $i < $messageCount; $i++) {
            $mailbox->dequeue();
        }
        
        $end = microtime(true);
        $duration = $end - $start;
        
        // 性能断言：1000条消息处理应该在100ms内完成
        $this->assertLessThan(0.1, $duration, "邮箱处理1000条消息耗时 {$duration}s，超过预期");
    }
} 