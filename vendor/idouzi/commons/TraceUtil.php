<?php

namespace Idouzi\Commons;

use whitemerry\phpkin\AnnotationBlock;
use whitemerry\phpkin\Endpoint;
use whitemerry\phpkin\Identifier\SpanIdentifier;
use whitemerry\phpkin\Logger\LoggerException;
use whitemerry\phpkin\Logger\SimpleHttpLogger;
use whitemerry\phpkin\Span;
use whitemerry\phpkin\Tracer;
use whitemerry\phpkin\TracerInfo;
use whitemerry\phpkin\TracerProxy;
use Yii;

/**
 * 记录业务性能日志工具类
 * @package Idouzi\Commons
 */
class TraceUtil
{
    /**
     * 检测是否开启记录器
     * @return bool
     */
    private static function isReady()
    {
        if (Yii::$app->response instanceof yii\web\Response
            && in_array(YII_ENV, Yii::$app->params['zipkin']['operatingEnvironment'])) {
            return true;
        }

        return false;
    }

    /**
     * 初始化记录器
     *
     * @param string $name 业务处理描述
     */
    public static function init($name)
    {
        if (self::isReady()) {
            $endpoint = new Endpoint(
                Yii::$app->params['zipkin']['endpoint']['name'],
                Yii::$app->params['zipkin']['endpoint']['ip'] ?? IpUtil::getLocalIp(),
                Yii::$app->params['zipkin']['endpoint']['port']
            );
            $logger = new SimpleHttpLogger(['host' => Yii::$app->params['zipkin']['host'], 'muteErrors' => false]);
            $tracer = new Tracer($name, $endpoint, $logger);

            TracerProxy::init($tracer);
        }
    }

    /**
     * 业务处理前开始记录
     * @return array|null 返回分段编号及记录时间
     */
    public static function startLogger()
    {
        if (self::isReady()) {
            $requestStart = zipkin_timestamp();
            $spanId = new SpanIdentifier();
            return ['requestStart' => $requestStart, 'spanId' => $spanId];
        }
        return [];
    }

    /**
     * 业务结束后记录
     *
     * @param string $serviceName 业务处理描述
     * @param string $name 具体的业务数据
     * @param array $startLogger
     */
    public static function endLogger(string $serviceName, string $name, array $startLogger)
    {
        if (self::isReady() && count($startLogger) > 0) {
            self::addHeader('X-B3-TraceId', TracerInfo::getTraceId());
            self::addHeader('X-B3-SpanId', (string)$startLogger['spanId']);
            self::addHeader('X-B3-ParentSpanId', TracerInfo::getTraceSpanId());
            self::addHeader('X-B3-Sampled', ((int)TracerInfo::isSampled()));

            $endpoint = new Endpoint(
                $serviceName, Yii::$app->params['zipkin']['endpoint']['ip'],
                Yii::$app->params['zipkin']['endpoint']['port']
            );
            $annotationBlock = new AnnotationBlock($endpoint, $startLogger['requestStart']);
            $span = new Span($startLogger['spanId'], $name, $annotationBlock);

            TracerProxy::addSpan($span);
        }
    }

    /**
     * 业务处理完成后发送日志
     */
    public static function trace()
    {
        if (self::isReady()) {
            try {
                TracerProxy::trace();
            } catch (LoggerException $e) {
                Yii::error('发送业务日志到Zipkin失败:' . $e->getMessage(), __METHOD__);
            }
        }
    }

    /**
     * 在响应头添加自定义信息
     *
     * @param string $key
     * @param string $value
     */
    private static function addHeader(string $key, string $value)
    {
        $headers = Yii::$app->response->headers;
        $headers->remove($key);
        $headers->add($key, $value);
    }
}