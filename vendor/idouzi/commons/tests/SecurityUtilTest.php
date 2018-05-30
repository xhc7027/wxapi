<?php

namespace Idouzi\tests;

use Idouzi\Commons\SecurityUtil;
use PHPUnit\Framework\TestCase;

class SecurityUtilTest extends TestCase
{
    private $timestamp;

    protected function setup()
    {
        $this->timestamp = time();
    }

    /**
     * 生成签名
     */
    public function test1()
    {
        $data = ['username' => 'zhanshan', 'timestamp' => $this->timestamp, 'appId' => 'ad'];
        $sec = new SecurityUtil($data, 'xxxx');
        $sec->generateSign();
    }

    /**
     * 验证签名
     */
    public function test2()
    {
        $data = ['username' => 'zhanshan', 'timestamp' => $this->timestamp, 'sign' => 'xxx'];
        $sec = new SecurityUtil($data, 'xxxx');
        $sec->signVerification();
    }
}