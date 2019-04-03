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
    private $use_ajax;
    /**
     * @var boolean
     */
    private $show_results;
    /**
     * @var array
     */
    private $search_engines;
    /**
     * @var array
     */
    private $indexed_images;
    /**
     * @var array
     */
    private $submission_urls = array(
        'google' => 'http://www.google.com/ping?sitemap=SITEMAP_URL',
        'bing'   => 'http://www.bing.com/ping?sitemap=SITEMAP_URL'
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

        $this->depth = $this->crawler->getDepth();

        $this->use_ajax = false;

        if (isset($_POST['ajax_enabled']) && $_POST['ajax_enabled'] == 'true') {
            // show results auto-enabled if we use Ajax
            $this->use_ajax = true;
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

                if ($this->show_results === true) {
                    echo $link . "\n";
                    if ($this->use_ajax !== true) {
                        echo '<br>';
                    }
                    echo str_pad("", 1024, " ");

                    @ob_flush();
                    @flush();
                }
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
        $output .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $output .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $output .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $output .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

        $output .= PHP_EOL;

        $links = array();
        foreach ($this->collection->links as $link) {
            $links[] = $link['url'];
        }

        natsort($links);
        $links = array_values($links);

        // we don't want to repeat all images in all pages
        $this->indexed_images = array();
        foreach ($links as $link) {
            $images = $this->crawler->getImages($link);
            $output .= '<url>' . PHP_EOL;
            $output .= '    <loc>' . $link . '</loc>' . PHP_EOL;
            $output .= '    <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
            if ($images !== false) {
                foreach ($images as $img) {
                    if (!in_array($img['src'], $this->indexed_images)) {
                        $output .= '    <image:image>' . PHP_EOL;
                        $output .= '    <image:loc>' . $img['src'] . '</image:loc>' . PHP_EOL;
                        $output .= '    <image:caption>' . str_replace('&', '&amp;', $img['alt']) . '</image:caption>' . PHP_EOL;
                        $output .= '    </image:image>' . PHP_EOL;
                        $this->indexed_images[] = $img['src'];
                    }
                }
            }
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

        if ($this->show_results === true) {
            if ($this->use_ajax === true) {
                echo '--split--' . "\n";
                echo count($this->indexed_images);
            } else {
                echo '<hr>';
                echo '<p><strong>The crawler found ' . count($links) . ' URLS and ' . count($this->indexed_images) . ' images</strong></p>';
            }
            echo str_pad("", 1024, " ");

            @ob_flush();
            @flush();
        }
    }

    /**
     * @param String $url
     */
    public function submitSiteMap($sitemap_url)
    {
        if ($this->show_results === true) {
            echo '--split--' . "\n";
            echo '<h3>Search engines results</h3>';
            echo str_pad("", 1024, " ");

            @ob_flush();
            @flush();
        }

        foreach ($this->search_engines as $engine_name) {
            $submission_url = $this->submission_urls[$engine_name];
            $submission_url = str_replace('SITEMAP_URL', $sitemap_url, $submission_url);

            $returnCode     = $this->submit($submission_url);

            if ($this->show_results === true) {
                echo '<div class="submission-results">';
                echo '<h4>' . ucfirst($engine_name) . '</h4>';
                echo '<p>Sitemap URL: ' . $sitemap_url . '</p>';
                echo '<p>Submission URL: ' . $submission_url . '</p>';
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
        // header required when using PHP-FPM
        header('Content-Encoding: none');
        // Turn off output buffering
        ini_set('output_buffering', 'off');
        // Turn off PHP output compression
        ini_set('zlib.output_compression', 0);
        // Implicitly flush the buffer(s)
        ini_set('implicit_flush', 1);
        ob_implicit_flush(1);
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
        set_time_limit(0);
    }
}
