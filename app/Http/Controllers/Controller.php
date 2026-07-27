<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Extends the framework's base controller so that authorizeResource() has a
 * middleware() to register its `can:` middleware with -- the skeleton's empty
 * abstract class does not provide one.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
