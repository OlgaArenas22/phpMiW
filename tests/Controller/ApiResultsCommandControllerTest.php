<?php

namespace App\Tests\Controller;

use App\Entity\Result;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Depends, Group};
use Symfony\Component\HttpFoundation\{Request, Response};

#[Group('controllers')]
#[CoversClass(\App\Controller\ApiResultsCommandController::class)]
class ApiResultsCommandControllerTest extends BaseTestCase
{
    private const string RUTA_API = '/api/v1/results';

    private static array $adminHeaders;
    private static array $userHeaders;

    private static ?int $adminId = null;
    private static ?int $userId = null;

    private static ?int $adminResultId = null;
    private static ?int $userResultId = null;

    private static function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createResultForUser(int $userId, int $value, ?DateTime $time = null): int
    {
        $em = self::em();
        $user = $em->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $user);

        $r = new Result();
        $r->setUser($user);
        $r->setResult($value);
        if ($time instanceof DateTime) {
            $r->setTime($time);
        }

        $em->persist($r);
        $em->flush();

        self::assertNotNull($r->getId());
        return (int) $r->getId();
    }

    private function getResultEtag(int $resultId, array $headers): string
    {
        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . $resultId . '.json', [], [], $headers);
        $etag = self::$client->getResponse()->getEtag();
        self::assertNotEmpty($etag);

        return (string) $etag;
    }

    public function testPost401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], ['HTTP_ACCEPT' => 'application/json'], (string) json_encode([
            'result' => 123,
        ]));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testPut401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_PUT, self::RUTA_API . '/1.json', [], [], ['HTTP_ACCEPT' => 'application/json'], (string) json_encode([
            'result' => 10,
        ]));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testDelete401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/1.json', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testResolveUserIds200OkAndCreateFixtures(): void
    {
        self::$adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );
        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(Request::METHOD_GET, '/api/v1/users.json/email', [], [], self::$adminHeaders);
        $resp = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $resp->getStatusCode());

        $data = json_decode((string) $resp->getContent(), true);
        self::assertArrayHasKey('users', $data);

        foreach ($data['users'] as $item) {
            $email = $item['user'][User::EMAIL_ATTR] ?? null;
            if ($email === self::$role_admin[User::EMAIL_ATTR]) {
                self::$adminId = $item['user']['id'] ?? null;
            }
            if ($email === self::$role_user[User::EMAIL_ATTR]) {
                self::$userId = $item['user']['id'] ?? null;
            }
        }

        self::assertNotNull(self::$adminId);
        self::assertNotNull(self::$userId);

        self::$adminResultId = $this->createResultForUser((int) self::$adminId, 900, new DateTime('2020-01-01 00:00:00'));
        self::$userResultId = $this->createResultForUser((int) self::$userId, 100, new DateTime('2020-01-02 00:00:00'));
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPost422UnprocessableEntityMissingResult(): void
    {
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, (string) json_encode([]));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, (string) json_encode(['result' => '']));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, (string) json_encode(['result' => null]));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPost400BadRequestInvalidResult(): void
    {
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, (string) json_encode([
            'result' => 'abc',
        ]));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPost400BadRequestInvalidTime(): void
    {
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, (string) json_encode([
            'result' => 123,
            'time' => 'not-a-date',
        ]));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPost201CreatedUser(): void
    {
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, (string) json_encode([
            'result' => 321,
            'time' => '2020-02-03 10:11:12',
        ]));

        $resp = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $resp->getStatusCode());
        self::assertNotEmpty($resp->headers->get('Location'));
        self::assertJson((string) $resp->getContent());

        $data = json_decode((string) $resp->getContent(), true);
        self::assertArrayHasKey('result', $data);
        self::assertArrayHasKey('id', $data['result']);
        self::assertSame(321, (int) ($data['result']['result'] ?? 0));
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut404NotFound(): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/99999999.json',
            [],
            [],
            self::$adminHeaders,
            (string) json_encode(['result' => 1])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut403ForbiddenUserNotOwner(): void
    {
        $etag = $this->getResultEtag((int) self::$adminResultId, self::$adminHeaders);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$adminResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode(['result' => 777])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut412PreconditionFailedMissingIfMatch(): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            self::$userHeaders,
            (string) json_encode(['result' => 777])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_PRECONDITION_FAILED);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut412PreconditionFailedWrongIfMatch(): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => 'wrong-etag']),
            (string) json_encode(['result' => 777])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_PRECONDITION_FAILED);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut400BadRequestInvalidResult(): void
    {
        $etag = $this->getResultEtag((int) self::$userResultId, self::$userHeaders);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode(['result' => 'abc'])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode(['result' => ''])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode(['result' => null])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut400BadRequestInvalidTime(): void
    {
        $etag = $this->getResultEtag((int) self::$userResultId, self::$userHeaders);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode(['time' => ''])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode(['time' => 'not-a-date'])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut400BadRequestNoDataToUpdate(): void
    {
        $etag = $this->getResultEtag((int) self::$userResultId, self::$userHeaders);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode([])
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut209ContentReturnedUpdateResultAndTimeAsOwner(): void
    {
        $etag = $this->getResultEtag((int) self::$userResultId, self::$userHeaders);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode([
                'result' => 888,
                'time' => '2020-03-04 12:13:14',
            ])
        );

        $resp = self::$client->getResponse();
        self::assertSame(209, $resp->getStatusCode());
        self::assertJson((string) $resp->getContent());

        $data = json_decode((string) $resp->getContent(), true);
        self::assertArrayHasKey('result', $data);
        self::assertSame(888, (int) ($data['result']['result'] ?? 0));
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPut209ContentReturnedUpdateAsAdmin(): void
    {
        $etag = $this->getResultEtag((int) self::$userResultId, self::$adminHeaders);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . self::$userResultId . '.json',
            [],
            [],
            array_merge(self::$adminHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode([
                'result' => 999,
            ])
        );

        $resp = self::$client->getResponse();
        self::assertSame(209, $resp->getStatusCode());
        self::assertJson((string) $resp->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testDelete404NotFound(): void
    {
        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/99999999.json', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testDelete403ForbiddenUserNotOwner(): void
    {
        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/' . self::$adminResultId . '.json', [], [], self::$userHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testDelete204NoContentAsOwner(): void
    {
        $resultId = $this->createResultForUser((int) self::$userId, 555, new DateTime('2020-04-04 00:00:00'));

        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/' . $resultId . '.json', [], [], self::$userHeaders);
        $resp = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $resp->getStatusCode());
        self::assertSame('', (string) $resp->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testDelete204NoContentAsAdmin(): void
    {
        $resultId = $this->createResultForUser((int) self::$userId, 556, new DateTime('2020-04-05 00:00:00'));

        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/' . $resultId . '.json', [], [], self::$adminHeaders);
        $resp = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $resp->getStatusCode());
        self::assertSame('', (string) $resp->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testPost401UnauthorizedWhenAuthenticatedUserNotFoundInDatabase(): void
    {
        $em = self::em();

        $email = 'tmp_no_db_' . uniqid('', true) . '@example.test';
        $plainPassword = 'P@ssw0rd_123';

        $user = new User();
        if (method_exists($user, 'setEmail')) {
            $user->setEmail($email);
        }
        if (method_exists($user, 'setRoles')) {
            $user->setRoles(['ROLE_USER']);
        }
        if (method_exists($user, 'setEnabled')) {
            $user->setEnabled(true);
        }
        if (method_exists($user, 'setPassword')) {
            $user->setPassword(password_hash($plainPassword, PASSWORD_BCRYPT));
        }

        $em->persist($user);
        $em->flush();

        self::assertNotNull($user->getId());

        $headers = $this->getTokenHeaders($email, $plainPassword);

        $em->remove($user);
        $em->flush();

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '.json',
            [],
            [],
            $headers,
            (string) json_encode(['result' => 123])
        );

        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

}
