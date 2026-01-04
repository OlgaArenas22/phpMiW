<?php

namespace App\Tests\Controller;

use App\Entity\User;
use PHPUnit\Framework\Attributes\{CoversClass, Group, Depends};
use Symfony\Component\HttpFoundation\{Request, Response};

#[Group('controllers')]
#[CoversClass(\App\Controller\ApiUsersCommandController::class)]
class ApiUsersCommandControllerTest extends BaseTestCase
{
    private const string RUTA_API = '/api/v1/users';

    private static array $adminHeaders;
    private static array $userHeaders;

    private static array $createdUser = [];

    public function testCommandStatus401UnauthorizedDelete(): void
    {
        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/1.json', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testCommandStatus401UnauthorizedPost(): void
    {
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testCommandStatus401UnauthorizedPut(): void
    {
        self::$client->request(Request::METHOD_PUT, self::RUTA_API . '/1.json', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNAUTHORIZED);
    }

    public function testCommandStatus403ForbiddenRoleUserDelete(): void
    {
        self::$userHeaders = $this->getTokenHeaders(self::$role_user[User::EMAIL_ATTR], self::$role_user[User::PASSWD_ATTR]);
        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/1.json', [], [], self::$userHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    public function testCommandStatus403ForbiddenRoleUserPost(): void
    {
        self::$userHeaders = $this->getTokenHeaders(self::$role_user[User::EMAIL_ATTR], self::$role_user[User::PASSWD_ATTR]);
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$userHeaders, strval(json_encode([])));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    public function testCommandStatus403ForbiddenRoleUserPutOtherUser(): void
    {
        self::$userHeaders = $this->getTokenHeaders(self::$role_user[User::EMAIL_ATTR], self::$role_user[User::PASSWD_ATTR]);
        self::$client->request(Request::METHOD_PUT, self::RUTA_API . '/1.json', [], [], self::$userHeaders, strval(json_encode([])));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    public function testPostUserAction422UnprocessableEntity(): void
    {
        self::$adminHeaders = $this->getTokenHeaders(self::$role_admin[User::EMAIL_ATTR], self::$role_admin[User::PASSWD_ATTR]);
        self::$client->request(Request::METHOD_POST, self::RUTA_API . '.json', [], [], self::$adminHeaders, strval(json_encode([])));
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPostUserAction201Created(): array
    {
        self::$adminHeaders = $this->getTokenHeaders(self::$role_admin[User::EMAIL_ATTR], self::$role_admin[User::PASSWD_ATTR]);

        $p_data = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => ['ROLE_USER'],
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '.json',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );

        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertNotNull($response->headers->get('Location'));
        self::assertJson((string) $response->getContent());

        $u = json_decode((string) $response->getContent(), true)[User::USER_ATTR];

        self::$createdUser = [
            'id' => $u['id'],
            User::EMAIL_ATTR => $p_data[User::EMAIL_ATTR],
            User::PASSWD_ATTR => $p_data[User::PASSWD_ATTR],
        ];

        return self::$createdUser;
    }

    public function testPostUserAction400BadRequest(): void
    {
        if (empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }

        $email = self::$faker->email();

        $first = [
            User::EMAIL_ATTR => $email,
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => ['ROLE_USER'],
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '.json',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($first))
        );
        $response1 = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response1->getStatusCode());

        $second = [
            User::EMAIL_ATTR => $email,
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => ['ROLE_USER'],
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '.json',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($second))
        );
        $response2 = self::$client->getResponse();

        $this->checkResponseErrorMessage($response2, Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testPostUserAction201Created')]
    public function testPutUserAction404NotFound(array $user): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/999999.json',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode([User::EMAIL_ATTR => self::$faker->email()]))
        );

        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    public function testPutUserAction412PreconditionFailed(): void
    {
        if (empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }

        $email = self::$faker->email();
        $passwd = self::$faker->password();

        $p_create = [
            User::EMAIL_ATTR => $email,
            User::PASSWD_ATTR => $passwd,
            User::ROLES_ATTR => ['ROLE_USER'],
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '.json',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_create))
        );
        $r1 = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $r1->getStatusCode());

        $created = json_decode((string) $r1->getContent(), true)[User::USER_ATTR];
        $id = $created['id'] ?? null;
        self::assertNotNull($id);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $id . '.json',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode([User::EMAIL_ATTR => self::$faker->email()]))
        );

        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_PRECONDITION_FAILED
        );
    }

    #[Depends('testPostUserAction201Created')]
    public function testPutUserAction209ContentReturned(array $user): array
    {
        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . $user['id'] . '.json', [], [], self::$adminHeaders);
        $etag = self::$client->getResponse()->getEtag();

        $p_data = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => ['ROLE_USER'],
        ];

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'] . '.json',
            [],
            [],
            array_merge(self::$adminHeaders, ['HTTP_If-Match' => $etag]),
            strval(json_encode($p_data))
        );

        $response = self::$client->getResponse();
        self::assertSame(209, $response->getStatusCode());
        self::assertJson((string) $response->getContent());

        $u = json_decode((string) $response->getContent(), true)[User::USER_ATTR];
        self::assertSame($user['id'], $u['id']);
        self::assertSame($p_data[User::EMAIL_ATTR], $u[User::EMAIL_ATTR]);

        self::$createdUser = [
            'id' => $u['id'],
            User::EMAIL_ATTR => $p_data[User::EMAIL_ATTR],
            User::PASSWD_ATTR => $user[User::PASSWD_ATTR],
        ];

        return self::$createdUser;
    }

    #[Depends('testPostUserAction201Created')]
    public function testPutUserAction403ForbiddenPromoteToAdmin(array $user): void
    {
        self::$userHeaders = $this->getTokenHeaders(self::$role_user[User::EMAIL_ATTR], self::$role_user[User::PASSWD_ATTR]);

        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . $user['id'] . '.json', [], [], self::$adminHeaders);
        $etag = self::$client->getResponse()->getEtag();

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'] . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            strval(json_encode([User::ROLES_ATTR => ['ROLE_ADMIN']]))
        );

        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_FORBIDDEN);
    }

    public function testPutUserAction403ForbiddenRoleUserSelfPromote(): void
    {
        if (empty(self::$adminHeaders)) {
            self::$adminHeaders = $this->getTokenHeaders(
                self::$role_admin[User::EMAIL_ATTR],
                self::$role_admin[User::PASSWD_ATTR]
            );
        }

        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '.json/email',
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson((string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('users', $data);

        $userId = null;
        foreach ($data['users'] as $item) {
            if (($item['user'][User::EMAIL_ATTR] ?? null) === self::$role_user[User::EMAIL_ATTR]) {
                $userId = $item['user']['id'] ?? null;
                break;
            }
        }
        self::assertNotNull($userId);

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $userId . '.json',
            [],
            [],
            self::$userHeaders
        );
        $etag = self::$client->getResponse()->getEtag();
        self::assertNotNull($etag);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $userId . '.json',
            [],
            [],
            array_merge(self::$userHeaders, ['HTTP_If-Match' => $etag]),
            strval(json_encode([User::ROLES_ATTR => ['ROLE_ADMIN']]))
        );

        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_FORBIDDEN
        );
    }

    public function testPutUserAction400BadRequestEmailAlreadyExists(): void
    {
        $adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        $payload = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => ['ROLE_USER'],
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '.json',
            [],
            [],
            $adminHeaders,
            (string) json_encode($payload)
        );
        $createResp = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $createResp->getStatusCode());

        $created = json_decode((string) $createResp->getContent(), true)[User::USER_ATTR];
        $id = $created['id'] ?? null;
        self::assertNotNull($id);

        self::$client->request(Request::METHOD_HEAD, self::RUTA_API . '/' . $id . '.json', [], [], $adminHeaders);
        $etag = self::$client->getResponse()->getEtag();
        self::assertNotNull($etag);

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $id . '.json',
            [],
            [],
            array_merge($adminHeaders, ['HTTP_If-Match' => $etag]),
            (string) json_encode([User::EMAIL_ATTR => self::$role_admin[User::EMAIL_ATTR]])
        );

        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_BAD_REQUEST);
    }

    #[Depends('testPostUserAction201Created')]
    public function testDeleteUserAction404NotFound(array $user): void
    {
        self::$client->request(Request::METHOD_DELETE, self::RUTA_API . '/999999.json', [], [], self::$adminHeaders);
        $this->checkResponseErrorMessage(self::$client->getResponse(), Response::HTTP_NOT_FOUND);
    }

    #[Depends('testPutUserAction209ContentReturned')]
    public function testDeleteUserAction204NoContent(array $user): void
    {
        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $user['id'] . '.json',
            [],
            [],
            self::$adminHeaders
        );

        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', (string) $response->getContent());
    }
}
