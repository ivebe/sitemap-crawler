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
     * @var boolean
     */
    private $show_results;
    /**
     * @var array
     */
    private $search_engines;
    /**
     * yahoo app id for yahoo submission
     * @var string
     */
    private $yahoo_app_id;
    /**
     * @var array
     */
    private $submission_urls = array(
        'google' => 'http://www.google.com/ping?sitemap=SITEMAP_URL',
        'bing'   => 'http://www.bing.com/ping?sitemap=SITEMAP_URL',
        'yahoo'  => 'http://search.yahooapis.com/SiteExplorerService/V1/updateNotification?appid=YAHOO_APP_ID&url=SITEMAP_URL',
        'ask'    => 'http://submissions.ask.com/ping?sitemap=SITEMAP_URL'
    );
    /**
     * @var integer depth to which we will crawl
     */
    private $depth;

    public function __construct(ICrawler $crawler, ILinkCollection $collection, $url, $config)
    {
        $this->crawler        = $crawler;
        $this->collection     = $collection;
        $this->url            = $url;
        $this->show_results   = $config['show_results'];
        $this->search_engines = $config['search_engines_submission']['search_engines'];
        $this->yahoo_app_id   = $config['search_engines_submission']['yahoo_app_id'];

        $this->depth = $this->crawler->getDepth();

        if (isset($_POST['ajax_enabled']) && $_POST['ajax_enabled'] == 'true') {
            // show results auto-enabled if we use Ajax
            $this->show_results = true;
        }

        if ($this->show_results === true) {
            $this->disableOb();
        }
    }

    /**
     * Add all links to collection, if they are not added already
     *
     * @param $links
     */
    private function bulkAdd($links)
    {
        foreach ($links as $link) {
            if (!$this->collection->exists($link)) {
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

        if ($depth > 0) {
            while ($depth > 0) {
                foreach ($this->collection->links as $k => $link) {
                    if (!$this->collection->isCrawled($k)) {
                        $links = $this->crawler->process($link['url']);
                        $this->bulkAdd($links);
                        $this->collection->links[$k]['crawled'] = true;

                        if ($this->show_results === true) {
                            echo $link['url'] . "\n";
                            echo str_pad("", 1024, " ");

                            @ob_flush();
                            @flush();
                        }
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

        foreach ($this->collection->links as $link) {
            $output .= '<url>' . PHP_EOL;
            $output .= '<loc>' . $link['url'] . '</loc>' . PHP_EOL;
            $output .= '<changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
            $output .= '</url>' . PHP_EOL;
        }

        $output .= '</urlset>';

        if (!$saveToFile) {
            header('Content-type: text/xml');
            header('Content-Disposition: attachment; filename="sitemap.xml"');

            echo $output;
            return;
        }

        file_put_contents($saveToFile, $output);
    }

    /**
     * @param String $url
     */
    public function submitSiteMap($sitemap_url)
    {
        if ($this->show_results === true) {
            echo '-- submission-results--' . "\n";
            echo str_pad("", 1024, " ");

            @ob_flush();
            @flush();
        }

        foreach ($this->search_engines as $engine_name) {
            $submission_url = $this->submission_urls[$engine_name];
            $find           = array('SITEMAP_URL', 'YAHOO_APP_ID');
            $replace        = array($sitemap_url, $this->yahoo_app_id);
            $submission_url = urlencode(str_replace($find, $replace, $sitemap_url));

            $returnCode     = $this->submit($submission_url);

            if ($this->show_results === true) {
                echo '<div class="submission-results">';
                echo '<h4>' . ucfirst($engine_name) . '</h4>';
                if ($returnCode != 200) {
                    echo '<p>Error ' . $returnCode . ': ' . $sitemap_url . '</p>';
                } else {
                    echo '<p>Sitemap successfully sent to ' . ucfirst($engine_name) . ': ' . $sitemap_url . '</p>';
                }
                echo '</div>';
                echo '<hr>';

                echo str_pad("", 1024, " ");

                @ob_flush();
                @flush();
            }
        }
    }

    /**
     * cUrl handler to ping the Sitemap submission URLs for Search Engines…
     * @param String $url
     */
    private function submit($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode;
    }

    /**
     * Turn off output buffering
     */
    private function disableOb()
    {
        // Turn off output buffering
        @ini_set('output_buffering', 'off');
        // Turn off PHP output compression
        @ini_set('zlib.output_compression', 0);
        // Implicitly flush the buffer(s)
        @ini_set('implicit_flush', 1);
        @ob_implicit_flush(1);
        // Clear, and turn off output buffering
        while (ob_get_level() > 0) {
            // Get the curent level
            $level = ob_get_level();
            // End the buffering
            ob_end_clean();
            // If the current level has not changed, abort
            if (ob_get_level() == $level) {
                break;
            }
        }
        // Disable apache output buffering/compression
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
            @apache_setenv('dont-vary', 1);
        }
    }
}
