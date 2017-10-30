<?php

namespace app\commons;

use app\exceptions\SystemException;
use app\models\RespMsg;
use Curl\Curl;
use yii;

class HttpUtil
{
    /**
     * 发送一个GET请求
     * @param $url
     * @param $params
     * @param bool $assoc
     * @return RespMsg
     */
    public static function get($url, $params = null, $assoc = false)
    {
        return self::http($url, 'GET', $params, null, $assoc);
    }

    /**
     * 发送一个POST请求
     * @param $url
     * @param $params
     * @param $body
     * @param bool $assoc
     * @return RespMsg
     */
    public static function post($url, $params = null, $body = null, $assoc = false)
    {
        return self::http($url, 'POST', $params, $body, $assoc);
    }


    /**
     * 发送一个HTTP请求<br>
     * 向指定的链接发送一个HTTP请求
     * @param string $url 被请求链接
     * @param string $method 请求类型，默认“GET”
     * @param string $params 请求附加参数，支持数组或字符
     * @param string $body 请求附加参数，支持数组或字符
     * @param bool $assoc 对返回结果解析JSON对象还是数组
     * @return RespMsg 返回响应内容
     */
    public static function http($url, $method, $params, $body, $assoc)
    {
        $curl = new Curl();
        $requestUrl = $params ? $url . '?' . $params : $url;
        if ('GET' === $method) {
            $curl->get($requestUrl);
        } else if ('POST' === $method) {
            $curl->post($requestUrl, $body);
        }
        $curl->close();
        $respMsg = new RespMsg();
        //判断请求状态
        if ($curl->error) {
            Yii::error('请求错误：' . $requestUrl . ', ' . $body, __METHOD__);
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '请求对方服务异常';
            return $respMsg;
        }

        //判断业务处理状态
        $response = json_decode($curl->response, $assoc);
        if (!$response) {
            Yii::error('调用接口：' . $requestUrl . '，参数：' . $body . '，json_decode解析异常：' . !$response
                . ', response:' . $curl->response, __METHOD__);
            $respMsg->return_code = RespMsg::FAIL;
            return $respMsg;
        }

        //判断业务状态
        if ($assoc) {
            $errCode = isset($response['errcode']) ? $response['errcode'] : 0;
        } else {
            $errCode = isset($response->errcode) ? $response->errcode : 0;
        }
        if ($errCode !== 0) {
            Yii::error('调用接口：' . $requestUrl . '，参数：' . $body . '，errcode:' . $errCode
                . ', 返回不正常信息：' . $curl->response, __METHOD__);
            //根据错误码获取中文说明
            $msgTxt = Yii::$app->params['weiChat']['interfaceResponseCode'][$errCode] ?? null;
            if ($msgTxt && $assoc) {
                $response['errmsg'] = $msgTxt;
            } else if ($msgTxt) {
                $response->errmsg = $msgTxt;
            }

            $respMsg->return_code = RespMsg::FAIL;
        }

        //返回请求结果
        $respMsg->return_msg = $response;
        return $respMsg;
    }

    /**
     * 微信表单数据上传
     * @param string $url 请求链接
     * @param array $postData 表单数据，如果有文件路径使用CURLFile对象
     * @return array 返回响应数据
     * @throws SystemException
     */
    public
    static function weiChatFormUpload(string $url, array $postData): array
    {
        $ch = curl_init();

        //Get the response from cURL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        //Set the Url
        curl_setopt($ch, CURLOPT_URL, $url);

        //Create a POST array with the file in it
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        // Execute the request
        $response = curl_exec($ch);
        if (!$error = curl_error($ch)) {
            curl_close($ch);
            $result = json_decode($response, true);
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                Yii::error('发送请求失败:' . $response, __METHOD__);
                throw new SystemException('发送请求失败:' . $response);
            }
            return $result;
        }

        curl_close($ch);
        throw new SystemException('微信表单数据上传异常:' . $error);
    }

    /**
     * 下载远程文件到本地
     *
     * @param string $url 远程文件地址
     * @param string $fileSuffixName 文件后缀名称，需要包含小数点。
     * @return string 返回文件绝对路径
     * @throws \Exception
     */
    public
    static function downloadRemoteFile(string $url, string $fileSuffixName): string
    {
        $tmpFileName = Yii::getAlias('@webroot/upload/tmp_' . mt_rand() . $fileSuffixName);
        $file = fopen($tmpFileName, 'w+');
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FILE => $file,
            CURLOPT_TIMEOUT => 50,
            CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)'
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            throw new SystemException('Curl error: ' . curl_error($curl));
        }

        return $tmpFileName;
    }
}