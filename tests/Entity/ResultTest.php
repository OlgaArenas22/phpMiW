<?php

/**
 * @category TestEntities
 * @package  App\Tests\Entity
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://miw.etsisi.upm.es/ E.T.S. de Ingeniería de Sistemas Informáticos
 */

namespace App\Tests\Entity;

use App\Entity\Result;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Group};
use PHPUnit\Framework\TestCase;

#[Group('entities')]
#[CoversClass(Result::class)]
class ResultTest extends TestCase
{
    private Result $result;

    protected function setUp(): void
    {
        $this->result = new Result();
    }

    public function testConstructorInitializesTime(): void
    {
        $r = new Result();

        self::assertNull($r->getId());
        self::assertSame(0, $r->getResult());

        $time = $r->getTime();
        self::assertInstanceOf(\DateTimeInterface::class, $time);

        self::assertGreaterThan(0, $time->getTimestamp());
    }

    public function testGetSetResult(): void
    {
        self::assertSame(0, $this->result->getResult());

        $this->result->setResult(123);
        self::assertSame(123, $this->result->getResult());
    }

    public function testGetSetTime(): void
    {
        $dt = new DateTimeImmutable('2020-01-02T03:04:05+00:00');

        $this->result->setTime($dt);

        self::assertSame($dt, $this->result->getTime());
        self::assertSame('2020-01-02T03:04:05+00:00', $this->result->getTime()->format(DATE_ATOM));
    }

    public function testGetSetUser(): void
    {
        $user = new User(email: 'u@example.com', password: 'hashed', roles: ['ROLE_USER']);

        self::assertNull($this->result->getUser());
        self::assertNull($this->result->getUserId());

        $this->result->setUser($user);

        self::assertSame($user, $this->result->getUser());

        self::assertSame(0, $this->result->getUserId());
    }

    public function testJsonSerialize(): void
    {
        $dt = new DateTimeImmutable('2022-02-03T04:05:06+00:00');
        $user = new User(email: 'u@example.com', password: 'hashed', roles: ['ROLE_USER']);

        $this->result
            ->setResult(77)
            ->setTime($dt)
            ->setUser($user);

        $data = $this->result->jsonSerialize();

        self::assertIsArray($data);

        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey(Result::RESULT_ATTR, $data);
        self::assertArrayHasKey(Result::TIME_ATTR, $data);
        self::assertArrayHasKey('userId', $data);

        self::assertNull($data['id']);
        self::assertSame(77, $data[Result::RESULT_ATTR]);
        self::assertSame('2022-02-03T04:05:06+00:00', $data[Result::TIME_ATTR]);

        self::assertSame(0, $data['userId']);
    }

    public function testJsonSerializeWithoutUser(): void
    {
        $dt = new DateTimeImmutable('2022-02-03T04:05:06+00:00');

        $this->result
            ->setResult(5)
            ->setTime($dt);

        $data = $this->result->jsonSerialize();

        self::assertNull($data['id']);
        self::assertSame(5, $data[Result::RESULT_ATTR]);
        self::assertSame('2022-02-03T04:05:06+00:00', $data[Result::TIME_ATTR]);
        self::assertNull($data['userId']);
    }
}
