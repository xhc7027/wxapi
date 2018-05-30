<?php

namespace Idouzi\Commons\QCloud;

use Idouzi\Commons\Exceptions\CTSDBException;
use Idouzi\Commons\Exceptions\HttpException;
use Idouzi\Commons\HttpUtil;
use Yii;

/**
 * <p>腾讯云时序数据库</p>
 *
 * <p>实现了腾讯云开放的所有HTTP接口，包含新建、查询、更新、删除、写入、查询和Rollup等操作。</p>
 *
 * 腾讯云时序数据库文档：https://cloud.tencent.com/document/product/652/14631
 *
 * 在使用本工具类时需要配置参数：<br>
 * <code>
 *   ## 时序数据库连接信息
 *   ctsdb.host = '10.0.17.2'
 *   ctsdb.port = 9200
 *   ctsdb.username = 'root'
 *   ctsdb.password = 'xxx'
 * </code>
 * @package Idouzi\Commons
 */
class TencentCTSDBUtil
{
    /**
     * 新建 metric，类似于关系型数据库中创建表结构。
     *
     * @param string $metricName 表名允许使用小写英文字母、数字、 _ 、 - 的组合，且不能以 _ 或 - 开头
     * @param array $tags 维度列，至少包含一个维度，支持的数据类型：text（带有分词、全文索引的字符串）、string（不分词的字符串）、long、
     * integer、short、byte、double、float、date、boolean。格式如：{"region": "string","set": "long","host": "string"}
     * @param array $fields 指标列，为了节省空间，建议使用最适合实际业务使用的类型，支持的数据类型：string（字符串）、long、integer、
     * short、byte、double、float、date、boolean。例如：{"cpu_usage":"float"}
     * @param array $time 时间列相关配置，例如：{"name": "timestamp", "format": "epoch_second"}
     * @param array $options 常用的调优配置信息，例如：{"expire_day":7,"refresh_interval":"10s","number_of_shards":5,
     * "number_of_replicas":1,"rolling_period":1,"max_string_length": 256,
     * "default_date_format":"strict_date_optional_time","indexed_fields":["host"]}
     * @return array 成功时返回{ "acknowledged": true, "message": "create ctsdb metric ctsdb_test success!" }，
     * 失败时返回{ "error": { "reason": "table ctsdb_test already exist", "type": "metric_exception" }, "status": 201 }
     * @throws HttpException
     */
    public static function createMetric(string $metricName, array $tags, array $fields, array $time = [],
                                        array $options = []): array
    {
        $data = [
            'tags' => $tags,
            'time' => 0 === count($time) ? ['name' => 'timestamp', 'format' => 'epoch_second'] : $time,
            'fields' => $fields,
            'options' => 0 === count($options) ? ['expire_day' => 90, 'refresh_interval' => '10s',
                'number_of_shards' => 5, 'number_of_replicas' => 1] : $options,
        ];

        return self::request(HttpUtil::POST, '/_metric/' . $metricName, [], $data);
    }

    /**
     * 获取所有metric
     *
     * @return array { "result": { "metrics": [ "ctsdb_test", "ctsdb_test1" ] }, "status": 200 }
     * @throws HttpException
     */
    public static function getMetrics(): array
    {
        return self::request(HttpUtil::GET, '/_metrics');
    }

    /**
     * 获取特定metric
     *
     * @param string $metricName 表名允许使用小写英文字母、数字、 _ 、 - 的组合，且不能以 _ 或 - 开头
     * @return array { "result": { "ctsdb_test": { "tags": { "region": "string" }, "time":
     * { "name": "timestamp", "format": "epoch_second" }, "fields": { "cpuUsage": "float" },
     * "options": { "expire_day": 7, "refresh_interval": "10s", "number_of_shards": 5 } } }, "status": 200 }
     * @throws HttpException
     */
    public static function getMetric(string $metricName): array
    {
        return self::request(HttpUtil::GET, '/_metric/' . $metricName);
    }

    /**
     * 更新 metric
     *
     * @param string $metricName 表名允许使用小写英文字母、数字、 _ 、 - 的组合，且不能以 _ 或 - 开头
     * @param array $tags 维度列，至少包含一个维度，支持的数据类型：text（带有分词、全文索引的字符串）、string（不分词的字符串）、long、
     * integer、short、byte、double、float、date、boolean。格式如：{"region": "string","set": "long","host": "string"}
     * @param array $fields 指标列，为了节省空间，建议使用最适合实际业务使用的类型，支持的数据类型：string（字符串）、long、integer、
     * short、byte、double、float、date、boolean。例如：{"cpu_usage":"float"}
     * @param array $time 时间列相关配置，例如：{"name": "timestamp", "format": "epoch_second"}
     * @param array $options 常用的调优配置信息，例如：{"expire_day":7,"refresh_interval":"10s","number_of_shards":5,
     * "number_of_replicas":1,"rolling_period":1,"max_string_length": 256,
     * "default_date_format":"strict_date_optional_time","indexed_fields":["host"]}
     * @return array 成功时返回{ "acknowledged": true, "message": "create ctsdb metric ctsdb_test success!" }，
     * 失败时返回{ "error": { "reason": "table ctsdb_test already exist", "type": "metric_exception" }, "status": 201 }
     * @throws HttpException
     */
    public static function updateMetric(string $metricName, array $tags, array $fields, array $time = [],
                                        array $options = []): array
    {
        $data = ['tags' => $tags, 'fields' => $fields];

        if (0 !== count($time)) {
            $data['time'] = $time;
        }

        if (0 !== count($options)) {
            $data['options'] = $options;
        }

        return self::request(HttpUtil::PUT, '/_metric/' . $metricName . '/update', [], $data);
    }

    /**
     * 删除 metric
     *
     * @param string $metricName 表名允许使用小写英文字母、数字、 _ 、 - 的组合，且不能以 _ 或 - 开头
     * @return array { "acknowledged": true, "message": "delete metric ctsdb_test1 success!" }
     * @throws HttpException
     */
    public static function deleteMetric(string $metricName): array
    {
        return self::request(HttpUtil::DELETE, '/_metric/' . $metricName);
    }

    /**
     * 删除metric字段
     *
     * @param string $metricName 表名允许使用小写英文字母、数字、 _ 、 - 的组合，且不能以 _ 或 - 开头
     * @param array $tags 维度列
     * @param array $fields 指标列
     * @return array{ "acknowledged": true, "message": "update ctsdb_test1 metric success!" }
     * @throws CTSDBException
     * @throws HttpException
     */
    public static function deleteTagsAndFields(string $metricName, array $tags = [], array $fields = []): array
    {
        $data = [];

        if (0 !== count($tags)) {
            $data['tags'] = $tags;
        }

        if (0 !== count($fields)) {
            $data['fields'] = $fields;
        }

        if (0 === count($data)) {
            throw new CTSDBException('必须指定要删除的维度或指标列的名称');
        }

        return self::request(HttpUtil::PUT, '/_metric/' . $metricName . '/delete', [], $data);
    }

    /**
     * 在单个metric中写入一条或多条数据，为提高写入性能，建议批量写入数据。
     *
     * @param string $metricName 表名允许使用小写英文字母、数字、 _ 、 - 的组合，且不能以 _ 或 - 开头
     * @param array $docs 要保存的一条或多条数据，如果只有一条数据也应该包含到数组中传递进来。
     * @return array { "took": 65, "errors": false, "items": [ { "index":
     * { "_index": "test@144000000_30", "_type": "doc", "_id": "AV_8cKnEUAkC9PF9L-2k", "_version": 1,
     * "result": "created", "_shards": { "total": 2, "successful": 2, "failed": 0 },
     * "created": true, "status": 201 } } ] }
     * @throws CTSDBException
     * @throws HttpException
     */
    public static function insert(string $metricName, array $docs = []): array
    {
        $data = '';
        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                throw new CTSDBException('只有一条数据时也应该包含到数组中传递');
            }
            $data .= json_encode(['index' => ['_id' => null, '_routing' => null]]) . "\n"
                . json_encode($doc) . "\n";
        }
        return self::request(HttpUtil::POST, '/' . $metricName . '/doc/_bulk', [], $data);
    }

    /**
     * 查询数据，为提高查询性能，请务必加上 time 字段的 range 查询，
     * 且 time 字段，不论查询时以何种方式写入，返回值统一为 epoch_millis 格式。
     *
     * @param string $metricName 表名
     * @param array $query 过滤条件，具体语句参数https://cloud.tencent.com/document/product/652/14631
     * @param array $aggs 用于构造聚合查询
     * @param array $fields 指定需要返回的字段名称，需要以数组的形式指定。
     * @param int $from 第一条数据的偏移量，可以配合size做分页操作
     * @param int $size 返回数据行数，默认最多10条
     * @param array $sort 对查询结果进行排序，CTSDB 对于用户自定义字段的默认排序方式为 asc。
     * @return array
     * @throws CTSDBException
     * @throws HttpException
     */
    public static function search(string $metricName, array $query, array $aggs = [],
                                  array $fields = [], int $from = 0, int $size = 10, array $sort = []): array
    {
        if ($from + $size > 65536) {
            throw new CTSDBException('From 与 Size 的总和系统默认不能超过 65536');
        }
        $data = ['query' => $query, 'from' => $from, 'size' => $size];
        if (count($fields) > 0) {
            $data['docvalue_fields'] = $fields;
        }
        if (count($sort) > 0) {
            $data['sort'] = $sort;
        }
        if (count($aggs) > 0) {
            $data['aggs'] = $aggs;
        }
        return self::request(HttpUtil::POST, '/' . $metricName . '/_search', [], $data);
    }

    /**
     * 建立Rollup任务
     * Rollup接口主要用于聚合历史数据，从而提高查询性能，降低存储成本。Rollup任务会自动根据base_metric建立子表，继承父表的所有配置，
     * 如果指定options，会覆盖父表配置。
     *
     * @param string $rollupTaskName Rollup任务的名称
     * @param string $baseMetric Rollup依赖的metric名称（父表）
     * @param string $rollupMetric Rollup产生的metric名称（子表）
     * @param array $groupTags 进行聚合的维度列，可以包含多列
     * @param array $fields 指定聚合的名称、方法和字段，例如：{"cost_total":{"sum": {"field":"cost"}},
     * "cpu_usage_avg":{ "avg": { "field":"cpu_usage"}}}
     * @param string $interval 聚合粒度，如1s、5minute、1h、1d等
     * @param string|null $baseRollup 依赖的Rollup任务，任务执行前会检查相应时间段的依赖任务是否完成执行（可以不指定）
     * @param string|null $query 过滤数据的查询条件，由很多个元素和操作对组成，例如name:host AND type:max OR region:gz
     * @param array $copyTags 不需要聚合的维度列，group_tags确定时，多条数据的copy_tags的值相同
     * @param string|null $delay 延迟执行时间，写入数据通常有一定的延时，避免丢失数据
     * @param string|null $startTime 开始时间，从该时间开始周期性执行Rollup，默认为当前时间
     * @param string|null $endTime 结束时间，到达该时间后不再调度 ，默认为时间戳最大值
     * @param array $options 聚合选项，跟新建metric选项一致
     * @return array
     */
    public static function createRollup(string $rollupTaskName, string $baseMetric, string $rollupMetric,
                                        array $groupTags, array $fields, string $interval, string $baseRollup = null,
                                        string $query = null, array $copyTags = [], string $delay = null,
                                        string $startTime = null, string $endTime = null, array $options = []): array
    {
        $data = [
            'base_metric' => $baseMetric, 'rollup_metric' => $rollupMetric, 'group_tags' => $groupTags,
            'fields' => $fields, 'interval' => $interval,
        ];
        if ($baseRollup) {
            $data['base_rollup'] = $baseRollup;
        }
        if ($query) {
            $data['query'] = $query;
        }
        if (count($copyTags) > 0) {
            $data['copy_tags'] = $copyTags;
        }
        if ($delay) {
            $data['delay'] = $delay;
        }
        if ($startTime) {
            $data['start_time'] = $startTime;
        }
        if ($endTime) {
            $data['end_time'] = $endTime;
        }
        if (count($options)) {
            $data['options'] = $options;
        }
        return self::request(HttpUtil::PUT, '/_rollup/' . $rollupTaskName, [], $data);
    }

    /**
     * 获取所有Rollup任务
     *
     * @return array { "result": { "rollups": [ "rollup_jgq_6", "rollup_jgq_60" ] }, "status": 200 }
     * @throws HttpException
     */
    public static function getRollups(): array
    {
        return self::request(HttpUtil::GET, '/_rollups');
    }

    /**
     * 获取某个Rollup任务
     *
     * @param string $rollupTaskName 为Rollup任务的名称
     * @return array
     * @throws HttpException
     */
    public static function getRollup(string $rollupTaskName): array
    {
        return self::request(HttpUtil::GET, '/_rollup/' . $rollupTaskName);
    }

    /**
     * 删除Rollup任务
     *
     * @param string $rollupTaskName 为Rollup任务的名称
     * @return array
     * @throws HttpException
     */
    public static function deleteRollup(string $rollupTaskName): array
    {
        return self::request(HttpUtil::DELETE, '/_rollup/' . $rollupTaskName);
    }

    /**
     * 启动某个Rollup任务
     *
     * @param string $rollupTaskName 为Rollup任务的名称
     * @param string|null $startTime 开始时间，从该时间开始周期性执行Rollup，默认为当前时间
     * @param string|null $endTime 结束时间，到达改时间后不再调度，默认为时间戳最大值
     * @param array $options 聚合选项，跟新建metric选项一致
     * @return array
     * @throws HttpException
     */
    public static function startRollup(string $rollupTaskName,
                                       string $startTime = null, string $endTime = null, array $options = []): array
    {
        $data = ['state' => 'pause'];
        if ($endTime) {
            $data['end_time'] = $endTime;
        }
        if ($startTime) {
            $data['start_time'] = $startTime;
        }
        if (count($options) > 0) {
            $data['options'] = $options;
        }
        return self::request(HttpUtil::POST, '/_rollup/' . $rollupTaskName . '/update', [], $data);
    }

    /**
     * 停止某个Rollup任务
     *
     * @param string $rollupTaskName 为Rollup任务的名称
     * @param string|null $startTime 开始时间，从该时间开始周期性执行Rollup，默认为当前时间
     * @param string|null $endTime 结束时间，到达改时间后不再调度，默认为时间戳最大值
     * @param array $options 聚合选项，跟新建metric选项一致
     * @return array
     * @throws HttpException
     */
    public static function stopRollup(string $rollupTaskName,
                                      string $startTime = null, string $endTime = null, array $options = []): array
    {
        $data = ['state' => 'pause'];
        if ($endTime) {
            $data['end_time'] = $endTime;
        }
        if ($startTime) {
            $data['start_time'] = $startTime;
        }
        if (count($options) > 0) {
            $data['options'] = $options;
        }
        return self::request(HttpUtil::POST, '/_rollup/' . $rollupTaskName . '/update', [], $data);
    }

    /**
     * 发送HTTP请求并判断响应数据是否异常，在请求成功的情况下返回业务数据。
     *
     * @param string $method 请求类型
     * @param string $url 请求链接
     * @param string|array $params 附加到请求链接上的参数
     * @param string|array $data 在POST请求模式下发送的数据
     * @return array 对方业务处理成功的情况下会返回原数据
     * @throws HttpException
     */
    private static function request(string $method, string $url, $params = [], $data = []): array
    {
        $reqUrl = 'http://' . Yii::$app->params['ctsdb']['host'] . ':' . Yii::$app->params['ctsdb']['port'] . $url;
        if (0 < count($params)) {
            $reqUrl .= '?' . http_build_query($params);
        }

        $username = Yii::$app->params['ctsdb']['username'];
        $password = Yii::$app->params['ctsdb']['password'];
        $contentType = 'application/json';
        $data = is_array($data) ? json_encode($data) : $data;

        try {
            if (HttpUtil::GET == $method) {
                $response = HttpUtil::simpleGet($reqUrl, [], $username, $password);
            } else if (HttpUtil::POST == $method) {
                $response = HttpUtil::simplePost($reqUrl, $data, $username, $password, $contentType);
            } else if (HttpUtil::PUT == $method) {
                $response = HttpUtil::simplePut($reqUrl, $data, $username, $password, $contentType);
            } else if (HttpUtil::DELETE == $method) {
                $response = HttpUtil::simpleDelete($reqUrl, '', $username, $password, $contentType);
            } else {
                throw new HttpException('未知的请求类型:' . $method);
            }
        } catch (\Exception $e) {
            Yii::error('调用时序数据库接口:' . $reqUrl . '失败:' . $e->getTraceAsString(), __METHOD__);
            throw new HttpException('接口请求异常:' . $e->getMessage());
        }

        $responseAry = json_decode($response, true);
        if (!is_array($responseAry) || isset($responseAry['error'])) {
            Yii::error('调用时序数据库接口:' . $reqUrl . '失败:' . $response, __METHOD__);
            throw new HttpException("调用时序数据库接口失败");
        }

        return $responseAry;
    }
}