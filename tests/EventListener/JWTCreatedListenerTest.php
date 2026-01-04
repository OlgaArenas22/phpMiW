<?php

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\JWTCreatedListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Group};
use PHPUnit\Framework\TestCase;

#[Group('listeners')]
#[CoversClass(JWTCreatedListener::class)]
class JWTCreatedListenerTest extends TestCase
{
    public function testOnJWTCreatedAddsExpIdEmailAndKeepsPayload(): void
    {
        $listener = new JWTCreatedListener();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);
        $user->method('getEmail')->willReturn('user@example.test');

        $payload = [
            'username' => 'user@example.test',
            'roles' => ['ROLE_USER'],
            'foo' => 'bar',
        ];

        $captured = null;

        $event = $this->createMock(JWTCreatedEvent::class);
        $event->expects(self::once())->method('getData')->willReturn($payload);
        $event->expects(self::once())->method('getUser')->willReturn($user);
        $event->expects(self::once())->method('setData')->willReturnCallback(function (array $data) use (&$captured): void {
            $captured = $data;
        });

        $before = time();
        $listener->onJWTCreated($event);
        $after = time();

        self::assertIsArray($captured);

        self::assertSame('bar', $captured['foo']);
        self::assertSame($payload['roles'], $captured['roles']);
        self::assertSame($payload['username'], $captured['username']);

        self::assertSame(123, $captured['id']);
        self::assertSame('user@example.test', $captured['email']);

        self::assertArrayHasKey('exp', $captured);
        self::assertIsInt($captured['exp']);

        $min = $before + 7200 - 3;
        $max = $after + 7200 + 3;
        self::assertGreaterThanOrEqual($min, $captured['exp']);
        self::assertLessThanOrEqual($max, $captured['exp']);
    }
}
