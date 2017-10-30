<?php

use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '代运营公众号报表';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="app-info-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?= Html::a('清除缓存', '/admin/app-delete-cache', [
        'class' => 'btn btn-warning',
        'data' => [
            'confirm' => "你确定清除所有公众号缓存数据吗？\n清除缓存之后将直接从数据库读取，对业务并没有影响！",
            'method' => 'post',
        ],
    ]) ?>

    <?= $this->render('_app-info-search', ['model' => $searchModel]); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'class' => 'yii\grid\DataColumn',
                'format' => 'html',
                'attribute' => 'headImg',
                'value' => function ($model, $key, $index, $column) {
                    return Html::img($model->headImg, ['width' => 60, 'height' => 60]);
                }
            ],
            'appId:text:AppID',
            'nickName:text:昵称',
            [
                'class' => 'yii\grid\DataColumn',
                'attribute' => 'serviceTypeInfo',
                'label' => '公众号类型',
                'value' => function ($model, $key, $index, $column) {
                    $type = [
                        '0' => '订阅号',
                        '1' => '订阅号',
                        '2' => '服务号',
                    ];
                    if (isset($model->serviceTypeInfo))
                        return $type[$model->serviceTypeInfo];
                    return null;
                }
            ],
            [
                'class' => 'yii\grid\DataColumn',
                'attribute' => 'verifyTypeInfo',
                'label' => '认证类型',
                'value' => function ($model, $key, $index, $column) {
                    $type = [
                        '-1' => '未认证',
                        '0' => '微信认证',
                        '1' => '新浪微博认证',
                        '2' => '腾讯微博认证',
                        '3' => '已资质认证但名称未认证',
                        '4' => '已资质认证但名称未认证，已新浪微博认证',
                        '5' => '已资质认证但名称未认证，已腾讯微博认证',
                    ];
                    if (isset($model->verifyTypeInfo))
                        return $type[$model->verifyTypeInfo];
                    return null;
                }
            ],
            'userName:text:原始ID',
            'alias:text:微信号',
            [
                'class' => 'yii\grid\DataColumn',
                'attribute' => 'infoType',
                'label' => '授权状态',
                'value' => function ($model, $key, $index, $column) {
                    $type = [
                        'unauthorized' => '取消授权',
                        'updateauthorized' => '更新授权',
                        'authorized' => '授权成功',
                    ];
                    if (isset($model->infoType))
                        return $type[$model->infoType];
                    return null;
                }
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
                'buttons' => [
                    'view' => function ($url, $model, $key) {
                        return Html::a('<span class="glyphicon glyphicon-eye-open"></span>', '/admin/app-info-view?id=' . $model->appId);
                    },
                ]
            ],
        ],
    ]); ?>
</div>