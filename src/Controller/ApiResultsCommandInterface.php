<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

interface ApiResultsCommandInterface
{
    /**
     * **DELETE** Action
     * Summary: Removes the Result resource.
     *
     * @param Request $request request
     * @param int $resultId Result id
     */
    public function deleteAction(Request $request, int $resultId): Response;
}
