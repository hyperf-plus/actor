<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Unit;

use HPlus\Actor\Message\Message;
use HPlus\Actor\Message\MessageInterface;

require_once __DIR__ . '/../bootstrap.php';

/**
 * 消息系统测试
 */
class MessageTest extends \TestCase
{
    public function testMessageCreation(): void
    {
        $message = new Message(
            'test.message',
            ['key' => 'value'],
            '/user/receiver',
            '/user/sender',
            5,
            true,
            'reply-123'
        );

        $this->assertInstanceOf(MessageInterface::class, $message);
        $this->assertEquals('test.message', $message->getType());
        $this->assertEquals(['key' => 'value'], $message->getPayload());
        $this->assertEquals('/user/receiver', $message->getReceiver());
        $this->assertEquals('/user/sender', $message->getSender());
        $this->assertEquals(5, $message->getPriority());
        $this->assertTrue($message->needsReply());
        $this->assertEquals('reply-123', $message->getReplyTo());
    }

    public function testMessageId(): void
    {
        $message1 = new Message('test', []);
        $message2 = new Message('test', []);

        $this->assertNotEmpty($message1->getId());
        $this->assertNotEmpty($message2->getId());
        $this->assertNotEquals($message1->getId(), $message2->getId());
    }

    public function testMessageTimestamp(): void
    {
        $before = time();
        $message = new Message('test', []);
        $after = time();

        $timestamp = $message->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testMessageToArray(): void
    {
        $message = new Message(
            'test.message',
            ['data' => 'test'],
            '/user/receiver',
            '/user/sender',
            1,
            false
        );

        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('test.message', $array['type']);
        $this->assertEquals(['data' => 'test'], $array['payload']);
        $this->assertEquals('/user/receiver', $array['receiver']);
        $this->assertEquals('/user/sender', $array['sender']);
        $this->assertEquals(1, $array['priority']);
        $this->assertFalse($array['needsReply']);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function testDefaultValues(): void
    {
        $message = new Message('test', []);

        $this->assertEquals('test', $message->getType());
        $this->assertEquals([], $message->getPayload());
        $this->assertEquals('', $message->getReceiver());
        $this->assertNull($message->getSender());
        $this->assertEquals(0, $message->getPriority());
        $this->assertFalse($message->needsReply());
        $this->assertNull($message->getReplyTo());
    }
} 