<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\AppInfo */

$this->title = '分享公众号列表';
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

    function clearQuota(id) {
        if (confirm("你确定把此公众号API调用次数清零吗？")) {
            $('#myModal').modal('show');
            $.getJSON('/admin/app-share-clear-quota', {'id': id}, function (data) {
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
    foreach ($list as $item => $app) {
        $model = $app->attributes();
        ?>
        <h3>No. <?= $item ?>
            <a class="btn btn-info" href="javascript:clearQuota('<?= $app->appId ?>');">API调用次数清零</a>
        </h3>
        <table id="w0" class="table table-striped table-bordered detail-view">
            <tbody>

            <?php
            foreach ($model as $field) {
                $label = $app->getAttributeLabel($field);
                $value = $app->$field;
                if (isset($app->$field) && ('ticketTime' == $field || 'tokenTime' == $field)) {
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