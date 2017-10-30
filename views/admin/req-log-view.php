<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\RequestLogger */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => '请求监控报表', 'url' => ['admin/req-log-index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="request-logger-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <table id="w0" class="table table-striped table-bordered detail-view">
        <tbody>
        <?php
        $reqLog = $model->attributes();
        foreach ($reqLog as $field) {
            $label = $model->getAttributeLabel($field);
            $value = $model->$field;
            if (isset($model->$field)) {
                if ('type' == $field) {
                    switch ($value) {
                        case 0:
                            $value = '内部调用';
                            break;
                        case 1:
                            $value = '微信回调';
                            break;
                        case 2:
                            $value = '调用微信';
                            break;
                    }
                } else if ('postStr' == $field || 'respStr' == $field) {
                    $value = Html::encode($value);
                }
            }
            ?>
            <tr>
                <th><?= $label ?></th>
                <td><?= $value ?></td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>

</div>