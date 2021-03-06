<?php

namespace Sabre\HTTP;

/**
 * This class represents a single HTTP response.
 *
 * @copyright Copyright (C) 2009-2013 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Response extends Message implements ResponseInterface {

    /**
     * This is the list of currently registered HTTP status codes.
     *
     * @var array
     */
    static public $statusCodes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authorative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status', // RFC 4918
        208 => 'Already Reported', // RFC 5842
        226 => 'IM Used', // RFC 3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        400 => 'Bad request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot', // RFC 2324
        422 => 'Unprocessable Entity', // RFC 4918
        423 => 'Locked', // RFC 4918
        424 => 'Failed Dependency', // RFC 4918
        426 => 'Upgrade required',
        428 => 'Precondition required', // RFC 6585
        429 => 'Too Many Requests', // RFC 6585
        431 => 'Request Header Fields Too Large', // RFC 6585
        451 => 'Unavailable For Legal Reasons', // draft-tbray-http-legally-restricted-status
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage', // RFC 4918
        508 => 'Loop Detected', // RFC 5842
        509 => 'Bandwidth Limit Exceeded', // non-standard
        510 => 'Not extended',
        511 => 'Network Authentication Required', // RFC 6585
    ];

    /**
     * HTTP status code
     *
     * @var string
     */
    protected $status;

    /**
     * Creates the response object
     *
     * @param string|int $status
     * @param array $headers
     * @param resource $body
     * @return void
     */
    public function __construct($status = null, array $headers = null, $body = null) {

        if (!is_null($status)) $this->setStatus($status);
        if (!is_null($headers)) $this->setHeaders($headers);
        if (!is_null($body)) $this->setBody($body);

    }


    /**
     * Returns the current HTTP status.
     *
     * This is the status-code as well as the human readable string.
     *
     * @return string
     */
    public function getStatus() {

        return $this->status;

    }

    /**
     * Sets the HTTP status code.
     *
     * This can be either the full HTTP status code with human readable string,
     * for example: "403 I can't let you do that, Dave".
     *
     * Or just the code, in which case the appropriate default message will be
     * added.
     *
     * @param string|int $status
     * @throws \InvalidArgumentExeption
     * @return void
     */
    public function setStatus($status) {

        if (ctype_digit($status) || is_int($status)) {

            $statusMessage = isset(self::$statusCodes[$status])?self::$statusCodes[$status]:'Unknown';
            $status = $status . ' ' . $statusMessage;

        }
        if ((int)$status < 100 || (int)$status>999) {
            throw new \InvalidArgumentException('The HTTP status code must be exactly 3 digits');
        }

        $this->status = $status;

    }

    /**
     * Sends the HTTP response back to a HTTP client.
     *
     * This calls php's header() function and streams the body to php://output.
     *
     * @return void
     */
    public function send() {

        header('HTTP/' . $this->httpVersion . ' ' . $this->status);
        foreach($this->headers as $key=>$value) {

            header($key . ': ' . $value);

        }
        file_put_contents('php://output', $this->body);

    }

}
