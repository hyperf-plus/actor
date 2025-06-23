<?php
declare(strict_types=1);

namespace HPlus\Actor\Tests\Unit;

use HPlus\Actor\Contract\ActorInterface;
use HPlus\Actor\Message\MessageInterface;
use HPlus\Actor\Registry\ActorRegistry;
use HPlus\Actor\System\ActorContext;
use HPlus\Actor\AbstractActor;

require_once __DIR__ . '/../bootstrap.php';

// 测试用Actor
class TestActor extends AbstractActor
{
    public function receive(MessageInterface $message): mixed
    {
        return ['type' => $message->getType(), 'payload' => $message->getPayload()];
    }
}

/**
 * Actor注册表测试
 */
class ActorRegistryTest extends \TestCase
{
    private ActorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建必要的依赖
        $this->container->set(\HPlus\Actor\Registry\ActorRegistry::class, \HPlus\Actor\Registry\ActorRegistry::class);
        $this->container->set(\HPlus\Actor\Router\MessageRouter::class, \HPlus\Actor\Router\MessageRouter::class);
        $this->container->set(\HPlus\Actor\Mailbox\MailboxFactory::class, \HPlus\Actor\Mailbox\MailboxFactory::class);
        $this->container->set(\HPlus\Actor\System\ActorContext::class, function() {
            return new ActorContext(
                $this->container,
                $this->container->get(\HPlus\Actor\Registry\ActorRegistry::class),
                $this->container->get(\HPlus\Actor\Router\MessageRouter::class)
            );
        });
        
        $this->registry = new ActorRegistry($this->container);
    }

    public function testCreateActor(): void
    {
        $actorPath = $this->registry->create(TestActor::class, 'test-actor');
        
        $this->assertStringContains('/user/test-actor', $actorPath);
        
        $actor = $this->registry->get($actorPath);
        $this->assertInstanceOf(TestActor::class, $actor);
        $this->assertInstanceOf(ActorInterface::class, $actor);
    }

    public function testActorId(): void
    {
        $actorPath = $this->registry->create(TestActor::class, 'test-actor');
        $actor = $this->registry->get($actorPath);
        
        $this->assertNotEmpty($actor->getId());
        $this->assertStringStartsWith('actor_', $actor->getId());
    }

    public function testActorPath(): void
    {
        $actorPath = $this->registry->create(TestActor::class, 'my-test-actor');
        $actor = $this->registry->get($actorPath);
        
        $this->assertEquals('/user/my-test-actor', $actor->getPath());
        $this->assertEquals($actorPath, $actor->getPath());
    }

    public function testDuplicateActorPath(): void
    {
        $this->registry->create(TestActor::class, 'duplicate-actor');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Actor path /user/duplicate-actor already exists');
        
        $this->registry->create(TestActor::class, 'duplicate-actor');
    }

    public function testInvalidActorClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Actor must implement ActorInterface');
        
        $this->registry->create(\stdClass::class, 'invalid-actor');
    }

    public function testGetNonexistentActor(): void
    {
        $actor = $this->registry->get('/user/nonexistent');
        $this->assertNull($actor);
    }

    public function testStopActor(): void
    {
        $actorPath = $this->registry->create(TestActor::class, 'stop-test');
        $actor = $this->registry->get($actorPath);
        $this->assertNotNull($actor);
        
        $this->registry->stop($actorPath);
        
        $stoppedActor = $this->registry->get($actorPath);
        $this->assertNull($stoppedActor);
    }

    public function testGetAllActors(): void
    {
        $this->assertEquals(0, $this->registry->count());
        
        $path1 = $this->registry->create(TestActor::class, 'actor1');
        $path2 = $this->registry->create(TestActor::class, 'actor2');
        $path3 = $this->registry->create(TestActor::class, 'actor3');
        
        $this->assertEquals(3, $this->registry->count());
        
        $allActors = $this->registry->getAll();
        $this->assertCount(3, $allActors);
        
        // 验证返回的是正确的Actor实例
        $actorPaths = array_map(fn($actor) => $actor->getPath(), $allActors);
        $this->assertContains($path1, $actorPaths);
        $this->assertContains($path2, $actorPaths);
        $this->assertContains($path3, $actorPaths);
    }

    public function testActorWithArguments(): void
    {
        // 创建一个需要额外参数的Actor
        $testActor = new class extends AbstractActor {
            private string $customValue;
            
            public function __construct(string $id, string $path, ActorContext $context, string $customValue = 'default')
            {
                parent::__construct($id, $path, $context);
                $this->customValue = $customValue;
            }
            
            public function receive(MessageInterface $message): mixed
            {
                return ['custom_value' => $this->customValue];
            }
            
            public function getCustomValue(): string
            {
                return $this->customValue;
            }
        };
        
        $actorPath = $this->registry->create(get_class($testActor), 'custom-actor', ['custom_test_value']);
        $actor = $this->registry->get($actorPath);
        
        $this->assertEquals('custom_test_value', $actor->getCustomValue());
    }

    public function testRestartActor(): void
    {
        $actorPath = $this->registry->create(TestActor::class, 'restart-test');
        $actor = $this->registry->get($actorPath);
        $originalId = $actor->getId();
        
        // 模拟异常重启
        $exception = new \RuntimeException('Test exception');
        $this->registry->restart($actorPath, $exception);
        
        // Actor应该仍然存在
        $restartedActor = $this->registry->get($actorPath);
        $this->assertNotNull($restartedActor);
        $this->assertEquals($originalId, $restartedActor->getId()); // ID应该保持不变
    }
} 