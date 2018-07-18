<?php

namespace app\models;

use app\exceptions\ModelValidateException;
use app\commons\SecurityUtil;
use app\services\ImageService;
use app\services\WeiXinService;
use Curl\Curl;
use Yii;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;

/**
 * @property integer $TsId
 * @property string $data
 * @property string $createTime
 */
class TsMsgSupplierFounder extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'tsId' => '事务Id',
            'data' => '消息数据',
            'createTime' => '创建时间',
        ];
    }

    /**
     * 插入公众号换绑的数据
     *
     * @param string $data
     * @return bool
     * @throws ModelValidateException
     */
    public function insertData(string $data)
    {
        $this->data = $data;
        $this->createTime = date('Y-m-d H:i:s');
        if (!$this->insert()) {
            throw new ModelValidateException(current($this->getFirstErrors()));
        }
        return $this->tsId;
    }

}
