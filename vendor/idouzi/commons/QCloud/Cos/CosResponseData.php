<?php

namespace Idouzi\Commons\QCloud\Cos;

use yii\base\Model;

/**
 * 腾讯云对象存储返回结果
 *
 * @package app\models
 */
class CosResponseData extends Model
{
    /**
     * @var string 通过 CDN 访问该文件的资源链接（访问速度更快）
     */
    public $accessUrl;

    /**
     * @var string 该文件在 COS 中的相对路径名，可作为其 ID 标识。 格式/<APPID>/<BucketName>/<ObjectName>。
     * 推荐业务端存储 resource_path，然后根据业务需求灵活拼接资源 url（通过 CDN 访问 COS 资源的 url 和直接访问 COS 资源的 url 不同）。
     */
    public $resourcePath;

    /**
     * @var string （不通过 CDN）直接访问 COS 的资源链接
     */
    public $sourceUrl;

    /**
     * @var string 操作文件的 url 。业务端可以将该 url 作为请求地址来进一步操作文件，
     * 对应 API ：文件属性、更新文件、删除文件、移动文件中的请求地址。
     */
    public $url;
}