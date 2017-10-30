<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\AppInfo */

$this->title = '第三方公众平台信息';
$this->params['breadcrumbs'][] = $this->title;

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

    function deleteCache(id) {
        if (confirm("你确定清除此公众号缓存数据吗？\n" +
                "清除缓存之后将直接从数据库读取，对业务并没有影响！")) {
            $('#myModal').modal('show');
            $.getJSON('/admin/component-delete-cache', {'id': id}, function (data) {
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
        if (confirm("你确定把第三方平台对其所有API调用次数清零吗？")) {
            $('#myModal').modal('show');
            $.getJSON('/admin/component-clear-quota', {'id': id}, function (data) {
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
<div class="app-info-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php
    foreach ($cmtInfos as $item => $cmtInfo) {
        $model = $cmtInfo->attributes();
        ?>
        <h3>No. <?= $item ?>
            <a class="btn btn-warning" href="javascript:deleteCache('<?= $cmtInfo->appId ?>');">清除缓存</a>
            <a class="btn btn-info" href="javascript:clearQuota('<?= $cmtInfo->appId ?>');">API调用次数清零</a>
        </h3>
        <table id="w0" class="table table-striped table-bordered detail-view">
            <tbody>

            <?php
            foreach ($model as $field) {
                $label = $cmtInfo->getAttributeLabel($field);
                $value = $cmtInfo->$field;
                if (isset($cmtInfo->$field) && ('zeroUpdatedAt' == $field || 'oneUpdatedAt' == $field)) {
                    $value = date('Y-m-d H:i:s', $value);
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

        <?php
    }
    ?>
</div>
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