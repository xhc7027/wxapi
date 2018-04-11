<?php

namespace app\models;


use yii\db\ActiveRecord;

/**
 * 网页授权用户信息表
 * @package app\models
 */
class WebUserAuthInfo extends ActiveRecord
{
    public function attributes()
    {
        return [
            'openId', 'appId', 'accessToken', 'accessTokenExpire', 'refreshToken', 'refreshTokenExpire'
        ];
    }

    public function rules()
    {
        return [
            [
                [
                    'openId', 'appId', 'accessToken', 'accessTokenExpire', 'refreshToken', 'refreshTokenExpire'
                ], 'required'
            ],
            [
                ['openId', 'appId', 'accessToken', 'refreshToken'], 'string'
            ],
            [['accessTokenExpire', 'refreshTokenExpire'], 'integer']
        ];
    }

    /**
     * 通过openId、appId获取刷新token信息
     * @param string $openId
     * @param string $appId
     * @return array
     */
    public static function getRefreshTokenInfoByOpenIdAppId(string $openId, string $appId)
    {
        return self::find()->select(['refreshToken', 'refreshTokenExpire'])
            ->where(['openId' => $openId, 'appId' => $appId])->asArray()->one();
    }

    /**
     * 获取accessToken
     * @param string $openId
     * @param string $appId
     * @return array
     */
    public static function getAccessToken(string $openId, string $appId)
    {
        return self::find()->select(['accessToken', 'accessTokenExpire'])
            ->where(['openId' => $openId, 'appId' => $appId])->asArray()->one();
    }

    /**
     * 更新token信息
     * @param array $info
     * @param string $openId
     * @param string $appId
     * @return int
     */
    public static function updateTokenInfo(array $info, string $openId, string $appId)
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
    public static function countByOpenIdAppId(string $openId, string $appId)
    {
        return self::find()->where(['openId' => $openId, 'appId' => $appId])->count();
    }
}