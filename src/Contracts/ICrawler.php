<?php

namespace Ivebe\SitemapCrawler\Contracts;

interface ICrawler
{
    public function getDepth();
    public function process($url);
}