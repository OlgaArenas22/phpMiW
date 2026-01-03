<?php

namespace App\Controller;

use App\Entity\Result;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
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
