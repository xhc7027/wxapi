<?php

namespace app\models\vo;

use app\commons\FileUtil;
use Yii;
use yii\base\Model;

/**
 * 新增其他类型永久素材表单数据模型
 *
 * @package app\models\vo
 */
class AddMaterialForm extends Model
{
    /**
     * @var string 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     */
    public $type;

    /**
     * @var string 临时文件路径
     */
    public $tmpName;

    public function rules()
    {
        return [
            [['tmpName', 'type'], 'required'],
            ['type', 'in', 'range' => ['image', 'voice', 'video', 'thumb']],
        ];
    }

    public function load($data, $formName = null)
    {
        if (isset($_FILES['file'])) {
            $tmpName = $_FILES['file']['tmp_name'];

            //构造文件临时存储路径
            $localTempPath = Yii::getAlias('@webroot') . '/upload/tmp_'
                . md5($tmpName)
                . FileUtil::getSuffixNameByType($_FILES['file']['type']);

            //移动图片到web/upload目录，重新命名图片并带上后缀名
            if (move_uploaded_file($tmpName, $localTempPath)) {
                //返回图片路径
                $this->tmpName = $localTempPath;
            }
        }

        return parent::load($data, $formName);
    }
}