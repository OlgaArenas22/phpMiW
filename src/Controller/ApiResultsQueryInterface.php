<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

/**
 * Interface ApiResultsQueryInterface
 *
 * @package App\Controller
 */
interface ApiResultsQueryInterface
{
    public final const string RUTA_API = '/api/v1/results';

    /**
     * **CGET** Action
     * Summary: Retrieves the collection of Result resources.
     */
    public function cgetAction(Request $request): Response;

    /**
     * **GET** Action
     * Summary: Retrieves a Result resource based on a single ID.
     *
     * @param Request $request request
     * @param int $resultId Result id
     */
    public function getAction(Request $request, int $resultId): Response;

    /**
     * **OPTIONS** Action
     * Summary: Provides the list of HTTP supported methods
     *
     * @param int|null $resultId Result id
     */
    public function optionsAction(?int $resultId): Response;
}
