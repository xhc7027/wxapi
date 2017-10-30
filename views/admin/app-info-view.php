<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\AppInfo */

$this->title = $model->appId;
$this->params['breadcrumbs'][] = ['label' => '代运营公众号报表', 'url' => ['admin/app-info-index']];
$this->params['breadcrumbs'][] = $this->title;

$prmSet = [
    '1' => '消息管理权限',
    '2' => '用户管理权限',
    '3' => '帐号服务权限',
    '4' => '网页服务权限',
    '5' => '微信小店权限',
    '6' => '微信多客服权限',
    '7' => '群发与通知权限',
    '8' => '微信卡券权限',
    '9' => '微信扫一扫权限',
    '10' => '微信连WIFI权限',
    '11' => '素材管理权限',
    '12' => '微信摇周边权限',
    '13' => '微信门店权限',
    '14' => '微信支付权限',
    '15' => '自定义菜单权限',
];
?>
<script>
    // implement JSON.stringify serialization
    JSON.stringify = JSON.stringify || function (obj) {
            var t = typeof (obj);
            if (t != "object" || obj === null) {
                // simple data type
                if (t == "string") obj = '"' + obj + '"';
                return String(obj);
            }
            else {
                // recurse array or object
                var n, v, json = [], arr = (obj && obj.constructor == Array);
                for (n in obj) {
                    v = obj[n];
                    t = typeof(v);
                    if (t == "string") v = '"' + v + '"';
                    else if (t == "object" && v !== null) v = JSON.stringify(v);
                    json.push((arr ? "" : '"' + n + '":') + String(v));
                }
                return (arr ? "[" : "{") + String(json) + (arr ? "]" : "}");
            }
        };
    function getAppBaseInfo(id) {
        if (confirm("你确定重新获取授权方的公众号帐号基本信息吗？\n" +
                "该API用于获取授权方的公众号基本信息，包括头像、昵称、帐号类型、认证类型、微信号、原始ID和二维码图片URL。")) {
            $('#myModal').modal('show');
            $.getJSON('/admin/get-app-base-info', {'id': id}, function (data) {
                if (data.return_code == 'SUCCESS') {
                    alert('更新成功！');
                    location.replace(location.href);
                } else {
                    $('.modal-body').html(JSON.stringify(data.return_msg));
                }
            });
        }
    }
    function clearQuota(id) {
        if (confirm("你确定对公众号的所有API调用（包括第三方代公众号调用）次数进行清零！")) {
            $('#myModal').modal('show');
            $.getJSON('/admin/app-info-clear-quota', {'id': id}, function (data) {
                if (data.return_code == 'SUCCESS') {
                    alert('更新成功！');
                    location.replace(location.href);
                } else {
                    $('.modal-body').html(JSON.stringify(data.return_msg));
                }
            });
        }
    }
</script>
<!-- Modal -->
<div class="modal fade" id="myModal" role="dialog">
    <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">处理结果</h4>
            </div>
            <div class="modal-body">请稍等...</div>
        </div>

    </div>
</div>
<div class="app-info-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <a class="btn btn-primary" href="javascript:getAppBaseInfo('<?= $model->appId ?>');">获取公众号基本信息</a>
        <a class="btn btn-info" href="javascript:clearQuota('<?= $model->appId ?>');">API调用次数清零</a>
        <?= Html::a('更新', '/admin/app-info-update?id=' . $model->appId, [
            'class' => 'btn btn-warning',
            'data' => [
                'confirm' => "你确定更新此公众号吗？\n更新一些字段之后可能造成公众号不稳定！",
                'method' => 'post',
            ],
        ]) ?>
        <?= Html::a('删除', '/admin/app-info-delete?id=' . $model->appId, [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => "你确定删除此公众号吗？\n删除之后需要用户重新绑定！",
                'method' => 'post',
            ],
        ]) ?>
        <?= Html::a('清除缓存', '/admin/app-delete-cache?id=' . $model->appId, [
            'class' => 'btn btn-warning',
            'data' => [
                'confirm' => "你确定清除此公众号缓存数据吗？\n清除缓存之后将直接从数据库读取，对业务并没有影响！",
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <table id="w0" class="table table-striped table-bordered detail-view">
        <tbody>
        <?php
        $appInfo = $model->attributes();
        foreach ($appInfo as $field) {
            $label = $model->getAttributeLabel($field);
            $value = $model->$field;
            if (isset($model->$field)) {
                if ('funcScopeCategory' == $field) {
                    $fucAry = json_decode($model->$field, true);
                    foreach ($fucAry as $category) {
                        $value .= $prmSet[$category] . ', ';
                    }
                } else if ('zeroUpdatedAt' == $field) {
                    $value = date('Y-m-d H:i:s', $value);
                } else if ('headImg' == $field) {
                    $value = Html::img($value);
                } else if ('serviceTypeInfo' == $field) {
                    switch ($value) {
                        case 0:
                            $value = '订阅号';
                            break;
                        case 1:
                            $value = '由历史老帐号升级后的订阅号';
                            break;
                        case 2:
                            $value = '服务号';
                            break;
                    }
                } else if ('verifyTypeInfo' == $field) {
                    switch ($value) {
                        case -1:
                            $value = '未认证';
                            break;
                        case 0:
                            $value = '微信认证';
                            break;
                        case 1:
                            $value = '新浪微博认证';
                            break;
                        case 2:
                            $value = '腾讯微博认证';
                            break;
                        case 3:
                            $value = '已资质认证通过但还未通过名称认证';
                            break;
                        case 4:
                            $value = '已资质认证通过、还未通过名称认证，但通过了新浪微博认证';
                            break;
                        case 5:
                            $value = '已资质认证通过、还未通过名称认证，但通过了腾讯微博认证';
                            break;
                    }
                } else if ('businessInfoOpenStore' == $field) {
                    if (0 === $value) {
                        $value = '未开通';
                    } else {
                        $value = '已开通';
                    }
                } else if ('businessInfoOpenScan' == $field) {
                    if (0 === $value) {
                        $value = '未开通';
                    } else {
                        $value = '已开通';
                    }
                } else if ('businessInfoOpenPay' == $field) {
                    if (0 === $value) {
                        $value = '未开通';
                    } else {
                        $value = '已开通';
                    }
                } else if ('businessInfoOpenCard' == $field) {
                    if (0 === $value) {
                        $value = '未开通';
                    } else {
                        $value = '已开通';
                    }
                } else if ('businessInfoOpenShake' == $field) {
                    if (0 === $value) {
                        $value = '未开通';
                    } else {
                        $value = '已开通';
                    }
                } else if ('oneUpdatedAt' == $field) {
                    $value = date('Y-m-d H:i:s', $value);
                } else if ('infoType' == $field) {
                    switch ($value) {
                        case 'unauthorized':
                            $value = '取消授权';
                            break;
                        case 'updateauthorized':
                            $value = '更新授权';
                            break;
                        case 'authorized':
                            $value = '授权成功';
                            break;
                    }
                } else if ('authorizationCodeExpiredTime' == $field) {
                    $value = date('Y-m-d H:i:s', $value);
                } else if ('twoUpdatedAt' == $field) {
                    $value = date('Y-m-d H:i:s', $value);
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