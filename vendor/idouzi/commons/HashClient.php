<?php

namespace Idouzi\Commons;

use Idouzi\Commons\ConsistentHashing\Flexihash;
use yii\base\InvalidArgumentException;

/**
 * 提供一个对外的简化客户端，提供一系列添加节点、查找节点的方法
 *
 * @package app\services\hash
 */
class HashClient
{
    /**
     * 节点默认数量
     */
    const NODE_NUM = 10;

    /**
     * 根据分区键用来寻找分表编号
     *
     * @param string $resource 资源名称，例如广告编号
     * @param int $nodeNum 分区数量，默认10
     * @return string 返回该活动编号分散在哪一个分区上，值是一个字符串表示的整型数字
     * @throws ConsistentHashing\FlexihashException
     */
    public static function lookup(string $resource, $nodeNum = 10)
    {
        if (!$resource) {
            throw new InvalidArgumentException('要查询的分表键不能为空');
        }

        $flexiHash = new Flexihash();
        for ($i = 0; $i < $nodeNum; $i++) {
            $flexiHash->addTarget(strval($i));
        }

        return $flexiHash->lookup($resource);
    }
}