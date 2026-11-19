<?php
// Copy this file to config.php and edit. Never commit config.php to a public repository.
return array(
    // Free key from https://4.dbt.io/api_key/request
    'api_key'           => 'PASTE-YOUR-KEY-HERE',
    'cache_hours'       => 12,
    // Optional: limit the country list, e.g. 'IN,NP,BD,BT,LK,MV'. Empty = all countries.
    'allowed_countries' => '',
    // '*' or a comma list of sites allowed to call this proxy, e.g. 'https://example.org,https://www.example.org'
    'allowed_origins'   => '*',
    // Requests per visitor per 5 minutes
    'rate_limit'        => 300,
    // Optional secret to clear the cache by visiting bbp-proxy.php?ep=flush&token=SECRET
    'flush_token'       => '',
    // Advanced: use a different API base (for a mirror or local testing). Leave empty for the official API.
    'api_base'          => '',
    'debug'             => false,
);
