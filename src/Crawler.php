<?php

namespace Ivebe\SitemapCrawler;

use Ivebe\SitemapCrawler\Contracts\ICrawler;

class Crawler implements ICrawler
{
    /**
     * Configuration array
     *
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $links = [];

    /**
     * @var array
     */
    private $images = [];

    /**
     * @var string
     */
    private $base;

    /**
     * Crawler constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct(string $base, array $config)
    {
        if (!isset($config['treat_trailing_slash_as_duplicate']) ||
           !isset($config['force_trailing_slash']) ||
           !isset($config['depth'])
        ) {
            throw new \Exception('Invalid config file. Please double check it.');
        }

        $this->config = $config;
        $this->base   = $base;
    }

    /**
     * Returns depth to which to follow links from base link.
     *
     * @return integer
     */
    public function getDepth()
    {
        return $this->config['depth'];
    }

    /**
     * Returns images from the given link.
     *
     * @return array|false
     */
    public function getImages($url)
    {
        if (isset($this->images[$url])) {
            return $this->images[$url];
        }
        return false;
    }

    /**
     * Fetch page over curl
     *
     * @param $url
     * @return mixed
     */
    private function fetch($url)
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 60,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_MAXREDIRS      => 10
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }


    /**
     * Parse links from HTML code
     *
     * @param $url string link to parse for other links
     */
    private function parse($url)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($this->fetch($url));

        $xPath = new \DOMXPath($dom);
        $elements = $xPath->query("//a/@href");

        foreach ($elements as $e) {
            $this->links[] = $e->nodeValue;
        }

        $images = $xPath->query("//img");
        $this->images[$url] = array();

        foreach ($images as $img) {
            $src      = $img->getAttribute('src');
            $data_src = $img->getAttribute('data-src');
            $alt      = $img->getAttribute('alt');

            if (!empty($data_src)) {
                $this->images[$url][] = array(
                    'src' => $this->rel2abs($data_src, $this->base),
                    'alt' => $alt
                );
            } else {
                $this->images[$url][] = array(
                    'src' => $this->rel2abs($src, $this->base),
                    'alt' => $alt
                );
            }
        }
    }

    /**
     * Filter duplicate links
     *
     * @param $baseURL root url from which other urls were collected
     */
    private function filter($baseURL)
    {
        //trim whitespaces
        $urls = array_map('trim', $this->links);

        $myHost   = parse_url($baseURL, PHP_URL_HOST);
        $myScheme = parse_url($baseURL, PHP_URL_SCHEME);

        $return = [];

        $img_exts = array("jpg", "jpeg", "png", "webp", "svg", "gif", "tiff", "tif");

        foreach ($urls as $k => $el) {
            // exclude links with hash (#)
            if (strpos($el, '#') !== false || strpos($el, 'mailto:') !== false || strpos($el, 'tel:') !== false) {
                continue;
            }

            // exclude images, which will be indexed separately
            if (in_array(pathinfo($el, PATHINFO_EXTENSION), $img_exts)) {
                continue;
            }

            //full link, no need to add anything. Just check if link is from the same domain
            if (substr($el, 0, 4) == 'http') {
                if ($myHost == parse_url($el, PHP_URL_HOST)) {
                    $return[] = $el;
                }

                continue;
            }

            //force current selected scheme in the sitemap file
            if (substr($el, 0, 2) == '//') {
                $return[] = $myScheme . ':' . $el;

                continue;
            } //absolute path links
            elseif (isset($el[0]) && $el[0] == '/') {
                $return[] = $myScheme . '://' . $myHost . $el;

                continue;
            }


            $return[] = $myScheme . '://' . $myHost . '/' . $el;
        }

        $this->links = $return;
    }

    /**
     * Crawl given url and return links fetched.
     *
     * @param $url
     * @return array
     */
    public function process($url)
    {
        $this->links        = [];
        $this->images[$url] = [];

        $this->parse($url);
        $this->filter($url);

        return $this->links;
    }

    /**
     * convert relative url to absolute
     *
     * @param string $rel   the url to convert
     * @param string $base  the url of the origin file
     *
     * @return the absolute url
     */
    private function rel2abs($rel, $base)
    {
        // remove beginning & ending quotes
        $rel = preg_replace('`^([\'"]?)([^\'"]+)([\'"]?)$`', '$2', $rel);

        // parse base URL  and convert to local variables: $scheme, $host,  $path
        extract(parse_url($base));

        if (!isset($path)) {
            $path = '';
        }

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');

        if (strpos($rel, "//") === 0) {
            return $scheme . ':' . $rel;
        }

        // return if already absolute URL
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        // queries and anchors
        if (isset($rel[0]) && ($rel[0] == '#' || $rel[0] == '?')) {
            return $base . $rel;
        }

        // remove non-directory element from path
        $path = preg_replace('#/[^/]*$#', '', $path);

        // destroy path if relative url points to root
        if (isset($rel[0]) && $rel[0] ==  '/') {
            $path = '';
        }

        // dirty absolute URL
        $abs = $host . $path . "/" . $rel;

        // replace '//' or  '/./' or '/foo/../' with '/'
        $abs = preg_replace("/(\/\.?\/)/", "/", $abs);
        $abs = preg_replace("/\/(?!\.\.)[^\/]+\/\.\.\//", "/", $abs);

        // absolute URL is ready!
        return $scheme . '://' . $abs;
    }
}
