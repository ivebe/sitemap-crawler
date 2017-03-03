<?php

namespace Ivebe\SitemapCrawler;

use Ivebe\SitemapCrawler\Contracts\ILinkCollection;

class LinkCollection implements ILinkCollection
{
    /**
     * Array of links fetched on all pages
     *
     * @var array
     */
    public $links;

    /**
     * Add only new links to the collection
     *
     * @param $url
     * @return bool
     */
    public function add($url)
    {
        $key = md5($url);

        if(isset($this->links[$key]))
            return false;

        $this->links[$key]['url']     = $url;
        $this->links[$key]['crawled'] = false;
        return true;
    }

    /**
     * Check if link is already in the collection
     *
     * @param $url
     * @return bool
     */
    public function exists($url)
    {
        return isset($this->links[ md5($url) ]);
    }

    /**
     * Do not crawl pages that were already crawled
     *
     * @param $key
     * @return mixed
     * @throws \Exception
     */
    public function isCrawled($key)
    {
        if(!isset($this->links[$key]))
            throw new \Exception('$key Key not set');

        return $this->links[$key]['crawled'];
    }
}
