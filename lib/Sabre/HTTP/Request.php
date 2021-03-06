<?php

namespace Sabre\HTTP;

/**
 * The Request class represents a single HTTP request.
 *
 * You can either simply construct the object from scratch, or if you would
 * like to create the request from the $_SERVER array, use the
 * createFromServerArray static method.
 *
 * @copyright Copyright (C) 2009-2013 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Request extends Message implements RequestInterface {

    /**
     * HTTP Method
     *
     * @var string
     */
    protected $method;

    /**
     * Request Url
     *
     * @var string
     */
    protected $url;

    /**
     * Creates the request object
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param resource $body
     */
    public function __construct($method = null, $url = null, array $headers = null, $body = null) {

        if (!is_null($method))      $this->setMethod($method);
        if (!is_null($url))         $this->setUrl($url);
        if (!is_null($headers))     $this->setHeaders($headers);
        if (!is_null($body))        $this->setBody($body);

    }

    /**
     * This static method will create a new Request object, based on the
     * current PHP request.
     *
     * @param resource $body
     * @return Request
     */
    static public function createFromPHPRequest() {

        $r = self::createFromServerArray($_SERVER);
        $r->setBody(fopen('php://input','r'));
        $r->setPostData($_POST);
        return $r;

    }

    /**
     * This static method will create a new Request object, based on a PHP
     * $_SERVER array.
     *
     * @param array $serverArray
     * @param resource $body
     * @return Request
     */
    static public function createFromServerArray(array $serverArray) {

        $headers = array();
        $method = null;
        $url = null;
        $httpVersion = '1.1';

        $protocol = 'http';
        $hostName = 'localhost';

        foreach($serverArray as $key=>$value) {

            switch($key) {

                case 'SERVER_PROTOCOL' :
                    if ($value==='HTTP/1.0') {
                        $httpVersion = '1.0';
                    }
                    break;
                case 'REQUEST_METHOD' :
                    $method = $value;
                    break;
                case 'REQUEST_URI' :
                    $url = $value;
                    break;

                // These sometimes should up without a HTTP_ prefix
                case 'CONTENT_TYPE' :
                    $headers['Content-Type'] = $value;
                    break;
                case 'CONTENT_LENGTH' :
                    $headers['Content-Length'] = $value;
                    break;

                // mod_php on apache will put credentials in these variables.
                // (fast)cgi does not usually do this, however.
                case 'PHP_AUTH_USER' :
                    if (isset($serverArray['PHP_AUTH_PW'])) {
                        $headers['Authorization'] = 'Basic ' . base64_encode($value . ':' . $serverArray['PHP_AUTH_PW']);
                    }
                    break;

                // Similarly, mod_php may also screw around with digest auth.
                case 'PHP_AUTH_DIGEST' :
                    $headers['Authorization'] = 'Digest ' . $value;
                    break;

                // Apache may prefix the HTTP_AUTHORIZATION header with
                // REDIRECT_, if mod_rewrite was used.
                case 'REDIRECT_HTTP_AUTHORIZATION' :
                    $headers['Authorization'] = $value;
                    break;

                case 'HTTP_HOST' :
                    $hostName = $value;
                    $headers['Host'] = $value;
                    break;

                case 'HTTPS' :
                    if (!empty($value) && $value!=='off') {
                        $protocol = 'https';
                    }
                    break;

                default :
                    if (substr($key,0,5)==='HTTP_') {
                        // It's a HTTP header

                        // Normalizing it to be prettier
                        $header = strtolower(substr($key,5));

                        // Transforming dashes into spaces, and uppercasing
                        // every first letter.
                        $header = ucwords(str_replace('_', ' ', $header));

                        // Turning spaces into dashes.
                        $header = str_replace(' ', '-', $header);
                        $headers[$header] = $value;

                    }
                    break;


            }

        }

        $r = new self($method, $url, $headers);
        $r->setHttpVersion($httpVersion);
        $r->setRawServerData($serverArray);
        $r->setAbsoluteUrl($protocol . '://' . $hostName . $url);
        return $r;

    }

    /**
     * Returns the current HTTP method
     *
     * @return string
     */
    public function getMethod() {

        return $this->method;

    }

    /**
     * Sets the HTTP method
     *
     * @param string $method
     * @return void
     */
    public function setMethod($method) {

        $this->method = $method;

    }

    /**
     * Returns the request url.
     *
     * @return string
     */
    public function getUrl() {

        return $this->url;

    }

    /**
     * Sets the request url.
     *
     * @param string $url
     * @return void
     */
    public function setUrl($url) {

        $this->url = $url;

    }

    /**
     * Returns the list of query parameters.
     *
     * This is equivalent to PHP's $_GET superglobal.
     *
     * @return array
     */
    public function getQueryParameters() {

        $url = $this->getUrl();
        if (($index = strpos($url,'?'))===false) {
            return [];
        } else {
            parse_str(substr($url, $index+1), $queryParams);
            return $queryParams;
        }

    }

    /**
     * Sets the absolute url.
     *
     * @param string $url
     * @return void
     */
    public function setAbsoluteUrl($url) {

        $this->absoluteUrl = $url;

    }

    /**
     * Returns the absolute url.
     *
     * @return string
     */
    public function getAbsoluteUrl() {

        return $this->absoluteUrl;

    }

    /**
     * Base url
     *
     * @var string
     */
    protected $baseUrl = '/';

    /**
     * Sets a base url.
     *
     * This url is used for relative path calculations.
     *
     * @param string $url
     * @return void
     */
    public function setBaseUrl($url) {

        $this->baseUrl = $url;

    }

    /**
     * Returns the current base url.
     *
     * @return string
     */
    public function getBaseUrl() {

        return $this->baseUrl;

    }

    /**
     * Returns the relative path.
     *
     * This is being calculated using the base url. This path will not start
     * with a slash, so it will always return something like
     * 'example/path.html'.
     *
     * If the full path is equal to the base url, this method will return an
     * empty string.
     *
     * This method will also urldecode the path, and if the url was incoded as
     * ISO-8859-1, it will convert it to UTF-8.
     *
     * If the path is outside of the base url, a LogicException will be thrown.
     *
     * @return string
     */
    public function getPath() {

        // Removing duplicated slashes.
        $uri = str_replace('//','/',$this->getUrl());

        if (strpos($uri,$this->getBaseUrl())===0) {

            // We're not interested in the query part (everything after the ?).
            list($uri) = explode('?', $uri);
            return trim(URLUtil::decodePath(substr($uri,strlen($this->getBaseUrl()))),'/');

        // A special case, if the baseUri was accessed without a trailing
        // slash, we'll accept it as well.
        } elseif ($uri.'/' === $this->getBaseUrl()) {

            return '';

        } else {

            throw new \LogicException('Requested uri (' . $this->getUrl() . ') is out of base uri (' . $this->getBaseUrl() . ')');

        }
    }

    /**
     * Equivalent of PHP's $_POST.
     *
     * @var array
     */
    protected $postData = [];

    /**
     * Sets the post data.
     *
     * This is equivalent to PHP's $_POST superglobal.
     *
     * This would not have been needed, if POST data was accessible as
     * php://input, but unfortunately we need to special case it.
     *
     * @param array $postData
     * @return void
     */
    public function setPostData(array $postData) {

        $this->postData = $postData;

    }

    /**
     * Returns the POST data.
     *
     * This is equivalent to PHP's $_POST superglobal.
     *
     * @return array
     */
    public function getPostData() {

        return $this->postData;

    }

    /**
     * An array containing the raw _SERVER array.
     *
     * @var array
     */
    protected $rawServerData;

    /**
     * Returns an item from the _SERVER array.
     *
     * If the value does not exist in the array, null is returned.
     *
     * @param string $valueName
     * @return string|null
     */
    public function getRawServerValue($valueName) {

        if (isset($this->rawServerData[$valueName])) {
            return $this->rawServerData[$valueName];
        }

    }

    /**
     * Sets the _SERVER array.
     *
     * @param array $data
     * @return void
     */
    public function setRawServerData(array $data) {

        $this->rawServerData = $data;

    }
}
