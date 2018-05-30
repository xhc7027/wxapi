<?php

namespace Idouzi\Commons\QCloud;

use Idouzi\Commons\Exceptions\MqException;
use Idouzi\Commons\HttpUtil;
use Idouzi\Commons\Models\ReceiveMessage;
use Yii;

/**
 * <p>腾讯云消息服务</p>
 *
 * <p>包含操作消息服务的公共方法：发送消息、消费消息、删除消息</p>
 *
 * <code>
 * 发送消息
 * TencentQueueUtil::sendMessage('vote-user', json_encode(['userId' => '1001', 'userName' => 'zhang三']));
 * 批量发送消息
 * TencentQueueUtil::batchSendMessage('vote-user', ['hello', 'world', '!']);
 *
 * 接收消息
 * $model = TencentQueueUtil::receiveMessage('vote-user');
 * 删除消息，如果不删除可以被再次消费。
 * TencentQueueUtil::deleteMessage('vote-user', $model->receiptHandle);
 *
 * 批量接收消息
 * $messages = TencentQueueUtil::batchReceiveMessage('vote-user', 3);
 * $receipts = null;
 * foreach ($messages as $message) {
 *     $receipts[] = $message->receiptHandle;
 * }
 * 批量删除消息
 * TencentQueueUtil::batchDeleteMessage('vote-user', $receipts);
 * </code>
 *
 * @package Idouzi\Commons
 */
class TencentQueueUtil
{
    /**
     * 发送消息
     *
     * @param string $queueName 队列名字，在单个地域同一个帐号下必须唯一。队列名称是一个不超过64个字符的字符串，必须以字母为首字符，
     *                          剩余部分可以包含字母、数字和横划线(-)。
     *                          例如：“test-queue-1”
     * @param string $msgBody 消息正文。大小至少 1 Byte，最大长度受限于设置的队列消息最大长度属性。
     * @param int $delaySeconds 单位为秒，表示该消息发送到队列后，需要延时多久用户才可见该消息。
     * @return bool 发送成功返回true
     * @throws MqException
     */
    public static function sendMessage(string $queueName, string $msgBody, int $delaySeconds = 0): bool
    {
        return self::send('SendMessage', $queueName, $msgBody, $delaySeconds);
    }

    /**
     * 批量发送消息
     *
     * @param string $queueName 队列名字，在单个地域同一个帐号下必须唯一。队列名称是一个不超过64个字符的字符串，必须以字母为首字符，
     *                          剩余部分可以包含字母、数字和横划线(-)。
     *                          例如：“test-queue-1”
     * @param array $msgBody 消息正文。表示这一批量中的一条消息。目前批量消息数量不能超过 16 条，整个消息大小不超过 64k。
     * @param int $delaySeconds 单位为秒，表示该消息发送到队列后，需要延时多久用户才可见该消息。
     * @return bool 发送成功返回true
     * @throws MqException
     */
    public static function batchSendMessage(string $queueName, array $msgBody, int $delaySeconds = 0): bool
    {
        if (count($msgBody) > 16) {
            throw new MqException("批量消息数量不能超过 16 条");
        }

        $messages = [];
        $i = 0;
        foreach ($msgBody as $item => $value) {
            $messages['msgBody.' . $i] = $value;
            $i++;
        }

        return self::send('BatchSendMessage', $queueName, $messages, $delaySeconds);
    }

    /**
     * 发送消息
     *
     * @param  string $action 接口名称
     * @param string $queueName 队列名字
     * @param string|array $msgBody 消息正文
     * @param int $delaySeconds 单位为秒，表示该消息发送到队列后，需要延时多久用户才可见该消息。
     * @return bool 发送成功返回true
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    private static function send(string $action, string $queueName, $msgBody, int $delaySeconds = 0): bool
    {
        $data = [
            'queueName' => $queueName,
            'delaySeconds' => $delaySeconds,
            'Action' => $action,
            'Region' => Yii::$app->params['cmq']['region'],
            'SecretId' => Yii::$app->params['cmq']['secretId'],
            'Nonce' => mt_rand(1, 65535),
            'Timestamp' => time(),
            'SignatureMethod' => 'HmacSHA256',
        ];

        //判断是否是批量发送
        if ('BatchSendMessage' === $action) {
            $data = array_merge($data, $msgBody);
        } else {
            $data['msgBody'] = $msgBody;
        }

        $data = array_merge($data, self::signature($data, Yii::$app->params['cmq']['endPoint']));
        $response = HttpUtil::simplePost('http://' . Yii::$app->params['cmq']['endPoint'], $data);

        $responseJson = json_decode($response);
        if (isset($responseJson->code) && 0 !== $responseJson->code) {
            Yii::error('发送消息失败:' . $responseJson->code . $responseJson->message, __METHOD__);
            throw new MqException("发送消息错误:" . $responseJson->message);
        }

        return true;
    }

    /**
     * 签名
     *
     * @param array $data 要签名的数据
     * @param string $endPoint 组成签名的不完整域名
     * @return array 返回签名值
     */
    private static function signature(array $data, string $endPoint): array
    {
        //排序
        ksort($data);

        //按文档要求格式拼接字符串
        $srcStr = 'POST' . $endPoint . '?';
        foreach ($data as $key => $value) {
            $srcStr .= $key . '=' . $value . '&';
        }
        $srcStr = substr($srcStr, 0, strlen($srcStr) - 1);

        //生成签名
        $signStr = base64_encode(hash_hmac('sha256', $srcStr, Yii::$app->params['cmq']['secretKey'], true));

        return ['Signature' => $signStr];
    }

    /**
     * 消费消息，根据业务自身特点，可以选择指定 pollingWaitSeconds 的值。
     *
     * @param string $queueName 队列名字
     * @param int $pollingWaitSeconds 本次请求的长轮询等待时间。取值范围0-30秒，默认值0。
     * @return ReceiveMessage|null|[] 返回消息对象模型
     * @throws MqException
     */
    public static function receiveMessage(string $queueName, int $pollingWaitSeconds = 0)
    {
        return self::receive('ReceiveMessage', $queueName, 0, $pollingWaitSeconds);
    }

    /**
     * 批量消费消息，根据业务自身特点，可以选择指定 pollingWaitSeconds 的值。
     *
     * @param string $queueName 队列名字
     * @param int $numOfMsg 本次消费的消息数量。取值范围 1-16。
     * @param int $pollingWaitSeconds 本次请求的长轮询等待时间。取值范围0-30秒，默认值0。
     * @return ReceiveMessage|null|[] 返回多个消息对象模型
     * @throws MqException
     */
    public static function batchReceiveMessage(string $queueName, int $numOfMsg, int $pollingWaitSeconds = 0)
    {
        if ($numOfMsg > 16 || $numOfMsg < 1) {
            throw new MqException("本次消费的消息数量。取值范围 1-16");
        }
        return self::receive('BatchReceiveMessage', $queueName, $numOfMsg, $pollingWaitSeconds);
    }

    /**
     * 批量消费消息，根据业务自身特点，可以选择指定 pollingWaitSeconds 的值。
     *
     * @param string $action 接口名称
     * @param string $queueName 队列名字
     * @param int $numOfMsg 本次消费的消息数量。取值范围 1-16。
     * @param int $pollingWaitSeconds 本次请求的长轮询等待时间。取值范围0-30秒，默认值0。
     * @return ReceiveMessage|array
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    private static function receive(string $action, string $queueName, int $numOfMsg = 0, int $pollingWaitSeconds = 0)
    {
        $data = [
            'queueName' => $queueName,
            'pollingWaitSeconds' => $pollingWaitSeconds,
            'Action' => $action,
            'Region' => Yii::$app->params['cmq']['region'],
            'SecretId' => Yii::$app->params['cmq']['secretId'],
            'Nonce' => mt_rand(1, 65535),
            'Timestamp' => time(),
            'SignatureMethod' => 'HmacSHA256',
        ];

        //判断是否是批量接收
        if ('BatchReceiveMessage' === $action) {
            $data['numOfMsg'] = $numOfMsg;
        }

        $data = array_merge($data, self::signature($data, Yii::$app->params['cmq']['endPoint']));
        $response = HttpUtil::simplePost('http://' . Yii::$app->params['cmq']['endPoint'], $data);
        Yii::trace('response:' . $response, __METHOD__);

        $responseJson = json_decode($response, true);
        if (isset($responseJson['code']) && 0 !== $responseJson['code']) {
            if (7000 !== $responseJson['code'] || 10200 !== $responseJson['code']) {
                //判断是否是批量接收
                if ('BatchReceiveMessage' === $action) {
                    return [];
                }
                return null;
            }
            Yii::error('接收消息失败:' . $responseJson['code'] . $responseJson['message'], __METHOD__);
            throw new MqException("接收消息错误:" . $responseJson['message']);
        }

        //判断是否是批量接收，需要分别进行处理
        if ('BatchReceiveMessage' === $action) {
            $result = [];
            foreach ($responseJson['msgInfoList'] as $item => $value) {
                $value['code'] = $responseJson['code'];
                $value['message'] = $responseJson['message'];
                $value['requestId'] = $responseJson['requestId'];
                $result[$item] = new ReceiveMessage($value);
            }

            return $result;
        }

        return new ReceiveMessage($responseJson);
    }

    /**
     * <p>删除消息，一般来说，消息被消费一次就应该删除掉，除非业务有重复消费的需求。</p>
     *
     * <p>千万注意要在 nextVisibleTime 的时间之前进行删除操作，否则receiptHandle 会失效，导致删除失败。</p>
     *
     * @param string $queueName 队列名字
     * @param string $receiptHandle 每次消费返回唯一的消息句柄，用于删除消息。当且仅当消息上次被消费时产生的句柄能用于删除本条消息。
     *                              例如：“"283748239349283" （上例中的receiptHandle)”
     * @return bool 删除成功返回true
     * @throws MqException
     */
    public static function deleteMessage(string $queueName, string $receiptHandle): bool
    {
        return self::delete('DeleteMessage', $queueName, $receiptHandle);
    }

    /**
     * <p>删除消息，一般来说，消息被消费一次就应该删除掉，除非业务有重复消费的需求。</p>
     *
     * <p>千万注意要在 nextVisibleTime 的时间之前进行删除操作，否则receiptHandle 会失效，导致删除失败。</p>
     *
     * @param string $action 接口名称
     * @param string $queueName 队列名字
     * @param string|array $receiptHandle 每次消费返回唯一的消息句柄
     * @return bool 删除成功返回true
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    private static function delete(string $action, string $queueName, $receiptHandle): bool
    {
        $data = [
            'queueName' => $queueName,
            'Action' => $action,
            'Region' => Yii::$app->params['cmq']['region'],
            'SecretId' => Yii::$app->params['cmq']['secretId'],
            'Nonce' => mt_rand(1, 65535),
            'Timestamp' => time(),
            'SignatureMethod' => 'HmacSHA256',
        ];

        if ('BatchDeleteMessage' === $action) {
            $data = array_merge($data, $receiptHandle);
        } else {
            $data['receiptHandle'] = $receiptHandle;
        }

        $data = array_merge($data, self::signature($data, Yii::$app->params['cmq']['endPoint']));
        $response = HttpUtil::simplePost('http://' . Yii::$app->params['cmq']['endPoint'], $data);

        $responseJson = json_decode($response);
        if (isset($responseJson->code) && 0 !== $responseJson->code) {
            Yii::error('删除消息失败:' . $responseJson->code . $responseJson->message, __METHOD__);
            throw new MqException("删除消息错误:" . $responseJson->message);
        }

        return true;
    }

    /**
     * 批量删除消息，必须在收到消息后30秒内操作，否则会删除失败。
     *
     * @param string $queueName
     * @param array $receiptHandle
     * @return bool
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    public static function batchDeleteMessage(string $queueName, array $receiptHandle): bool
    {
        $count = count($receiptHandle);
        if ($count > 16 || $count < 1) {
            throw new MqException("要批量删除的消息数量不能超过16条");
        }

        $messages = [];
        $i = 0;
        foreach ($receiptHandle as $item => $value) {
            $messages['receiptHandle.' . $i] = $value;
            $i++;
        }

        return self::delete('BatchDeleteMessage', $queueName, $messages);
    }

    /**
     * 主题模型：发布消息
     *
     * @param string $topicName 主题名字，在单个地域同一帐号下唯一。主题名称是一个不超过 64 个字符的字符串，必须以字母为首字符，
     * 剩余部分可以包含字母、数字和横划线(-)。
     * @param string $msgBody 消息正文。至少 1 Byte，最大长度受限于设置的主题消息最大长度属性。
     * @return bool
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    public static function publishMessage(string $topicName, string $msgBody): bool
    {
        return self::publish('PublishMessage', $topicName, $msgBody);
    }

    /**
     * 主题模型：批量发布消息
     *
     * @param string $topicName 主题名字，在单个地域同一帐号下唯一。主题名称是一个不超过 64 个字符的字符串，必须以字母为首字符，
     * 剩余部分可以包含字母、数字和横划线(-)。
     * @param array $msgBody 消息正文。至少 1 Byte，最大长度受限于设置的主题消息最大长度属性。
     * @return bool
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    public static function batchPublishMessage(string $topicName, array $msgBody): bool
    {
        $count = count($msgBody);
        if ($count > 16 || $count < 1) {
            throw new MqException("要批量发布的消息数量不能超过16条");
        }

        $messages = [];
        $i = 0;
        foreach ($msgBody as $item => $value) {
            $messages['msgBody.' . $i] = $value;
            $i++;
        }

        return self::publish('BatchPublishMessage', $topicName, $messages);
    }

    /**
     * 主题模型：发布消息
     *
     * @param string $action 接口名称
     * @param string $topicName 主题名字
     * @param string|array $msgBody 消息正文
     * @return bool
     * @throws Exceptions\HttpException
     * @throws MqException
     * @throws \ErrorException
     */
    private static function publish(string $action, string $topicName, $msgBody): bool
    {
        $data = [
            'topicName' => $topicName,
            'Action' => $action,
            'Region' => Yii::$app->params['cmq']['region'],
            'SecretId' => Yii::$app->params['cmq']['secretId'],
            'Nonce' => mt_rand(1, 65535),
            'Timestamp' => time(),
            'SignatureMethod' => 'HmacSHA256',
        ];

        if ('BatchPublishMessage' === $action) {
            $data = array_merge($data, $msgBody);
        } else {
            $data['msgBody'] = $msgBody;
        }

        $data = array_merge($data, self::signature($data, Yii::$app->params['cmq']['endPointTopic']));
        $response = HttpUtil::simplePost('http://' . Yii::$app->params['cmq']['endPointTopic'], $data);

        $responseJson = json_decode($response);
        if (isset($responseJson->code) && 0 !== $responseJson->code) {
            Yii::error('发布消息失败:' . $responseJson->code . $responseJson->message, __METHOD__);
            throw new MqException("发布消息错误:" . $responseJson->message);
        }

        return true;
    }

}