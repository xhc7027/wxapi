<?php

return [
    'adminEmail' => 'admin@example.com',
    'wxConfig' => [
        'url' => 'https://api.weixin.qq.com/cgi-bin/component',//公众号第三方平台接口地址
        'appId' => 'wxebf053990b5bf228',//公众号第三方平台
        'secret' => '0c79e1fa963cd80cc0be99b20a18faeb',//公众号第三方平台
        'token' => 'idouzi',//公众号消息校验Token
        'encodingAESKey' => 'A1NSzgwZ8IdZuTJCtzUbzHIDZ2wS8sTNlKfGOy7LWhW',//公众号消息加解密Key
        'appUrl' => 'https://api.weixin.qq.com',//公众号接口地址
        'openUrl' => 'https://open.weixin.qq.com',//开放平台地址
        'snsOauth2Url' => 'https://api.weixin.qq.com/sns/oauth2/component',//发起网页授权接口地址
    ],
    'serviceDomain' => [
        'weiXinApiDomain' => 'http://weixinapi2.idouzi.com',//代理平台服务域名
        'weiXinMsgDomain' => 'http://weixinmsg.idouzi.com', //消息管理服务域名
        'iDouZiDomain' => 'http://new.idouzi.com', //爱豆子服务域名
        'voteDomain' => 'http://vote2.idouzi.com',//微投票服务域名
    ],
    'signKey' => [
        'apiSignKey' => '1USyZ9Adxx8lI58hsikLbnBZpMEGn4gA',//代理平台跨服务接口安全认证key
        'iDouZiSignKey' => '1USyZ9Adxx8lI58hsikLbnBZpMEGn4gA',//爱豆子跨服务接口安全认证key
        'msgSignKey' => '1USyZ9Adxx8lI58hsikLbnBZpMEGn4gA',//消息系统跨服务接口安全认证key
        'voteSignKey' => '1USyZ9Adxx8lI58hsikLbnBZpMEGn4gA', //微投票跨服务接口安全认证key
    ],
    'weiXinDataApi' => [
        ['name' => '获取用户增减数据', 'type' => 'getusersummary',],
        ['name' => '获取累计用户数据', 'type' => 'getusercumulate',],
        ['name' => '获取图文群发每日数据', 'type' => 'getarticlesummary',],
        ['name' => '获取图文群发总数据', 'type' => 'getarticletotal',],
        ['name' => '获取图文统计数据', 'type' => 'getuserread',],
        ['name' => '获取图文统计分时数据', 'type' => 'getuserreadhour',],
        ['name' => '获取图文分享转发数据', 'type' => 'getusershare',],
        ['name' => '获取图文分享转发分时数据', 'type' => 'getusersharehour',],
        ['name' => '获取消息发送概况数据', 'type' => 'getupstreammsg',],
        ['name' => '获取消息分送分时数据', 'type' => 'getupstreammsghour',],
//        ['name' => '获取消息发送周数据', 'type' => 'getupstreammsgweek',],
//        ['name' => '获取消息发送月数据', 'type' => 'getupstreammsgmonth',],
//        ['name' => '获取消息发送分布数据', 'type' => 'getupstreammsgdist',],
//        ['name' => '获取消息发送分布周数据', 'type' => 'getupstreammsgdistweek',],
//        ['name' => '获取消息发送分布月数据', 'type' => 'getupstreammsgdistmonth',],
        ['name' => '获取接口分析数据', 'type' => 'getinterfacesummary',],
        ['name' => '获取接口分析分时数据', 'type' => 'getinterfacesummaryhour',],
    ],
    //微信接口权限列表:subscribe表示订阅号 subscribeAuth：认证订阅号 service：服务号 serviceAuth：认证服务号 false代表没有权限，true代表有
    'wxApiAuthorize' => [
        'userManagementAuthorize' =>
            ['subscribe' => false, 'subscribeAuth' => false, 'service' => false, 'serviceAuth' => true],//发起网页授权
        'jsSdkShare' =>
            ['subscribe' => false, 'subscribeAuth' => true, 'service' => false, 'serviceAuth' => true],//jsSdk 分享
        'onMenuShareTimeline' =>
            ['subscribe' => false, 'subscribeAuth' => true, 'service' => false, 'serviceAuth' => true],//jsSdk 分享朋友圈
        'onMenuShareAppMessage' =>
            ['subscribe' => false, 'subscribeAuth' => true, 'service' => false, 'serviceAuth' => true],//jsSdk 分享给好友
    ]
];
