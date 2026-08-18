<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Password
    |--------------------------------------------------------------------------
    |
    | Applied when an admin creates a user through POST /user, where no
    | password is submitted. Self-registration through /auth/register always
    | carries its own password and never falls back to this.
    |
    */

    'default_password' => env('USER_DEFAULT_PASSWORD', 'Secret123!'),

];
