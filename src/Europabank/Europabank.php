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
     * @param array $merchant
     * @param array $customer
     * @param array $transaction
     *
     * @return mixed
     * @throws APIException
     */
    public function authorize($merchant = array(), $customer = array(), $transaction = array())
    {
        // Build request
        $data = array(
          'Authorize' => array(
            'version'     => 1.1,
            'Merchant'    => array(),
            'Customer'    => array(),
            'Transaction' => array()
          )
        );

        // Prepare data
        $data['Authorize']['Merchant']    = $this->_parseMerchantData($merchant);
        $data['Authorize']['Customer']    = $this->_parseCustomerData($customer);
        $data['Authorize']['Transaction'] = $this->_parseTransactionData($transaction);

        // Calculate Hash
        if (
          isset($data['Authorize']['Merchant']['uid'])
          && $data['Authorize']['Transaction']['orderid']
          && $data['Authorize']['Transaction']['amount']
          && $data['Authorize']['Transaction']['description']
        ) {
            $data['Authorize']['hash'] = sha1(
              $data['Authorize']['Merchant']['uid']
              . $data['Authorize']['Transaction']['orderid']
              . $data['Authorize']['Transaction']['amount']
              . $data['Authorize']['Transaction']['description']
              . $this->clientSecret
            );
        }

        // Do call
        $result = $this->_executeCurl($data);

        // Process the result
        if (isset($result->Response->url)) {
            return (string)$result->Response->url;
        } else {
            throw new APIException($result->Error->errorCode . ": " . $result->Error->errorMessage . " " . $result->Error->errorDetail);
        }
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function _parseMerchantData($data)
    {
        $validParameters = array(
          'uid',
          'css',
          'template',
          'title',
          'beneficiary',
          'param',
          'redirecturl',
          'redirecttype',
          'feedbackurl',
          'feedbacktype',
          'feedbackemail'
        );

        // Automatically set Uid
        if (!isset($data['uid'])) {
            $data['uid'] = $this->uid;
        }

        // Unset parameters which are not valid for this section
        foreach ($data as $key => $value) {
            if (!in_array($key, $validParameters)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function _parseCustomerData($data)
    {
        $validParameters = array(
          'name',
          'country',
          'ip',
          'email',
          'language'
        );

        // Automatically set Uid
        if (!isset($data['uid'])) {
            $data['uid'] = $this->uid;
        }

        // Unset parameters which are not valid for this section
        foreach ($data as $key => $value) {
            if (!in_array($key, $validParameters)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function _parseTransactionData($data)
    {
        $validParameters = array(
          'brand',
          'orderid',
          'amount',
          'description',
        );

        // Automatically set Uid
        if (!isset($data['uid'])) {
            $data['uid'] = $this->uid;
        }

        // Unset parameters which are not valid for this section
        foreach ($data as $key => $value) {
            if (!in_array($key, $validParameters)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param array $postData
     *
     * @return SimpleXMLElement
     * @throws Exception
     */
    private function _executeCurl($postData)
    {
        // Build XML
        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><MPI_Interface></MPI_Interface>");
        $this->_arrayToXml($postData, $xml);
        $xml = $xml->asXML();

        // Set up curl connection
        $ch = curl_init($this->server);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Do request and get response
        $data = curl_exec($ch);
        $info = curl_getinfo($ch);

        // Close connection
        curl_close($ch);

        // Check if return code is in 2xx range
        if (floor($info['http_code'] / 100) != 2) {
            $this->errorCode   = $info['http_code'];
            $this->errorString = "Curl request failed";
            throw new Exception($this->errorCode . ": " . $this->errorString, $this->errorCode);
        }

        // Request failed
        if ($data === false) {
            $this->errorCode   = curl_errno($ch);
            $this->errorString = curl_error($ch);
            throw new Exception($this->errorCode . ": " . $this->errorString, $this->errorCode);
        }

        return simplexml_load_string($data);
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

    /**
     * @param $id
     * @param $merchant
     * @param $transaction
     *
     * @return array
     * @throws APIException
     */
    public function status($id, $merchant = array(), $transaction = array())
    {
        // Build request
        $data = array(
          'Status' => array(
            'version'     => 1.1,
            'Merchant'    => array(),
            'Transaction' => array()
          )
        );

        // Prepare data
        $data['Status']['Merchant']    = $this->_parseMerchantData($merchant);
        $data['Status']['Transaction'] = $this->_parseTransactionData($transaction);

        // Set id
        $data['Status']['attributes']['id'] = $id;

        // Calculate Hash
        if (
          isset($data['Status']['Merchant']['uid'])
          && $id
        ) {
            $data['Status']['hash'] = sha1(
              $data['Status']['Merchant']['uid']
              . $id
              . $this->clientSecret
            );
        }

        // Do call
        $result = $this->_executeCurl($data);

        // Process the result
        if (isset($result->Response)) {
            return $result->Response;
        } else {
            throw new APIException($result->Error->errorCode . ": " . $result->Error->errorMessage . " " . $result->Error->errorDetail);
        }
    }

    public function capture($id, $merchant = array(), $transaction = array())
    {
        // Build request
        $data = array(
          'Capture' => array(
            'version'     => 1.1,
            'Merchant'    => array(),
            'Transaction' => array()
          )
        );

        // Prepare data
        $data['Capture']['Merchant']    = $this->_parseMerchantData($merchant);
        $data['Capture']['Transaction'] = $this->_parseTransactionData($transaction);

        // Set id
        $data['Capture']['attributes']['id'] = $id;

        // Calculate Hash
        if (
          isset($data['Capture']['Merchant']['uid'])
          && $id
        ) {
            $data['Capture']['hash'] = sha1(
              $data['Capture']['Merchant']['uid']
              . $id
              . $this->clientSecret
            );
        }

        // Do call
        $result = $this->_executeCurl($data);

        // Process the result
        if (isset($result->Response)) {
            return $result->Response;
        } else {
            throw new APIException($result->Error->errorCode . ": " . $result->Error->errorMessage . " " . $result->Error->errorDetail);
        }
    }
}

/**
 * Class APIException
 */
class APIException extends Exception
{
}