<?php

namespace App\Libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GuzzleLibrary
{

    protected $oClient;

    public function __construct(Client $oClient)
    {
        $this->oClient = $oClient;
    }

    /**
     * Call an API using Guzzle
     *
     * @param       $sRequestType
     * @param       $sUri
     * @param array $aRequest
     * @return array
     */
    public function request($sRequestType, $sUri, $aRequest = [])
    {
        try {
            $oResponse = $this->oClient->request($sRequestType, $sUri, $aRequest);
            $mResponse = [
                'mData'     => json_decode($oResponse->getBody()->getContents())
            ];
        } catch (GuzzleException $oException) {
            $mResponse = [
                'mCode'      => $oException->getCode(),
                'sMessage' => $oException->getMessage()
            ];
        }
        return $mResponse;
    }
}
