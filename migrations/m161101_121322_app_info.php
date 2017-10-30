<?php

use yii\db\Migration;

class m161101_121322_idouzi_api_app_info extends Migration
{
    public function safeUp()
    {
        $this->execute("
        CREATE TABLE `app_info` (
  `appId`                        CHAR(18) NOT NULL
  COMMENT '授权方appid',
  `accessToken`                  CHAR(138)    DEFAULT NULL
  COMMENT '[0]授权方接口调用凭据',
  `refreshToken`                 VARCHAR(255) DEFAULT NULL
  COMMENT '[0]接口调用凭据刷新令牌',
  `funcScopeCategory`            VARCHAR(255) DEFAULT NULL
  COMMENT '[0]公众号授权给开发者的权限集列表',
  `expiresIn`                    INT(11)      DEFAULT NULL
  COMMENT '[0]有效期（在授权的公众号具备API权限时，才有此返回值）',
  `zeroUpdatedAt`                INT(11)      DEFAULT NULL
  COMMENT '[0]更新时间戳',
  `nickName`                     VARCHAR(255) DEFAULT NULL
  COMMENT '[1]授权方昵称',
  `headImg`                      VARCHAR(255) DEFAULT NULL
  COMMENT '[1]授权方头像',
  `serviceTypeInfo`              TINYINT(4)   DEFAULT NULL
  COMMENT '[1]授权方公众号类型，0代表订阅号，1代表由历史老帐号升级后的订阅号，2代表服务号',
  `verifyTypeInfo`               TINYINT(4)   DEFAULT NULL
  COMMENT '[1]授权方认证类型，-1代表未认证，0代表微信认证，1代表新浪微博认证，2代表腾讯微博认证，3代表已资质认证通过但还未通过名称认证，4代表已资质认证通过、还未通过名称认证，但通过了新浪微博认证，5代表已资质认证通过、还未通过名称认证，但通过了腾讯微博认证',
  `userName`                     VARCHAR(255) DEFAULT NULL
  COMMENT '[1]授权方公众号的原始ID',
  `alias`                        VARCHAR(255) DEFAULT NULL
  COMMENT '[1]授权方公众号所设置的微信号，可能为空',
  `businessInfoOpenStore`        TINYINT(4)   DEFAULT NULL
  COMMENT '[1]是否开通微信门店功能（0代表未开通，1代表已开通）',
  `businessInfoOpenScan`         TINYINT(4)   DEFAULT NULL
  COMMENT '[1]是否开通微信扫商品功能（0代表未开通，1代表已开通）',
  `businessInfoOpenPay`          TINYINT(4)   DEFAULT NULL
  COMMENT '[1]是否开通微信支付功能（0代表未开通，1代表已开通）',
  `businessInfoOpenCard`         TINYINT(4)   DEFAULT NULL
  COMMENT '[1]是否开通微信卡券功能（0代表未开通，1代表已开通）',
  `businessInfoOpenShake`        TINYINT(4)   DEFAULT NULL
  COMMENT '[1]是否开通微信摇一摇功能（0代表未开通，1代表已开通）',
  `qrcodeUrl`                    VARCHAR(255) DEFAULT NULL
  COMMENT '[1]二维码图片的URL',
  `oneUpdatedAt`                 INT(11)      DEFAULT NULL
  COMMENT '[1]更新时间戳',
  `componentAppId`               CHAR(18)     DEFAULT NULL
  COMMENT '[2]第三方平台appid',
  `infoType`                     VARCHAR(16)  DEFAULT NULL
  COMMENT '[2]unauthorized是取消授权，updateauthorized是更新授权，authorized是授权成功通知',
  `authorizationCode`            VARCHAR(255) DEFAULT NULL
  COMMENT '[2]授权码，可用于换取公众号的接口调用凭据，详细见上面的说明',
  `authorizationCodeExpiredTime` INT(11)      DEFAULT NULL
  COMMENT '[2]授权码过期时间',
  `twoUpdatedAt`                 INT(11)      DEFAULT NULL
  COMMENT '[2]更新时间戳',
  PRIMARY KEY (`appId`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COMMENT = '代运营公众号信息';
        ");
    }

    public function safeDown()
    {
        $this->execute("DROP TABLE `app_info`");
    }
}
