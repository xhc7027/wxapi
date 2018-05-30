<?php

namespace Idouzi\Commons\Models;

/**
 * 腾讯云消息服务接收模型
 *
 * @package app\models
 */
class ReceiveMessage
{
    /**
     * @var int 0：表示成功，others：错误，详细错误见下表。
     */
    public $code;
    /**
     * @var string 错误提示信息。
     */
    public $message;
    /**
     * @var string 服务器生成的请求 Id。出现服务器内部错误时，用户可提交此 Id 给后台定位问题。
     */
    public $requestId;
    /**
     * @var string 本次消费的消息正文。
     */
    public $msgBody;
    /**
     * @var string 本次消费的消息唯一标识 Id。
     */
    public $msgId;
    /**
     * @var string 每次消费返回唯一的消息句柄。用于删除该消息，仅上一次消费时产生的消息句柄能用于删除消息。
     */
    public $receiptHandle;
    /**
     * @var int 消费被生产出来，进入队列的时间。返回Unix时间戳，精确到秒。
     */
    public $enqueueTime;
    /**
     * @var int 第一次消费该消息的时间。返回Unix时间戳，精确到秒。
     */
    public $firstDequeueTime;
    /**
     * @var int 消息的下次可见（可再次被消费）时间。返回Unix时间戳，精确到秒。
     */
    public $nextVisibleTime;
    /**
     * @var int 消息被消费的次数。
     */
    public $dequeueCount;

    public $originalQueueId;

    public $originalEnQueueTime;

    public $originalDeQueueCount;

    /**
     * 初始化构造方法，初始化属性值
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
    }

}