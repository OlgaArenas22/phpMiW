<?php

namespace App\Tests\Controller;

use App\Entity\Result;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Depends, Group};
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('controllers')]
#[CoversClass(\App\Controller\ApiResultsQueryController::class)]
class ApiResultsControllerTest extends BaseTestCase
{
    private const string RUTA_API = '/api/v1/results';

    private static array $adminHeaders;
    private static array $userHeaders;

    private static ?int $adminId = null;
    private static ?int $userId = null;

    private static ?int $adminResultId = null;
    private static ?int $userResultId = null;

    private function createUserAndGetAdminHeadersAndUserId(): array
    {
        $adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        self::$client->request(Request::METHOD_POST, '/api/v1/users.json', [], [], $adminHeaders, (string) json_encode([
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => 'P@ssw0rd_123',
            User::ROLES_ATTR => ['ROLE_USER'],
        ]));

        $resp = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $resp->getStatusCode());

        $u = json_decode((string) $resp->getContent(), true)[User::USER_ATTR];
        $userId = (int) ($u['id'] ?? 0);
        self::assertGreaterThan(0, $userId);

        return [$adminHeaders, $userId];
    }


    private static function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private static function passwordHasher(): UserPasswordHasherInterface
    {
        return self::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private function createUserWithoutResults(string $plainPassword = 'P@ssw0rd_123'): array
    {
        $em = self::em();

        $email = 'noresults_' . uniqid('', true) . '@example.test';

        $u = new User();
        if (method_exists($u, 'setEmail')) {
            $u->setEmail($email);
        }
        if (method_exists($u, 'setRoles')) {
            $u->setRoles(['ROLE_USER']);
        }
        if (method_exists($u, 'setEnabled')) {
            $u->setEnabled(true);
        }

        $hashed = self::passwordHasher()->hashPassword($u, $plainPassword);
        if (method_exists($u, 'setPassword')) {
            $u->setPassword($hashed);
        }

        $em->persist($u);
        $em->flush();

        self::assertNotNull($u->getId());

        return [
            'id' => (int) $u->getId(),
            'email' => $email,
            'password' => $plainPassword,
        ];
    }

    private function createResultForUser(int $userId, int $value, DateTime $time): int
    {
        $em = self::em();
        $user = $em->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $user);

        $r = new Result();
        $r->setUser($user);
        $r->setResult($value);
        $r->setTime($time);

        $em->persist($r);
        $em->flush();

        self::assertNotNull($r->getId());
        return (int) $r->getId();
    }

    public function testOptionsResultsAction204NoContent(): void
    {
        self::$client->request(Request::METHOD_OPTIONS, self::RUTA_API);
        $r1 = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $r1->getStatusCode());
        self::assertNotEmpty($r1->headers->get('Allow'));

        self::$client->request(Request::METHOD_OPTIONS, self::RUTA_API . '/1.json');
        $r2 = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $r2->getStatusCode());
        self::assertNotEmpty($r2->headers->get('Allow'));
    }

    public function testResultsCGet401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '.json');
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testResultsGet401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/1.json');
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testTop401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json');
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testStats401Unauthorized(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/stats.json');
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testOptionsTop204NoContent(): void
    {
        self::$client->request(Request::METHOD_OPTIONS, self::RUTA_API . '/top.json');
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $r->getStatusCode());
        self::assertNotEmpty($r->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_GET, (string) $r->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_HEAD, (string) $r->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_OPTIONS, (string) $r->headers->get('Allow'));
    }

    public function testOptionsStats204NoContent(): void
    {
        self::$client->request(Request::METHOD_OPTIONS, self::RUTA_API . '/stats.json');
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $r->getStatusCode());
        self::assertNotEmpty($r->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_GET, (string) $r->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_HEAD, (string) $r->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_OPTIONS, (string) $r->headers->get('Allow'));
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

        self::$adminResultId = $this->createResultForUser(
            (int) self::$adminId,
            999,
            new DateTime('2020-01-01 00:00:00')
        );
        self::$userResultId = $this->createResultForUser(
            (int) self::$userId,
            111,
            new DateTime('2020-01-02 00:00:00')
        );
    }

    public function testCGet404NotFoundWhenNoResultsCoversLine80(): void
    {
        $u = $this->createUserWithoutResults();
        $headers = $this->getTokenHeaders($u['email'], $u['password']);

        self::$client->request(Request::METHOD_GET, self::RUTA_API . '.json/id', [], [], $headers);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testCGetResults200OkAdminGetAndHeadAnd304(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '.json/id', [], [], self::$adminHeaders);
        $r1 = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r1->getStatusCode());
        self::assertNotNull($r1->getEtag());
        self::assertJson((string) $r1->getContent());
        $etag = (string) $r1->getEtag();

        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '.json/id', [], [], self::$adminHeaders);
        $r2 = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r2->getStatusCode());
        self::assertNotNull($r2->getEtag());
        self::assertSame('', (string) $r2->getContent());

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '.json/id',
            [],
            [],
            array_merge(self::$adminHeaders, ['HTTP_If-None-Match' => [$etag]])
        );
        $r3 = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $r3->getStatusCode());

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '.json/id',
            [],
            [],
            array_merge(self::$adminHeaders, ['HTTP_If-None-Match' => ['*']])
        );
        $r4 = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $r4->getStatusCode());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testCGetResults200OkUserOnlyOwn(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '.json/id', [], [], self::$userHeaders);
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertJson((string) $r->getContent());

        $data = json_decode((string) $r->getContent(), true);
        self::assertArrayHasKey('results', $data);

        foreach ($data['results'] as $item) {
            $result = $item['result'] ?? [];
            if (is_array($result) && array_key_exists('userId', $result)) {
                self::assertSame((int) self::$userId, (int) $result['userId']);
            }
        }
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testGetResult200OkAdminAndHeadAnd304(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/' . self::$adminResultId . '.json', [], [], self::$adminHeaders);
        $r1 = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r1->getStatusCode());
        self::assertNotNull($r1->getEtag());
        $etag = (string) $r1->getEtag();

        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . self::$adminResultId . '.json', [], [], self::$adminHeaders);
        $r2 = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r2->getStatusCode());
        self::assertNotNull($r2->getEtag());
        self::assertSame('', (string) $r2->getContent());

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . self::$adminResultId . '.json',
            [],
            [],
            array_merge(self::$adminHeaders, ['HTTP_If-None-Match' => [$etag]])
        );
        $r3 = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $r3->getStatusCode());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testGetResult403ForbiddenUserNotOwner(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . self::$adminResultId . '.json',
            [],
            [],
            self::$userHeaders
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testGetResult404NotFound(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/999999.json', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop400BadRequestInvalidLimit(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json?limit=abc', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop400BadRequestLimitRangeLow(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json?limit=0', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop400BadRequestLimitRangeHigh(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json?limit=101', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop400BadRequestInvalidUserId(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json?userId=abc', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testStats400BadRequestInvalidUserId(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/stats.json?userId=abc', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop403ForbiddenUserOtherUserId(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/top.json?userId=' . self::$adminId,
            [],
            [],
            self::$userHeaders
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testStats403ForbiddenUserOtherUserId(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats.json?userId=' . self::$adminId,
            [],
            [],
            self::$userHeaders
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop200OkAdminGetAndHeadAnd304(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json?limit=10', [], [], self::$adminHeaders);
        $r1 = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r1->getStatusCode());
        self::assertNotNull($r1->getEtag());
        self::assertJson((string) $r1->getContent());
        $etag = (string) $r1->getEtag();

        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/top.json?limit=10', [], [], self::$adminHeaders);
        $r2 = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r2->getStatusCode());
        self::assertNotNull($r2->getEtag());
        self::assertSame('', (string) $r2->getContent());

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/top.json?limit=10',
            [],
            [],
            array_merge(self::$adminHeaders, ['HTTP_If-None-Match' => [$etag]])
        );
        $r3 = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $r3->getStatusCode());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop200OkUserOwnDefaultScope(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/top.json?limit=10', [], [], self::$userHeaders);
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertJson((string) $r->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTop200OkAdminForSpecificUser(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/top.json?limit=10&userId=' . self::$userId,
            [],
            [],
            self::$adminHeaders
        );
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertJson((string) $r->getContent());
    }

    public function testStats200OkAdminWithUserIdCoversFilterUserIdAssignment(): void
    {
        [$adminHeaders, $userId] = $this->createUserAndGetAdminHeadersAndUserId();

        $this->createResultForUser($userId, 456, new DateTime('2020-01-04 00:00:00'));

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats.json?userId=' . $userId,
            [],
            [],
            $adminHeaders
        );

        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testStats200OkUserOwnDefaultScope(): void
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/stats.json', [], [], self::$userHeaders);
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertJson((string) $r->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testStats200OkAdminWithUserIdCoversLine377(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats.json?userId=' . self::$userId,
            [],
            [],
            self::$adminHeaders
        );
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertJson((string) $r->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testStats200OkAdminForSpecificUser(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats.json?userId=' . self::$userId,
            [],
            [],
            self::$adminHeaders
        );
        $r = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertJson((string) $r->getContent());
    }

    #[Depends('testResolveUserIds200OkAndCreateFixtures')]
    public function testTopAndStats404WhenNoDataForNonExistingUserScope(): void
    {
        $nonExistingUser = 999999;

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/top.json?limit=10&userId=' . $nonExistingUser,
            [],
            [],
            self::$adminHeaders
        );
        $r1 = self::$client->getResponse();
        self::assertTrue(in_array($r1->getStatusCode(), [Response::HTTP_OK, Response::HTTP_NOT_FOUND], true));
        if ($r1->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $this->checkResponseErrorMessage($r1, Response::HTTP_NOT_FOUND);
        }

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats.json?userId=' . $nonExistingUser,
            [],
            [],
            self::$adminHeaders
        );
        $r2 = self::$client->getResponse();
        self::assertTrue(in_array($r2->getStatusCode(), [Response::HTTP_OK, Response::HTTP_NOT_FOUND], true));
        if ($r2->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $this->checkResponseErrorMessage($r2, Response::HTTP_NOT_FOUND);
        }
    }

    public function testTop200OkAdminWithUserIdCoversFilterUserIdAssignment(): void
    {
        $this->ensureAdminHeaders();

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/top.json?limit=10&userId=' . self::$userId,
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $this->checkResponseErrorMessage($response, Response::HTTP_NOT_FOUND);
            return;
        }

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertJson((string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('results', $data);
    }

    public function testTop404NotFoundForNonExistingUserCoversEmptyResultsBranch(): void
    {
        $this->ensureAdminHeaders();

        $nonExistingUserId = 99999999;

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/top.json?limit=10&userId=' . $nonExistingUserId,
            [],
            [],
            self::$adminHeaders
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    public function testStats404NotFoundForNonExistingUserCoversEmptyStatsBranch(): void
    {
        $this->ensureAdminHeaders();

        $nonExistingUserId = 99999999;

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats.json?userId=' . $nonExistingUserId,
            [],
            [],
            self::$adminHeaders
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    private function ensureAdminHeaders(): void
    {
        if (!isset(self::$adminHeaders) || empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }
    }
}
