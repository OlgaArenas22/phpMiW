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

#[Route(
    path: ApiResultsQueryInterface::RUTA_API,
    name: 'api_results_'
)]
class ApiResultsCommandController extends AbstractController implements ApiResultsCommandInterface
{
    private const string ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws JsonException
     */
    #[Route(
        path: ".{_format}",
        name: 'post',
        requirements: [
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [ Request::METHOD_POST ],
    )]
    public function postAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $postData = $request->getPayload();

        if (!$postData->has('result') || $postData->get('result') === null || $postData->get('result') === '') {
            return Utils::errorMessage(Response::HTTP_UNPROCESSABLE_ENTITY, null, $format);
        }

        if (!is_numeric($postData->get('result'))) {
            return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid result', $format);
        }

        /** @var User $authUser */
        $authUser = $this->getUser();

        $user = $this->entityManager->getRepository(User::class)->find($authUser->getId());
        if (!$user instanceof User) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $result = new Result();
        $result->setUser($user);
        $result->setResult((int) $postData->get('result'));

        if ($postData->has('time') && !empty($postData->get('time'))) {
            try {
                $dt = new \DateTime(strval($postData->get('time')));
                $result->setTime($dt);
            } catch (\Throwable) {
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid time', $format);
            }
        }

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ 'result' => $result ],
            $format,
            [
                'Location' => $request->getScheme() . '://' . $request->getHttpHost()
                    . ApiResultsQueryInterface::RUTA_API . '/' . $result->getId(),
            ]
        );
    }

    /**
     * @throws JsonException
     */
    #[Route(
        path: "/{resultId}.{_format}",
        name: 'put',
        requirements: [
            'resultId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [ Request::METHOD_PUT ],
    )]
    public function putAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

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

        if (!$result instanceof Result) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

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
        if (!$request->headers->has('If-Match') || $etag !== $request->headers->get('If-Match')) {
            return Utils::errorMessage(
                Response::HTTP_PRECONDITION_FAILED,
                'PRECONDITION FAILED: one or more conditions given evaluated to false',
                $format
            );
        }

        $postData = $request->getPayload();
        $updated = false;

        if ($postData->has('result')) {
            if ($postData->get('result') === null || $postData->get('result') === '' || !is_numeric($postData->get('result'))) {
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid result', $format);
            }
            $result->setResult((int) $postData->get('result'));
            $updated = true;
        }

        if ($postData->has('time')) {
            if (empty($postData->get('time'))) {
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid time', $format);
            }
            try {
                $dt = new \DateTime(strval($postData->get('time')));
                $result->setTime($dt);
                $updated = true;
            } catch (\Throwable) {
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid time', $format);
            }
        }

        if (!$updated) {
            return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: no data to update', $format);
        }

        $this->entityManager->flush();

        return Utils::apiResponse(
            209,
            [ 'result' => $result ],
            $format
        );
    }

    #[Route(
        path: "/{resultId}.{_format}",
        name: 'delete',
        requirements: [
            'resultId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [ Request::METHOD_DELETE ],
    )]
    public function deleteAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

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

        if (!$result instanceof Result) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

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

        $this->entityManager->remove($result);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }
}
