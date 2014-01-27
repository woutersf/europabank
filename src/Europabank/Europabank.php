<?php

/**
 * PHP class for connecting to the Europabank MPI
 *
 * @link    https://www.ebonline.be/test/
 * @version 0.0.1
 */
class Europabank
{
    /**
     * @var null|string
     */
    private $server = "https://www.ebonline.be/test/mpi/authenticate";

    /**
     * @var null
     */
    private $uid;
    /**
     * @var null
     */
    private $serverSecret;
    /**
     * @var null
     */
    private $clientSecret;

    /**
     * @var
     */
    private $errorCode;
    /**
     * @var
     */
    private $errorString;

    /**
     * @param null $server
     * @param null $uid
     * @param null $serverSecret
     * @param null $clientSecret
     *
     * @throws InvalidArgumentException
     */
    public function __construct($server = null, $uid = null, $serverSecret = null, $clientSecret = null)
    {
        if (!is_null($server)) {
            $this->server = $server;
        }

        if (!is_null($uid)) {
            $this->uid = $uid;
        }

        if (!is_null($serverSecret)) {
            $this->serverSecret = $serverSecret;
        }

        if (!is_null($clientSecret)) {
            $this->clientSecret = $clientSecret;
        }

        if (empty($this->server) || empty($this->uid) || empty($this->serverSecret) || empty($this->clientSecret)) {
            throw new InvalidArgumentException("Server, uid, server secret and client secret are required");
        }
    }

    /**
     * @param $data
     * @param $xml
     */
    private function _arrayToXml($data, SimpleXMLElement &$xml)
    {
        foreach ($data as $key => $value) {
            if (!is_array($key) && $key == "attributes") {
                foreach ($value as $attr_key => $attr_value) {
                    $xml->addAttribute($attr_key, $attr_value);
                }
            } elseif (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild("{$key}");
                    $this->_arrayToXml($value, $subnode);
                } else {
                    $subnode = $xml->addChild("item{$key}");
                    $this->_arrayToXml($value, $subnode);
                }
            } else {
                $xml->addChild("{$key}", "{$value}");
            }

        }
    }
}

/**
 * Class APIException
 */
class APIException extends Exception
{
}