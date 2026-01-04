<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

/**
 * Interface ApiUsersQueryInterface
 *
 * @package App\Controller
 *
 */
interface ApiUsersQueryInterface
{
    public final const string RUTA_API = '/api/v1/users';

    /**
     * **CGET** Action<br><br>
     * Summary: Retrieves the collection of User resources.<br>
     * _Notes_: Returns all users from the system that the user has access to.
     */
    public function cgetAction(Request $request): Response;

    /**
     * **GET** Action<br><br>
     * Summary: Retrieves a User resource based on a single ID.<br>
     * _Notes_: Returns the user identified by <code>userId</code>.
     *
     * @param int $userId User id
     */
    public function getAction(Request $request, int $userId): Response;

    /**
     * **OPTIONS** Action<br><br>
     * Summary: Provides the list of HTTP supported methods<br>
     * _Notes_: Return a <code>Allow</code> header with a list of HTTP supported methods.
     *
     * @param  int|null $userId User id
     */
    public function optionsAction(?int $userId): Response;

    /**
     * **GET BEST** Action
     * Summary: Retrieves the user(s) with the highest result.
     *
     * Only ROLE_ADMIN.
     */
    public function bestAction(Request $request): Response;

    /**
     * **OPTIONS BEST** Action
     * Summary: Provides the list of HTTP supported methods for /users/best
     */
    public function optionsBestAction(): Response;
}
