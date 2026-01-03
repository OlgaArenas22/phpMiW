<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

interface ApiResultsCommandInterface
{
    /**
     * **POST** action
     * Summary: Creates a Result resource.
     *
     * @param Request $request request
     */
    public function postAction(Request $request): Response;

    /**
     * **DELETE** Action
     * Summary: Removes the Result resource.
     *
     * @param Request $request request
     * @param int $resultId Result id
     */
    public function deleteAction(Request $request, int $resultId): Response;
}
