<?php

// Merged into the Laravel connector's "porro" config, so it sits alongside
// the key and URL and a published config/porro.php can set it too.
return [

    // Porro members can sign in to this site's control panel from the site
    // page in Porro, once "Sign in to client sites" is on for the workspace.
    // Set PORRO_SIGN_IN=false to turn it off here, whatever Porro says.
    'sign_in' => env('PORRO_SIGN_IN', true),

];
