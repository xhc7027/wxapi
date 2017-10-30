<?php

namespace app\models;

use yii\base\Model;

/**
 * 统一接口响应回去的信息
 *
 * @package app\models
 */
class RespMsg extends Model
{
    const SUCCESS = 'SUCCESS';
    const FAIL = 'FAIL';
    /**
     * @var string 业务处理状态，分成SUCCESS和 FAIL
     */
    public $return_code = self::SUCCESS;
    public $return_msg;

    /**
     * JSON格式化输出对象内容
     * @return null|string
     */
    public function toJsonStr()
    {
        if (!$this) {
            return null;
        }
        return json_encode($this);
    }

    /**
     * 实现对象打印时的格式化内容
     * @return null|string
     */
    function __toString()
    {
        return $this->toJsonStr();
    }
}
