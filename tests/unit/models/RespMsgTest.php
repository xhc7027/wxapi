<?php

namespace app\tests\unit\models;

use app\models\RespMsg;
use Codeception\Test\Unit;

class RespMsgTest extends Unit
{
    public function testInit()
    {
        $respMsg = new RespMsg();
        $this->assertEquals(RespMsg::SUCCESS, $respMsg->return_code);

        $respMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        $this->assertEquals(RespMsg::FAIL, $respMsg->return_code);
    }

    public function testSetValue()
    {
        $respMsg = new RespMsg();
        $response = json_decode('{
"pre_auth_code":"Cx_Dk6qiBE0Dmx4EmlT3oRfArPvwSQ-oa3NL_fwHM7VI08r52wazoZX2Rhpz1dEw",
"expires_in":600
}');
        $respMsg->return_msg = $response;
        $respMsg->return_msg = null;
        $respMsg->return_msg['preAuthCode'] = $response->pre_auth_code;
        $this->assertEquals('Cx_Dk6qiBE0Dmx4EmlT3oRfArPvwSQ-oa3NL_fwHM7VI08r52wazoZX2Rhpz1dEw', $respMsg->return_msg['preAuthCode']);
    }
}