<?php

namespace App\Tests\Security;

use App\Security\JWTTokenAuthenticator;
use PHPUnit\Framework\Attributes\{CoversClass, Group};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

#[Group('security')]
#[CoversClass(JWTTokenAuthenticator::class)]
class JWTTokenAuthenticatorTest extends TestCase
{
    private function auth(): JWTTokenAuthenticator
    {
        return new class extends JWTTokenAuthenticator {
            public function __construct() {}
        };
    }

    public function testCheckCredentialsFalseWhenNoRoles(): void
    {
        $auth = $this->auth();

        $user = $this->createMock(UserInterface::class);
        $user->method('getRoles')->willReturn([]);

        self::assertFalse($auth->checkCredentials(null, $user));
    }

    public function testCheckCredentialsTrueWhenHasRoles(): void
    {
        $auth = $this->auth();

        $user = $this->createMock(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        self::assertTrue($auth->checkCredentials(null, $user));
    }

    public function testOnAuthenticationFailureReturns403JsonResponse(): void
    {
        $auth = $this->auth();

        $request = Request::create('/api/v1/login', Request::METHOD_POST);

        $exception = new class extends AuthenticationException {
            public function getMessageKey(): string
            {
                return 'bad creds';
            }
        };

        $resp = $auth->onAuthenticationFailure($request, $exception);

        self::assertInstanceOf(JsonResponse::class, $resp);
        self::assertSame(Response::HTTP_FORBIDDEN, $resp->getStatusCode());

        $data = json_decode((string) $resp->getContent(), true);
        self::assertSame(Response::HTTP_FORBIDDEN, $data['code']);
        self::assertStringContainsString('Forbidden', $data['message']);
        self::assertStringContainsString('bad creds', $data['message']);
    }

    public function testStartReturns401(): void
    {
        $auth = $this->auth();

        $request = Request::create('/api/v1/anything', Request::METHOD_GET, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $resp = $auth->start($request);

        self::assertInstanceOf(Response::class, $resp);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $resp->getStatusCode());
    }

    public function testSupportsOnlyWhenMethodIsPostAndParentSupports(): void
    {
        $auth = new class extends JWTTokenAuthenticator {
            public function __construct() {}
            public bool $parentSupports = true;

            public function supports(Request $request): bool
            {
                return $this->parentSupports && $request->isMethod(Request::METHOD_POST);
            }
        };

        $reqPost = Request::create('/api/v1/login', Request::METHOD_POST);
        $reqGet = Request::create('/api/v1/login', Request::METHOD_GET);

        $auth->parentSupports = true;
        self::assertTrue($auth->supports($reqPost));
        self::assertFalse($auth->supports($reqGet));

        $auth->parentSupports = false;
        self::assertFalse($auth->supports($reqPost));
    }
}
