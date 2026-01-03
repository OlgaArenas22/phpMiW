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

        // 401
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $postData = $request->getPayload();

        // 422 si falta "result" o viene vacío
        if (!$postData->has('result') || $postData->get('result') === null || $postData->get('result') === '') {
            return Utils::errorMessage(Response::HTTP_UNPROCESSABLE_ENTITY, null, $format);
        }

        if (!is_numeric($postData->get('result'))) {
            return Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'BAD REQUEST: invalid result', $format);
        }

        /** @var User $me */
        $me = $this->getUser();

        $result = new Result();
        $result->setUser($me);
        $result->setResult((int) $postData->get('result'));

        if ($postData->has('time') && !empty($postData->get('time'))) {
            try {
                $dt = new \DateTimeImmutable(strval($postData->get('time')));
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

        $this->entityManager->remove($result);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }
}
