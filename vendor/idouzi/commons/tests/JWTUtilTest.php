<?php

namespace Idouzi\Commons\tests;

use Idouzi\Commons\JWTUtil;
use PHPUnit\Framework\TestCase;

class JWTUtilTest extends TestCase
{
    public function testEncode()
    {
        $jwt = new JWTUtil('sezia6Zaboo1eoph');
        echo $jwt->encode('abc', 123456);
    }

    public function testDecode()
    {
        $jwt = new JWTUtil('sezia6Zaboo1eoph');
        echo $jwt->decode('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjg2NDAwLCJkYXRhIjoiYWJjIn0=.544a2d682604f28a5304d349eb80b3f23f9ac4901d45ce502e6c837910fdea8b');
    }
}