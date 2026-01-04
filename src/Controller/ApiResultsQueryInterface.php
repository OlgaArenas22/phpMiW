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

        /**
     * **GET TOP** Action
     * Summary: Retrieves the top results (highest values).
     *
     * Query params:
     * - userId (optional): filter by user (admin only if different from current user)
     * - limit (optional): max number of results (default 10)
     */
    public function topAction(Request $request): Response;

    /**
     * **OPTIONS TOP** Action
     * Summary: Provides the list of HTTP supported methods for /results/top
     */
    public function optionsTopAction(): Response;
    
    /**
     * **GET STATS** Action
     * Summary: Retrieves statistics for results (count, min, max, avg).
     *
     * Query params:
     * - userId (optional): filter by user (admin only if different from current user)
     */
    public function statsAction(Request $request): Response;

    /**
     * **OPTIONS STATS** Action
     * Summary: Provides the list of HTTP supported methods for /results/stats
     */
    public function optionsStatsAction(): Response;

}
