<?php

/**
 * @category TestEntities
 * @package  App\Tests\Entity
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://miw.etsisi.upm.es/ E.T.S. de Ingeniería de Sistemas Informáticos
 */

namespace App\Tests\Entity;

use App\Entity\User;
use Exception;
use Faker\Factory as FakerFactoryAlias;
use Faker\Generator as FakerGeneratorAlias;
use PHPUnit\Framework\Attributes\{ CoversClass, Group };
use PHPUnit\Framework\TestCase;

#[Group('entities')]
#[CoversClass(User::class)]
class UserTest extends TestCase
{
    protected static User $usuario;

    private static FakerGeneratorAlias $faker;

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     */
    public static function setUpBeforeClass(): void
    {
        self::$usuario = new User();
        self::$faker = FakerFactoryAlias::create('es_ES');
    }

    /**
     * Implement testConstructor().
     */
    public function testConstructor(): void
    {
        $n_usuario = new User();
        self::assertEmpty($n_usuario->getUserIdentifier());
        self::assertEmpty($n_usuario->getEmail());
        self::assertSame(0, $n_usuario->getId());
        self::assertContains('ROLE_USER', $n_usuario->getRoles());
    }

    /**
     * Constructor with params should set email/password/roles.
     *
     * @throws Exception
     */
    public function testConstructorWithParams(): void
    {
        $email = self::$faker->email();
        $password = self::$faker->password(minLength: 20);
        $roles = [ 'ROLE_ADMIN' ];

        $user = new User(email: $email, password: $password, roles: $roles);

        self::assertSame($email, $user->getEmail());
        self::assertSame($email, $user->getUserIdentifier());
        self::assertSame($password, $user->getPassword());

        // getRoles() always adds ROLE_USER and uniques
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    /**
     * Implement testGetId().
     */
    public function testGetId(): void
    {
        self::assertSame(
            expected: 0,
            actual: self::$usuario->getId()
        );
    }

    /**
     * Implements testGetSetEmail().
     *
     * @throws Exception
     */
    public function testGetSetEmail(): void
    {
        $userEmail = self::$faker->email();
        self::$usuario->setEmail($userEmail);
        self::assertSame($userEmail, self::$usuario->getEmail());
        self::assertSame($userEmail, self::$usuario->getUserIdentifier());
    }

    /**
     * Implements testGetSetPassword().
     *
     * @throws Exception
     */
    public function testGetSetPassword(): void
    {
        $password = self::$faker->password(minLength: 20);
        self::$usuario->setPassword($password);
        self::assertSame($password, self::$usuario->getPassword());
    }

    /**
     * Implement testGetSetRoles().
     *
     * @throws Exception
     */
    public function testGetSetRoles(): void
    {
        self::assertContains('ROLE_USER', self::$usuario->getRoles());

        $role = strtoupper(self::$faker->word());
        self::$usuario->setRoles([ $role ]);

        self::assertContains($role, self::$usuario->getRoles());
        // Always adds ROLE_USER
        self::assertContains('ROLE_USER', self::$usuario->getRoles());
    }

    /**
     * getRoles() must return unique roles and always include ROLE_USER.
     */
    public function testGetRolesAlwaysAddsRoleUserAndIsUnique(): void
    {
        $user = new User(
            email: self::$faker->email(),
            password: self::$faker->password(minLength: 20),
            roles: [ 'ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN', 'ROLE_ADMIN' ]
        );

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);

        // Ensure uniqueness
        self::assertSameSize($roles, array_unique($roles));
    }

    /**
     * eraseCredentials() should wipe sensitive temporary data.
     * Here it resets password to '' (see implementation).
     *
     * @throws Exception
     */
    public function testEraseCredentials(): void
    {
        $password = self::$faker->password(minLength: 20);
        self::$usuario->setPassword($password);

        self::assertSame($password, self::$usuario->getPassword());

        self::$usuario->eraseCredentials();

        self::assertSame('', self::$usuario->getPassword());
    }

    /**
     * jsonSerialize() must return the expected keys and values.
     *
     * @throws Exception
     */
    public function testJsonSerialize(): void
    {
        $email = self::$faker->email();
        $roles = [ 'ROLE_ADMIN' ];

        $user = new User(email: $email, password: 'hashed', roles: $roles);

        $data = $user->jsonSerialize();

        self::assertArrayHasKey('Id', $data);
        self::assertArrayHasKey(User::EMAIL_ATTR, $data);
        self::assertArrayHasKey(User::ROLES_ATTR, $data);

        self::assertSame($email, $data[User::EMAIL_ATTR]);
        
        self::assertContains('ROLE_ADMIN', $data[User::ROLES_ATTR]);
        self::assertContains('ROLE_USER', $data[User::ROLES_ATTR]);

        self::assertSame(0, $data['Id']);
    }

    /**
     * createFromPayload() must build a JWT user with id/email/roles from payload.
     */
    public function testCreateFromPayload(): void
    {
        $email = self::$faker->email();
        $payload = [
            'id' => 123,
            'roles' => [ 'ROLE_ADMIN' ],
        ];

        $user = User::createFromPayload($email, $payload);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(123, $user->getId());
        self::assertSame($email, $user->getEmail());
        self::assertSame($email, $user->getUserIdentifier());

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles()); 
    }
}
