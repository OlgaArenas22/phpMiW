<?php

namespace App\Controller;

use App\Entity\Result;
use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ResultRepository;


use function in_array;

#[Route(
    path: ApiResultsQueryInterface::RUTA_API,
    name: 'api_results_'
)]
class ApiResultsQueryController extends AbstractController implements ApiResultsQueryInterface
{
    private const string ROLE_ADMIN = 'ROLE_ADMIN';

    private const string HEADER_CACHE_CONTROL = 'Cache-Control';
    private const string HEADER_ETAG = 'ETag';
    private const string HEADER_ALLOW = 'Allow';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * GET /results (collection)
     *
     * @throws JsonException
     */
    #[Route(
        path: ".{_format}/{sort?id}",
        name: 'cget',
        requirements: [
            'sort' => "id|result|time",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json', 'sort' => 'id' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function cgetAction(Request $request): Response
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

        $order = strval($request->attributes->get('sort'));

        // Admin: ve todos / User: solo los suyos
        if ($this->isGranted(self::ROLE_ADMIN)) {
            $results = $this->entityManager
                ->getRepository(Result::class)
                ->findBy([], [ $order => 'ASC' ]);
        } else {
            /** @var User $me */
            $me = $this->getUser();
            $results = $this->entityManager
                ->getRepository(Result::class)
                ->findBy([ 'user' => $me ], [ $order => 'ASC' ]);
        }

        // Si no hay resultados -> 404
        if (empty($results)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $etag = md5((string) json_encode($results, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) && (in_array($etag, $etags) || in_array('*', $etags))) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET))
                ? [ 'results' => array_map(fn ($r) => [ 'result' => $r ], $results) ]
                : null,
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * GET /results/{id} (item)
     *
     * @throws JsonException
     */
    #[Route(
        path: "/{resultId}.{_format}",
        name: 'get',
        requirements: [
            "resultId" => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function getAction(Request $request, int $resultId): Response
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

        /** @var Result|null $result */
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        // 404
        if (!$result instanceof Result) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        // 403 si no es admin y no es owner
        if (
            !$this->isGranted(self::ROLE_ADMIN)
            && ($result->getUser()?->getId() !== $this->getUser()?->getId())
        ) {
            return Utils::errorMessage(
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN: you don\'t have permission to access',
                $format
            );
        }

        $etag = md5((string) json_encode($result, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) && (in_array($etag, $etags) || in_array('*', $etags))) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET))
                ? [ 'result' => $result ]
                : null,
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * OPTIONS /results and /results/{id}
     */
    #[Route(
        path: "/{resultId}.{_format}",
        name: 'options',
        requirements: [
            'resultId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ 'resultId' => 0, '_format' => 'json' ],
        methods: [ Request::METHOD_OPTIONS ],
    )]
    public function optionsAction(int|null $resultId): Response
    {
        $methods = $resultId !== 0
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
        path: "/top.{_format}",
        name: 'top',
        requirements: [
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function topAction(Request $request): Response
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

        // limit (default 10)
        $limitRaw = $request->query->get('limit', '10');
        if (!is_numeric($limitRaw)) {
            return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid limit', $format);
        }
        $limit = (int) $limitRaw;
        if ($limit < 1 || $limit > 100) {
            return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid limit', $format);
        }

        // userId (optional)
        $userIdRaw = $request->query->get('userId');
        $requestedUserId = null;
        if (null !== $userIdRaw && $userIdRaw !== '') {
            if (!is_numeric($userIdRaw)) {
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid userId', $format);
            }
            $requestedUserId = (int) $userIdRaw;
        }

        /** @var User $me */
        $me = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Scope:
        // - USER: if userId missing -> own; if userId != me -> 403
        // - ADMIN: if userId missing -> global (null); if userId present -> that user
        $filterUserId = null;

        if ($isAdmin) {
            $filterUserId = $requestedUserId; // null => global
        } else {
            // normal user
            if (null === $requestedUserId) {
                $filterUserId = $me->getId();
            } else {
                if ($requestedUserId !== $me->getId()) {
                    return Utils::errorMessage(
                        Response::HTTP_FORBIDDEN,
                        'FORBIDDEN: you don\'t have permission to access',
                        $format
                    );
                }
                $filterUserId = $requestedUserId;
            }
        }

        /** @var ResultRepository $repo */
        $repo = $this->entityManager->getRepository(Result::class);
        $results = $repo->findTopResults($filterUserId, $limit);

        if (empty($results)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $etag = md5((string) json_encode($results, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) && (in_array($etag, $etags, true) || in_array('*', $etags, true))) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET))
                ? [ 'results' => array_map(fn ($r) => ['result' => $r], $results) ]
                : null,
            $format,
            [
                'Cache-Control' => 'private',
                'ETag' => $etag,
            ]
        );
    }

    #[Route(
        path: "/top.{_format}",
        name: 'options_top',
        requirements: [
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_OPTIONS ],
    )]
    public function optionsTopAction(): Response
    {
        $methods = [ Request::METHOD_GET, Request::METHOD_HEAD, Request::METHOD_OPTIONS ];

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                'Allow' => implode(',', $methods),
                'Cache-Control' => 'public, inmutable'
            ]
        );
    }

    /**
     * @throws \JsonException
     */
    #[Route(
        path: "/stats.{_format}",
        name: 'stats',
        requirements: [
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET, Request::METHOD_HEAD ],
    )]
    public function statsAction(Request $request): Response
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

        // userId (optional)
        $userIdRaw = $request->query->get('userId');
        $requestedUserId = null;
        if (null !== $userIdRaw && $userIdRaw !== '') {
            if (!is_numeric($userIdRaw)) {
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid userId', $format);
            }
            $requestedUserId = (int) $userIdRaw;
        }

        /** @var User $me */
        $me = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Scope:
        // - USER: if userId missing -> own; if userId != me -> 403
        // - ADMIN: if userId missing -> global (null); if userId present -> that user
        $filterUserId = null;

        if ($isAdmin) {
            $filterUserId = $requestedUserId; // null => global
        } else {
            if (null === $requestedUserId) {
                $filterUserId = $me->getId();
            } else {
                if ($requestedUserId !== $me->getId()) {
                    return Utils::errorMessage(
                        Response::HTTP_FORBIDDEN,
                        'FORBIDDEN: you don\'t have permission to access',
                        $format
                    );
                }
                $filterUserId = $requestedUserId;
            }
        }

        /** @var ResultRepository $repo */
        $repo = $this->entityManager->getRepository(Result::class);
        $stats = $repo->getStats($filterUserId);

        // Si no hay resultados en ese scope => 404 
        if (($stats['count'] ?? 0) === 0) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        // ETag caching
        $etag = md5((string) json_encode($stats, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) && (in_array($etag, $etags, true) || in_array('*', $etags, true))) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ($request->isMethod(Request::METHOD_GET))
                ? [ 'stats' => $stats ]
                : null,
            $format,
            [
                'Cache-Control' => 'private',
                'ETag' => $etag,
            ]
        );
    }

    #[Route(
        path: "/stats.{_format}",
        name: 'options_stats',
        requirements: [
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_OPTIONS ],
    )]
    public function optionsStatsAction(): Response
    {
        $methods = [ Request::METHOD_GET, Request::METHOD_HEAD, Request::METHOD_OPTIONS ];

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                'Allow' => implode(',', $methods),
                'Cache-Control' => 'public, inmutable'
            ]
        );
    }
}
