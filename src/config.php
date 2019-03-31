<?php
/**
 * - treat_trailing_slash_as_duplicate
 *   links like http://sample.com/home and http://sample.com/home/ will be treated as same link
 *
 * - force_trailing_slash
 *   if treat_trailing_slash_as_duplicate is set to true, determine which link should take precedence
 *
 * - depth
 *   how many links will we crawl in depth
 *
 * - ignore_nofollow
 *   option to avoid nofollow links
 */

$config = array(
    'crawler' => array(
        'treat_trailing_slash_as_duplicate' => true,
        'force_trailing_slash'              => false,
        'depth'                             => 2,
        'ignore_nofollow'                   => true
    ),
    'sitemap_service' => array(
        'show_results' => true
    ),
    'search_engines_submission' => array(
        'enabled' => true,
        'search_engines' => array(
            'google',
            'bing',
            'yahoo',
            'ask'
        ),
        // get your yahoo APP_ID here: https://developer.yahoo.com/
        'yahoo_app_id' => 'your-yahoo-app-id'
    )
);

return $config;
