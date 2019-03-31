<?php

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

use Ivebe\SitemapCrawler\Crawler;
use Ivebe\SitemapCrawler\SitemapService;
use Ivebe\SitemapCrawler\LinkCollection;

$config = require "src/config.php";

$url = "https://www.google.com";
/**
 * $dest:
 *      false if you want to download the generated sitemap
 *      'filename.xml' to save file on server
 */
$dest = __DIR__ . '/sitemap.xml';

/**
 * sitemap url for search engines submission
 */
$sitemap_url = 'https://www.google.com/sitemap.xml';

$crawler    = new Crawler($config['crawler']);
$collection = new LinkCollection();
$provider   = new SitemapService($crawler, $collection, $url, $config);

$links = $provider->crawl($url);

$provider->export('daily', $dest);

if ($config['search_engines_submission']['enabled'] === true) {
    $provider->SubmitSiteMap($sitemap_url);
}
