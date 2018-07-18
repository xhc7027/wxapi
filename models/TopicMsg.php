<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/11 0011
 * Time: 15:18
 */

namespace app\models;


use yii\base\Model;

class TopicMsg extends Model
{
    public $type;
    public $data;
    public $createAt;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // type、createAt、data是必须存在的
            [['type', 'createAt', 'data'], 'required'],
            // createAt必须是时间型
            ['createAt', 'date'],
            // data必须是字符串型
            [['data', 'createAt'], 'string'],
            ['type', 'in', 'range' => ['重新绑定', '解绑']]
        ];
    }
}