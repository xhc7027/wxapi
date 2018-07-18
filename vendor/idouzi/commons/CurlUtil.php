<?php

namespace Idouzi\Commons;

use Curl\Curl;
use Idouzi\Commons\Exceptions\HttpException;
use Idouzi\Commons\Models\RespMsg;
use yii;

class CurlUtil extends Curl
{
    public $httpBuildQuery = true;

    /**
     * @param array|object|string $data
     */
    protected function preparePayload($data)
    {
        $this->setOpt(CURLOPT_POST, true);

        if ($this->httpBuildQuery && (is_array($data) || is_object($data))) {
            $data = http_build_query($data);
        }

        $this->setOpt(CURLOPT_POSTFIELDS, $data);
    }

    public function setHttpBuildQuery(bool $httpBuildQuery)
    {
        $this->httpBuildQuery = $httpBuildQuery;

        return $this;
    }
}