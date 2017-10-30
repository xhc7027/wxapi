<?php

use yii\db\Migration;

class m161101_121354_idouzi_api_request_logger extends Migration
{
    public function safeUp()
    {
        $this->execute("
        CREATE TABLE `request_logger` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `appId` CHAR(18) DEFAULT NULL COMMENT '授权方appid',
  `type` TINYINT(4) NOT NULL COMMENT '日志类型（0内部调用，1微信回调）',
  `method` VARCHAR(10) DEFAULT NULL COMMENT '请求类型',
  `reqTime` DATETIME DEFAULT NULL COMMENT '请求时间，此字段方便按日期查询',
  `reqTimeStr` VARCHAR(24) DEFAULT NULL COMMENT '请求时间字符串形式，精确到微妙',
  `srcIp` VARCHAR(255) DEFAULT NULL COMMENT '来源IP',
  `reqUri` VARCHAR(255) DEFAULT NULL COMMENT '访问页面',
  `queryStr` TEXT COMMENT '请求参数',
  `postStr` TEXT COMMENT 'POST数据',
  `respStr` TEXT COMMENT '响应数据',
  `timeConsume` DOUBLE DEFAULT NULL COMMENT '请求耗时，单位秒',
  PRIMARY KEY (`id`),
  KEY `reqTime` (`reqTime`),
  KEY `appId` (`appId`),
  KEY `timeConsume` (`timeConsume`)
) ENGINE=INNODB AUTO_INCREMENT=2387 DEFAULT CHARSET=utf8 COMMENT='记录所有请求数据'
PARTITION BY HASH(id)  
PARTITIONS 10;
        ");
    }

    public function safeDown()
    {
        $this->execute("DROP TABLE `request_logger`");
    }
}
