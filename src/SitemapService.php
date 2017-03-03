<?php

namespace Ivebe\SitemapCrawler;

use Ivebe\SitemapCrawler\Contracts\ICrawler;
use Ivebe\SitemapCrawler\Contracts\ILinkCollection;

class SitemapService
{
    /**
     * @var ICrawler
     */
    private $crawler;

    /**
     * @var ILinkCollection
     */
    private $collection;

    /**
     * @var integer depth to which we will crawl
     */
    private $depth;

    public function __construct(ICrawler $crawler, ILinkCollection $collection)
    {
        $this->crawler    = $crawler;
        $this->collection = $collection;

        $this->depth = $this->crawler->getDepth();
    }

    /**
     * Add all links to collection, if they are not added already
     *
     * @param $links
     */
    private function bulkAdd($links)
    {
        foreach($links as $link){
            if(!$this->collection->exists($link)) {
                $this->collection->add($link);
            }
        }
    }

    /**
     * Main entry point, from which we will fetch all links
     * in the provided depth
     *
     * @param $url
     * @return array links
     */
    public function crawl($url)
    {
        $links = $this->crawler->process($url);

        $this->bulkAdd($links);

        $depth = $this->depth;

        if($depth > 0)
        {
            while($depth > 0)
            {
                foreach($this->collection->links as $k => $link){
                    if(!$this->collection->isCrawled($k)){

                        $links = $this->crawler->process($link['url']);
                        $this->bulkAdd($links);
                        $this->collection->links[$k]['crawled'] = true;
                    }
                }

                $depth--;
            }
        }

        return $this->collection->links;
    }

    /**
     * Exporting sitemap.xml as file to download, or save on server.
     *
     * @param $changefreq string that will go to <changefreq> in the xml
     * @param bool $saveToFile
     */
    public function export($changefreq, $saveToFile = false)
    {
        $output  = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $output .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $output .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $output .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

        $output .= PHP_EOL;

        foreach($this->collection->links as $link){
            $output .= '<url>' . PHP_EOL;
            $output .= '<loc>' . $link['url'] . '</loc>' . PHP_EOL;
            $output .= '<changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
            $output .= '</url>' . PHP_EOL;
        }

        $output .= '</urlset>';

        if(!$saveToFile)
        {

            header('Content-type: text/xml');
            header('Content-Disposition: attachment; filename="sitemap.xml"');

            echo $output;
            return;
        }

        file_put_contents($saveToFile, $output);
    }
}