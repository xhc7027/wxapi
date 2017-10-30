<?php

use yii\db\Migration;

class m161101_121336_idouzi_api_component_info extends Migration
{
    public function safeUp()
    {
        $this->execute("
        CREATE TABLE `component_info` (
  `appId`         CHAR(18) NOT NULL
  COMMENT '第三方平台appid',
  `infoType`      VARCHAR(255)   DEFAULT NULL
  COMMENT '[0]component_verify_ticket',
  `verifyTicket`  VARCHAR(255)   DEFAULT NULL
  COMMENT '[0]Ticket内容',
  `zeroUpdatedAt` INT(11)        DEFAULT NULL
  COMMENT '[0]更新时间戳',
  `accessToken`   VARBINARY(138) DEFAULT NULL
  COMMENT '[1]第三方平台access_token',
  `zeroExpiresIn` INT(11)        DEFAULT NULL
  COMMENT '[1]有效期',
  `oneUpdatedAt`  INT(11)        DEFAULT NULL
  COMMENT '[1]更新时间戳',
  PRIMARY KEY (`appId`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COMMENT = '第三方公众号信息';
        ");
    }

    public function safeDown()
    {
        $this->execute("DROP TABLE `component_info`");
    }
}
