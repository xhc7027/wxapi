<?php

namespace app\services\migration;

/**
 * 构建者，定义构建过程
 * @package app\services\migration
 */
interface Builder
{
    /**
     * 对数据进行迁移
     * @return mixed
     */
    public function migration();
}