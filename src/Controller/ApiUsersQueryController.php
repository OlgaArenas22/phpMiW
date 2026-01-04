<?php

namespace App\Controller;

use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

/**
 * Class ApiUsersController
 *
 * @package App\Controller
 */
#[Route(
    path: ApiUsersQueryInterface::RUTA_API,
    name: 'api_users_'
)]
class ApiUsersQueryController extends AbstractController implements ApiUsersQueryInterface
{
    private const string HEADER_CACHE_CONTROL = 'Cache-Control';
    private const string HEADER_ETAG = 'ETag';
    private const string HEADER_ALLOW = 'Allow';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @see ApiUsersQueryInterface::cgetAction()
     *
     * @throws JsonException
     */
    #[Route(
        path: ".{_format}/{sort?id}",
        name: 'cget',
        requirements: [
            'sort' => "id|email|roles",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json', 'sort' => 'id' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function cgetAction(Request $request): Response
    {
        $format = Utils::getFormat($request);
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage( // 401
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $order = strval($request->attributes->get('sort'));
        $users = $this->entityManager
            ->getRepository(User::class)
            ->findBy([], [ $order => 'ASC' ]);

        // No hay usuarios?
        // @codeCoverageIgnoreStart
        if (empty($users)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);    // 404
        }
        // @codeCoverageIgnoreEnd

        // Caching with ETag
        $etag = md5((string) json_encode($users, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) && (in_array($etag, $etags) || in_array('*', $etags))) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET))
                ? [ 'users' => array_map(fn ($u) =>  ['user' => $u], $users) ]  // GET method
                : null, // HEAD method
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * @see ApiUsersQueryInterface::getAction()
     *
     * @throws JsonException
     */
    #[Route(
        path: "/{userId}.{_format}",
        name: 'get',
        requirements: [
            "userId" => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function getAction(Request $request, int $userId): Response
    {
        $format = Utils::getFormat($request);
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage( // 401
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (!$user instanceof User) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);    // 404
        }

        // Caching with ETag (password included)
        $etag = md5(json_encode($user, JSON_THROW_ON_ERROR) . $user->getPassword());
        if (($etags = $request->getETags()) && (in_array($etag, $etags) || in_array('*', $etags))) {
                return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET))
                ? [ User::USER_ATTR => $user ]  // GET method
                : null,                         // HEAD method
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * @see ApiUsersQueryInterface::optionsAction()
     */
     #[Route(
        path: "/{userId}.{_format}",
        name: 'options',
        requirements: [
            'userId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ 'userId' => 0, '_format' => 'json' ],
        methods: [ Request::METHOD_OPTIONS ],
    )]
    public function optionsAction(int|null $userId): Response
    {
        $methods = $userId !== 0
            ? [ Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE ]
            : [ Request::METHOD_GET, Request::METHOD_POST ];
        $methods[] = Request::METHOD_OPTIONS;

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                self::HEADER_ALLOW => implode(',', $methods),
                self::HEADER_CACHE_CONTROL => 'public, inmutable'
            ]
        );
    }

    /**
     * @throws \JsonException
     */
    #[Route(
        path: "/best.{_format}",
        name: 'best',
        requirements: [ '_format' => "json|xml" ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function bestAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        // 401
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        // 403 
        if (!$this->isGranted('ROLE_ADMIN')) {
            return Utils::errorMessage(
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN: you don\'t have permission to access',
                $format
            );
        }

        $repo = $this->entityManager->getRepository(\App\Entity\Result::class);
        $bestResults = $repo->findBestResultsGlobal();

        // 404 
        if (empty($bestResults)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $payload = [
            'bestResults' => array_map(
                fn ($r) => ['result' => $r],
                $bestResults
            ),
        ];

        // ETag
        $etag = md5(json_encode($payload, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) &&
            (in_array($etag, $etags, true) || in_array('*', $etags, true))) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET)) ? $payload : null,
            $format,
            [
                'Cache-Control' => 'private',
                'ETag' => $etag,
            ]
        );
    }

    #[Route(
        path: "/best.{_format}",
        name: 'options_best',
        requirements: [ '_format' => "json|xml" ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_OPTIONS ],
    )]
    public function optionsBestAction(): Response
    {
        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                'Allow' => implode(',', [
                    Request::METHOD_GET,
                    Request::METHOD_HEAD,
                    Request::METHOD_OPTIONS
                ]),
                'Cache-Control' => 'public, inmutable'
            ]
        );
    }
}
