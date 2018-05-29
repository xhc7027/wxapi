<?php

namespace Idouzi\Commons;

use Yii;
use yii\base\UserException;

/**
 * 文件常用操作工具类
 *
 * @package app\common
 */
class FileUtil
{
    /**
     * @param string  $filePath 文件路径（包含文件名称）
     * @param string  $pathAlias 路径别名（默认是/web/下面）
     * @param boolean $pathType 标识文件路径是否传的绝对路径（默认是相对路径）
     * @throws UserException
     * @return boolean 返回true表示删除成功
     */
    public static function delete($filePath, $pathAlias = '@webroot', $pathType = true)
    {
        if ($pathType)
            $filePath = Yii::getAlias($pathAlias) . '/' . $filePath;

        //判断文件是否存在
        if (file_exists($filePath))
            return unlink($filePath);

        throw new UserException('不能删除一个不存在的文件');
    }

    /**
     * 根据本地绝对全路径删除文件
     *
     * @param string $filePath
     * @return bool
     */
    public static function deleteFileByAbsolutePath(string $filePath): bool
    {
        //判断文件是否存在
        if (file_exists($filePath))
            return unlink($filePath);

        return false;
    }

    /**
     * <p>从文件类型中拆解出文件后缀名</p>
     * 文件类型由两部分构成，一是文件的主要类型（image），二是具体的后缀名称（jpg）。
     *
     * @param string $type 文件上传过来的原始文件类型
     * @return string 带小数点的后缀明
     */
    public static function getSuffixNameByType(string $type): string
    {
        //找到“/”线处并分割
        return '.' . substr($type, strpos($type, '/') + 1, strlen($type));
    }
}