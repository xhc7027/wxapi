<?php

namespace app\models;


use yii\db\ActiveRecord;

/**
 * 网页授权用户信息表
 *
 * @property string $appId
 * @property string $openId
 * @property string $accessToken
 * @property integer $accessTokenExpire
 * @property string $refreshToken
 * @property integer $refreshTokenExpire
 * @property string $queryAppId
 *
 * @package app\models
 */
class WebUserAuthInfo extends ActiveRecord
{
    public function attributes()
    {
        return [
            'openId', 'appId', 'accessToken', 'accessTokenExpire', 'refreshToken', 'refreshTokenExpire', 'queryAppId'
        ];
    }

    public function rules()
    {
        return [
            [
                [
                    'openId', 'accessToken', 'accessTokenExpire', 'refreshToken', 'refreshTokenExpire'
                ], 'required'
            ],
            [
                ['openId', 'appId', 'accessToken', 'refreshToken', 'queryAppId'], 'string'
            ],
            [['accessTokenExpire', 'refreshTokenExpire'], 'integer']
        ];
    }

    /**
     * 获取有值属性的值
     * @return mixed
     */
    public function getAttributeValue()
    {
        $return = [];
        foreach ($this->attributes() as $val) {
            if ($this->$val)
                $return[$val] = $this->$val;
        }

        return $return;
    }

    /**
     * 通过openId、appId获取刷新token信息
     * @param string $openId
     * @param string $appId
     * @return array
     */
    public static function getRefreshTokenInfoByOpenIdAppId(string $openId, string $appId, string $queryAppId = '')
    {
        return self::find()->select(['refreshToken', 'refreshTokenExpire'])
            ->where(['appId' => $appId])->asArray()->one();
    }

    /**
     * 获取accessToken
     * @param string $openId
     * @param string $appId
     * @return array
     */
    public static function getAccessToken(string $openId, string $appId, string $queryAppId = '')
    {
        return self::find()->select(['accessToken', 'accessTokenExpire', 'refreshToken', 'refreshTokenExpire'])
            ->where(['appId' => $appId])->asArray()->one();
    }

    /**
     * 更新token信息
     * @param array $info
     * @param string $openId
     * @param string $appId
     * @return int
     */
    public static function updateTokenInfo(array $info, string $openId, string $appId, string $queryAppId = '')
    {
        return self::updateAll(
            $info,
            'openId=:openId AND appId = :appId',
            array(':appId' => $appId, ':openId' => $openId)
        );
    }

    /**
     * 通过openId、appId查询条数
     * @param string $openId
     * @param string $appId
     * @return int|string
     */
    public static function countByOpenIdAppId(string $openId, string $appId, string $queryAppId)
    {
        return self::find()->where(['appId' => $appId])->count();
    }
}