<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Mailjet\Api;

/**
 * Class MailjetClient
 * @package Mailjet\Api
 * @author Benjamin Perche <bperche@openstudio.com>
 */
class MailjetClient
{
    const RESOURCE_CONTACTS_LIST = "contactslist";
    const RESOURCE_CONTACT = "contact";
    const RESOURCE_LIST_RECIPIENT = "listrecipient";

    /**
     * @var resource
     */
    protected $curlHandler;

    protected $auth;
    protected $wsAddress;

    public function __construct($apiKey, $apiSecret, $wsAddress)
    {
        $this->wsAddress = $wsAddress;
        $this->auth = sprintf("%s:%s", $apiKey, $apiSecret);
    }

    protected function initilize($address)
    {
        /**
         * Initialize connection
         */
        $this->curlHandler = curl_init($address);
        curl_setopt_array($this->curlHandler, [
            CURLOPT_USERPWD => $this->auth,
            CURLOPT_RETURNTRANSFER => true,
        ]);
    }

    public function get($resource, $id = null, array $params = array())
    {
        $address = $this->lazyBuildAddress($resource, $id, $params);

        $this->initilize($address);
        return $this->getResponse();
    }

    public function post($resource, array $params = array())
    {
        $address = $this->lazyBuildAddress($resource, null, []);

        $this->initilize($address);
        $this->initializePostFields($params);

        curl_setopt($this->curlHandler, CURLOPT_POST, count($params));

        return $this->getResponse();
    }

    public function put($resource, $id, array $params = array())
    {
        $address = $this->lazyBuildAddress($resource, $id, []);

        $this->initilize($address);
        $this->initializePostFields($params);

        curl_setopt($this->curlHandler, CURLOPT_CUSTOMREQUEST, "PUT");

        return $this->getResponse();
    }

    public function delete($resource, $id, array $params = array())
    {
        $address = $this->lazyBuildAddress($resource, $id, $params);

        $this->initilize($address);

        curl_setopt($this->curlHandler, CURLOPT_CUSTOMREQUEST, "DELETE");

        return $this->getResponse();
    }

    protected function initializePostFields(array $params)
    {
        // sanitize
        $string = '';
        foreach ($params as $key => $value) {
            if ('' !== trim($value)) {
                $string .= $key . '=' . urlencode($value) . '&';
            }
        }

        $string = substr($string, 0, -1);
        curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS, $string);
    }

    protected function getResponse()
    {
        $response = curl_exec($this->curlHandler);
        $code = curl_getinfo($this->curlHandler, CURLINFO_HTTP_CODE);
    
        $error = curl_error($this->curlHandler);
        $errno = curl_errno($this->curlHandler);
    
        curl_close($this->curlHandler);
    
        return [$code, $response ?: $error . " (errno $errno)"];
    }

    protected function lazyBuildAddress($resource, $id = null, $params = array())
    {
        $address = sprintf("%s/%s", $this->wsAddress, $resource);

        if (null !== $id) {
            $address = sprintf("%s/%s", $address, $id);
        }

        $address .= "?";
        foreach ($params as $name => $value) {
            if ('' !== trim($value)) {
                $address .= $name . "=" . $value . "&";
            }
        }

        return $address;
    }
}
