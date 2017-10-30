<?php

/* @var $this yii\web\View */

$this->title = '业务状态监控中心';
?>
<style type="text/css">
    span {
        padding: 5px 10px 5px 10px;
        background-color: rgb(242, 242, 242);
        border-radius: 2px;
    }
</style>
<p>
    <span><a href="javascript:getDayReport();">今天</a></span>
    <span><a href="javascript:getYesterdayReport();">昨天</a></span>
    <span><a href="javascript:getHalfMonthReport();">最近7天</a></span>
</p>

<div id="internalCall" style="width: 50%;height:400px;float: left">数据加载中...</div>
<div id="internalTime" style="width: 50%;height:400px;float: right">数据加载中...</div>
<div id="externalCall" style="width: 50%;height:400px;float: left">数据加载中...</div>
<div id="externalTime" style="width: 50%;height:400px;float: right">数据加载中...</div>
<script type="text/javascript">
    function initBar(id, xAxisData, yAxisData, title) {
        myChart = echarts.init(document.getElementById(id));
        option = {
            title: {
                text: title
            },
            color: ['rgb(194,53,49)'],
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '3%',
                containLabel: true
            },
            xAxis: [
                {
                    type: 'category',
                    boundaryGap: true,
                    data: xAxisData
                }
            ],
            yAxis: [
                {
                    type: 'value',
                    axisLabel: {
                        formatter: '{value} 次数'
                    }
                }
            ],
            /*dataZoom: [
                {
                    id: 'dataZoomX',
                    type: 'slider',
                    xAxisIndex: [0],
                    filterMode: 'filter',
                    start: 40,
                    end: 100
                }
            ],*/
            series: [
                {
                    type: 'bar',
                    data: yAxisData
                }
            ]
        };

        // 使用刚指定的配置项和数据显示图表。
        myChart.setOption(option);
    }

    function initLine(id, xAxisData, yAxisDatas, title) {
        myChart = echarts.init(document.getElementById(id));
        option = {
            title: {
                text: title
            },
            tooltip: {
                trigger: 'axis'
            },
            xAxis: {
                type: 'category',
                data: xAxisData
            },
            yAxis: {
                type: 'value',
                boundaryGap: false,
                axisLabel: {
                    formatter: '{value} 秒'
                }
            },
            /*dataZoom: [
                {
                    id: 'dataZoomX',
                    type: 'slider',
                    xAxisIndex: [0],
                    filterMode: 'filter',
                    start: 40,
                    end: 100
                }
            ],*/
            series: [
                {
                    name: '最长耗时',
                    type: 'line',
                    data: yAxisDatas[1],
                    markLine: {
                        data: [
                            {type: 'average', name: '平均值'}
                        ]
                    }
                },
                {
                    name: '最短耗时',
                    type: 'line',
                    data: yAxisDatas[0],
                    markLine: {
                        data: [
                            {type: 'average', name: '平均值'}
                        ]
                    }
                }
            ]
        };

        // 使用刚指定的配置项和数据显示图表。
        myChart.setOption(option);
    }

    function getReport(type, ids, titles, cycle) {
        $.getJSON('/admin/report', {type: type, cycle: cycle}, function (data) {
            if (data.return_code == 'SUCCESS') {
                if (!data.return_msg) {
                    alert('统计不到数据');
                    return;
                }
                if (!data.return_msg.xAxisData) {
                    document.getElementById(ids[0]).innerHTML = '没有数据';
                    document.getElementById(ids[1]).innerHTML = '没有数据';
                    return;
                }
                initBar(ids[0], data.return_msg.xAxisData, data.return_msg.yAxisDataNumber, titles[0]);
                initLine(ids[1], data.return_msg.xAxisData,
                    [data.return_msg.yAxisDataMinTimeConsume, data.return_msg.yAxisDataMaxTimeConsume], titles[1]);
            } else {
                alert(data.return_msg);
            }
        });
    }

    function getDayReport() {
        getReport(0, ['internalCall', 'internalTime'], ['外部请求每小时调用次数', '外部请求每小时处理耗时'], 0);
        getReport(1, ['externalCall', 'externalTime'], ['内部请求每小时调用次数', '内部请求每小时处理耗时'], 0);
    }
    function getYesterdayReport() {
        getReport(0, ['internalCall', 'internalTime'], ['外部请求每小时调用次数', '外部请求每小时处理耗时'], 1);
        getReport(1, ['externalCall', 'externalTime'], ['内部请求每小时调用次数', '内部请求每小时处理耗时'], 1);
    }
    function getHalfMonthReport() {
        getReport(0, ['internalCall', 'internalTime'], ['外部请求每天调用次数', '外部请求每天处理耗时'], 2);
        getReport(1, ['externalCall', 'externalTime'], ['内部请求每天调用次数', '内部请求每天处理耗时'], 2);
    }

    getDayReport();
</script>
