<?php

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

use Ivebe\SitemapCrawler\Crawler;
use Ivebe\SitemapCrawler\SitemapService;
use Ivebe\SitemapCrawler\LinkCollection;

$config = require "src/config.php";

$url = "http://www.google.com";

$crawler    = new Crawler($config);
$collection = new LinkCollection();
$provider   = new SitemapService($crawler, $collection);

$links = $provider->crawl($url);
$provider->export('monthly');

