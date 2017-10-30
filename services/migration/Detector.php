<?php

namespace app\services\migration;

/**
 * 指导具体的过程
 * @package app\services\migration
 */
class Detector
{
    /**
     * @var Builder
     */
    private $builder;

    /**
     * Detector constructor.
     * @param Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function run()
    {
        $this->builder->migration();
    }
}