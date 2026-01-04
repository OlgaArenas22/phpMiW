<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Faker\Factory as FakerFactoryAlias;
use Generator;
use JetBrains\PhpStorm\ArrayShape;
use App\Repository\ResultRepository;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Depends, Group};
use Symfony\Component\HttpFoundation\{ Request, Response };

#[Group('controllers')]
#[CoversClass(\App\Controller\ApiUsersQueryController::class)]
class ApiUsersControllerTest extends BaseTestCase
{
    private const string RUTA_API = '/api/v1/users';

    /** @var array<string,string> $adminHeaders */
    private static array $adminHeaders;

    public function testBestAction404NotFound(): void
    {
        $headers = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\Result r')->execute();

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/best.json',
            [],
            [],
            $headers
        );

        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND
        );
    }

    private static function createBestFixtures(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $admin = $em->getRepository(User::class)->findOneBy([User::EMAIL_ATTR => self::$role_admin[User::EMAIL_ATTR]]);
        if (!$admin instanceof User) {
            return;
        }

        $r1 = (new Result())->setUser($admin)->setResult(999)->setTime(new \DateTime('2020-01-01T00:00:00+00:00'));
        $r2 = (new Result())->setUser($admin)->setResult(888)->setTime(new \DateTime('2020-01-02T00:00:00+00:00'));

        $em->persist($r1);
        $em->persist($r2);
        $em->flush();
    }

    /**
     * Test OPTIONS /users[/userId] 204 No Content
     */
    public function testOptionsUserAction204NoContent(): void
    {
        // OPTIONS /api/v1/users
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNotEmpty($response->headers->get('Allow'));

        // OPTIONS /api/v1/users/{id}
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API . '/' . self::$faker->numberBetween(1, 100)
        );
        $response = self::$client->getResponse(); // <-- IMPORTANTE: refrescar

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNotEmpty($response->headers->get('Allow'));
    }

    /**
     * Test POST /users 201 Created
     *
     * @return array<string,mixed> user data
     */
    public function testPostUserAction201Created(): array
    {
        $p_data = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => [ self::$faker->word() ],
        ];
        self::$adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->headers->get('Location'));
        self::assertJson(strval($response->getContent()));

        $user = json_decode(strval($response->getContent()), true)[User::USER_ATTR];
        self::assertNotEmpty($user['id']);
        self::assertSame($p_data[User::EMAIL_ATTR], $user[User::EMAIL_ATTR]);
        self::assertContains($p_data[User::ROLES_ATTR][0], $user[User::ROLES_ATTR]);

        return $user;
    }

    /**
     * Test GET /users 200 Ok
     *
     * @return string ETag header
     */
    #[Depends('testPostUserAction201Created')]
    public function testCGetUserAction200Ok(): string
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API, [], [], self::$adminHeaders);
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());
        $r_body = strval($response->getContent());
        self::assertJson($r_body);
        $users = json_decode($r_body, true);
        self::assertArrayHasKey('users', $users);

        return (string) $response->getEtag();
    }

    /**
     * Test GET /users 304 NOT MODIFIED
     *
     * @param string $etag returned by testCGetUserAction200Ok
     */
    #[Depends('testCGetUserAction200Ok')]
    public function testCGetUserAction304NotModified(string $etag): void
    {
        $headers = array_merge(self::$adminHeaders, [ 'HTTP_If-None-Match' => [$etag] ]);
        self::$client->request(Request::METHOD_GET, self::RUTA_API, [], [], $headers);
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    /**
     * Test GET /users 200 Ok (with XML header)
     *
     * @param array<string,mixed> $user
     */
    #[Depends('testPostUserAction201Created')]
    public function testCGetUserAction200XmlOk(array $user): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $user['id'] . '.xml',
            [],
            [],
            array_merge(self::$adminHeaders, [ 'HTTP_ACCEPT' => 'application/xml' ])
        );
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful(), strval($response->getContent()));
        self::assertNotNull($response->getEtag());
        self::assertTrue($response->headers->contains('content-type', 'application/xml'));
    }

    /**
     * Test GET /users/{userId} 200 Ok
     *
     * @param array<string,mixed> $user
     * @return string ETag
     */
    #[Depends('testPostUserAction201Created')]
    public function testGetUserAction200Ok(array $user): string
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotNull($response->getEtag());
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        $user_aux = json_decode($r_body, true)[User::USER_ATTR];
        self::assertSame($user['id'], $user_aux['id']);

        return (string) $response->getEtag();
    }

    /**
     * Test GET /users/{userId} 304 NOT MODIFIED
     *
     * @param array<string,mixed> $user
     * @param string $etag
     * @return string Entity Tag
     */
    #[Depends('testPostUserAction201Created')]
    #[Depends('testGetUserAction200Ok')]
    public function testGetUserAction304NotModified(array $user, string $etag): string
    {
        $headers = array_merge(self::$adminHeaders, [ 'HTTP_If-None-Match' => [$etag] ]);
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/' . $user['id'], [], [], $headers);
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());

        return $etag;
    }

    /**
     * Test POST /users 400 Bad Request
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    #[Depends('testPostUserAction201Created')]
    public function testPostUserAction400BadRequest(array $user): array
    {
        $p_data = [
            User::EMAIL_ATTR => $user[User::EMAIL_ATTR],
            User::PASSWD_ATTR => self::$faker->password(),
        ];
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);

        return $user;
    }

    /**
     * Test PUT /users/{userId} 209 Content Returned
     *
     * @param array<string,mixed> $user
     * @param string $etag
     * @return array<string,mixed>
     */
    #[Depends('testPostUserAction201Created')]
    #[Depends('testGetUserAction304NotModified')]
    #[Depends('testCGetUserAction304NotModified')]
    #[Depends('testPostUserAction400BadRequest')]
    public function testPutUserAction209ContentReturned(array $user, string $etag): array
    {
        $role = self::$faker->word();
        $p_data = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => [ $role ],
        ];

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            array_merge(self::$adminHeaders, [ 'HTTP_If-Match' => $etag ]),
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();

        self::assertSame(209, $response->getStatusCode());
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        $user_aux = json_decode($r_body, true)[User::USER_ATTR];
        $user_aux[User::PASSWD_ATTR] = $p_data[User::PASSWD_ATTR];

        self::assertSame($user['id'], $user_aux['id']);
        self::assertSame($p_data[User::EMAIL_ATTR], $user_aux[User::EMAIL_ATTR]);
        self::assertContains($role, $user_aux[User::ROLES_ATTR]);

        return $user_aux;
    }

    /**
     * Test PUT /users/{userId} 400 Bad Request
     *
     * @param array<string,mixed> $user
     */
    #[Depends('testPutUserAction209ContentReturned')]
    public function testPutUserAction400BadRequest(array $user): void
    {
        $p_data = [ User::EMAIL_ATTR => $user[User::EMAIL_ATTR] ];

        // get etag
        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . $user['id'], [], [], self::$adminHeaders);
        $etag = self::$client->getResponse()->getEtag();

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            array_merge(self::$adminHeaders, [ 'HTTP_If-Match' => $etag ]),
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test PUT /users/{userId} 412 PRECONDITION_FAILED
     *
     * @param array<string,mixed> $user
     */
    #[Depends('testPutUserAction209ContentReturned')]
    public function testPutUserAction412PreconditionFailed(array $user): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_PRECONDITION_FAILED);
    }

    /**
     * Test PUT /users/{userId} 403 FORBIDDEN - try to promote the user to admin role
     *
     * @param array<string,mixed> $user
     */
    #[Depends('testPutUserAction209ContentReturned')]
    public function testPutUserAction403Forbidden(array $user): void
    {
        $userHeaders = $this->getTokenHeaders($user[User::EMAIL_ATTR], $user[User::PASSWD_ATTR]);

        // get user's etag
        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . $user['id'], [], [], $userHeaders);
        $etag = self::$client->getResponse()->getEtag();

        $p_data = [ User::ROLES_ATTR => [ 'ROLE_ADMIN' ] ];

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            array_merge($userHeaders, [ 'HTTP_If-Match' => $etag ]),
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_FORBIDDEN);
    }

    /**
     * Test DELETE /users/{userId} 204 No Content
     *
     * @param array<string,mixed> $user
     * @return int userId
     */
    #[Depends('testPostUserAction400BadRequest')]
    #[Depends('testPutUserAction412PreconditionFailed')]
    #[Depends('testPutUserAction403Forbidden')]
    #[Depends('testCGetUserAction200XmlOk')]
    #[Depends('testPutUserAction400BadRequest')]
    public function testDeleteUserAction204NoContent(array $user): int
    {
        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertEmpty($response->getContent());

        return intval($user['id']);
    }

    /**
     * Test POST /users 422 Unprocessable Entity
     */
    #[Depends('testPutUserAction209ContentReturned')]
    #[DataProvider('userProvider422')]
    public function testPostUserAction422UnprocessableEntity(?string $email, ?string $password): void
    {
        $p_data = [ User::EMAIL_ATTR => $email, User::PASSWD_ATTR => $password ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * 401 UNAUTHORIZED routes
     */
    #[DataProvider('providerRoutes401')]
    public function testUserStatus401Unauthorized(string $method, string $uri): void
    {
        self::$client->request(
            $method,
            $uri,
            [],
            [],
            [ 'HTTP_ACCEPT' => 'application/json' ]
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * 404 NOT FOUND routes (after delete)
     */
    #[Depends('testDeleteUserAction204NoContent')]
    #[DataProvider('providerRoutes404')]
    public function testUserStatus404NotFound(string $method, int $userId): void
    {
        self::$client->request(
            $method,
            self::RUTA_API . '/' . $userId,
            [],
            [],
            self::$adminHeaders
        );
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    /**
     * 403 FORBIDDEN routes for ROLE_USER
     */
    #[DataProvider('providerRoutes403')]
    public function testUserStatus403Forbidden(string $method, string $uri): void
    {
        $userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );
        self::$client->request($method, $uri, [], [], $userHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }
    
    /**
     * Test OPTIONS /users/best 204 No Content
     */
    public function testOptionsBestAction204NoContent(): void
    {
        self::$client->request(Request::METHOD_OPTIONS, self::RUTA_API . '/best.json');
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNotEmpty($response->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_GET, $response->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_HEAD, $response->headers->get('Allow'));
        self::assertStringContainsString(Request::METHOD_OPTIONS, $response->headers->get('Allow'));
    }

    public function testBestAction200Ok(): string
    {
        if (empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }

        self::createBestFixtures();

        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/best.json', [], [], self::$adminHeaders);
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());

        $body = (string) $response->getContent();
        self::assertJson($body);

        $data = json_decode($body, true);
        self::assertArrayHasKey('bestResults', $data);
        self::assertIsArray($data['bestResults']);
        self::assertNotEmpty($data['bestResults']);
        self::assertArrayHasKey('result', $data['bestResults'][0]);

        return (string) $response->getEtag();
    }

    public function testBestAction200XmlOk(): void
    {
        if (empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }

        self::createBestFixtures();

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/best.xml',
            [],
            [],
            array_merge(self::$adminHeaders, [ 'HTTP_ACCEPT' => 'application/xml' ])
        );
        $response = self::$client->getResponse();

        self::assertTrue($response->isSuccessful(), (string) $response->getContent());
        self::assertNotNull($response->getEtag());
        self::assertTrue($response->headers->contains('content-type', 'application/xml'));
    }

    public function testBestHeadAction200Ok(): void
    {
        if (empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }

        self::createBestFixtures();

        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/best.json', [], [], self::$adminHeaders);
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotNull($response->getEtag());
        self::assertSame('', (string) $response->getContent());
    }

    public function testBestAction401Unauthorized(): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/best.json',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_UNAUTHORIZED
        );
    }

    public function testBestAction403Forbidden(): void
    {
        $headers = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/best.json',
            [],
            [],
            $headers
        );

        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_FORBIDDEN
        );
    }

    public function testBestAction304NotModifiedWithWildcard(): void
    {
        $headers = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        self::createBestFixtures();

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/best.json',
            [],
            [],
            array_merge($headers, ['HTTP_If-None-Match' => ['*']])
        );

        self::assertSame(
            Response::HTTP_NOT_MODIFIED,
            self::$client->getResponse()->getStatusCode()
        );
    }

    // ============================================================
    // PROVIDERS
    // ============================================================

    /**
     * User provider (incomplete) -> 422 status code
     *
     * @return Generator user data [email, password]
     */
    #[ArrayShape([
        'no_email' => "array",
        'no_passwd' => "array",
        'nothing' => "array"
    ])]
    public static function userProvider422(): Generator
    {
        $faker = FakerFactoryAlias::create('es_ES');
        $email = $faker->email();
        $password = $faker->password();

        yield 'no_email'  => [ null,   $password ];
        yield 'no_passwd' => [ $email, null      ];
        yield 'nothing'   => [ null,   null      ];
    }

    /**
     * Route provider (expected status: 401 UNAUTHORIZED)
     *
     * @return Generator name => [ method, url ]
     */
    #[ArrayShape([
        'cgetAction401' => "array",
        'getAction401' => "array",
        'postAction401' => "array",
        'putAction401' => "array",
        'deleteAction401' => "array"
    ])]
    public static function providerRoutes401(): Generator
    {
        yield 'cgetAction401'   => [ Request::METHOD_GET,    self::RUTA_API ];
        yield 'getAction401'    => [ Request::METHOD_GET,    self::RUTA_API . '/1' ];
        yield 'postAction401'   => [ Request::METHOD_POST,   self::RUTA_API ];
        yield 'putAction401'    => [ Request::METHOD_PUT,    self::RUTA_API . '/1' ];
        yield 'deleteAction401' => [ Request::METHOD_DELETE, self::RUTA_API . '/1' ];
    }

    /**
     * Route provider (expected status 404 NOT FOUND)
     *
     * @return Generator name => [ method ]
     */
    #[ArrayShape([
        'getAction404' => "array",
        'putAction404' => "array",
        'deleteAction404' => "array"
    ])]
    public static function providerRoutes404(): Generator
    {
        yield 'getAction404'    => [ Request::METHOD_GET ];
        yield 'putAction404'    => [ Request::METHOD_PUT ];
        yield 'deleteAction404' => [ Request::METHOD_DELETE ];
    }

    /**
     * Route provider (expected status: 403 FORBIDDEN)
     *
     * @return Generator name => [ method, url ]
     */
    #[ArrayShape([
        'postAction403' => "array",
        'putAction403' => "array",
        'deleteAction403' => "array"
    ])]
    public static function providerRoutes403(): Generator
    {
        yield 'postAction403'   => [ Request::METHOD_POST,   self::RUTA_API ];
        yield 'putAction403'    => [ Request::METHOD_PUT,    self::RUTA_API . '/1' ];
        yield 'deleteAction403' => [ Request::METHOD_DELETE, self::RUTA_API . '/1' ];
    }
}
