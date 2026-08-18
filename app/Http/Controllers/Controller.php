<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 12's stub ships an empty base controller, so `$this->authorize()`
    // is unavailable until this is pulled in. Needed for model-level checks
    // that route middleware cannot express — see BranchPolicy.
    use AuthorizesRequests;
}
