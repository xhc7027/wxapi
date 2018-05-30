<?php

namespace Idouzi\Commons;

use yii\base\Exception;


class ExcelExportUtil
{
    /**
     * @var \PHPExcel
     */
    private $phpExcel;

    /**
     * @var array 标题样式配置
     */
    private $titleCssConfig;

    /**
     * @var array 表头样式配置
     */
    private $headCssConfig;

    /**
     * @var array 表格样式配置
     */
    private $formCssConfig;

    /**
     * @var array 要显示的单元格['A', 'B' ,... ,'Z', 'AA', ...]
     */
    private $cells = null;

    /**
     * @var array 单元格范围数组中文名称
     */
    private $cellHeadArr = [];

    /**
     * @var array 单元格范围数组英文键名称
     */
    private $cellHeadKeyNameArr = [];

    /**
     * @param string $titleName execl标题名称
     * @param array $cellHeadArr execl每个单元格的栏目的名称，以['name' => '姓名', 'phone' => '手机号']的格式
     */
    public function __construct(string $titleName, array $cellHeadArr)
    {
        $this->init();

        $this->setCellNames($cellHeadArr);
        $this->setCells(count($cellHeadArr));

        $this->setExcelTitle($titleName);
        $this->setExcelHead();
    }

    /**
     * 获取excel实例
     * @return \PHPExcel
     */
    public function getPhpExcel()
    {
        return $this->phpExcel;
    }

    /**
     * 初始化
     */
    private function init()
    {
        $this->phpExcel = new \PHPExcel();
        $this->phpExcel->getProperties()->setCreator("Maarten Balliauw")
            ->setLastModifiedBy("Maarten Balliauw")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("Test result file");
    }

    /**
     * 设置单元格称呼的中文和键值名
     * @param array $cellHeadArr
     */
    public function setCellNames(array $cellHeadArr)
    {
        foreach ($cellHeadArr as $key => $value) {
            $this->cellHeadArr[] = $value;
            $this->cellHeadKeyNameArr[] = $key;
        }
    }

    /**
     * 设置标题样式
     * @param string $titleName
     * @param array|null $config
     * @throws Exception
     */
    public function setExcelTitle(string $titleName, array $config = null)
    {
        $this->checkCellsExist();

        $config ? $this->titleCssConfig = $config : $this->setDefaultTitleCssConfig();

        $this->phpExcel->setActiveSheetIndex(0)->setCellValue(reset($this->cells) . '1', $titleName);
        $this->phpExcel->getActiveSheet()->getStyle(reset($this->cells) . '1')
            ->applyFromArray($this->titleCssConfig);

        $this->phpExcel->getActiveSheet()->mergeCells($this->getCellRangeStr(1));
    }

    /**
     * 设置excel头样式
     * @param array|null $config
     * @param string $startColor 开头样式的颜色
     */
    public function setExcelHead(array $config = null, $startColor = 'FF7292BE')
    {
        $this->checkCellsExist();
        $config ? $this->headCssConfig = $config : $this->setDefaultHeadCssConfig();

        $this->setHeadCellValue();

        $this->phpExcel->getActiveSheet()->getStyle($this->getCellRangeStr(2))->applyFromArray($this->headCssConfig);

        $this->phpExcel->getActiveSheet()->getStyle($this->getCellRangeStr(2))
            ->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);
        $this->phpExcel->getActiveSheet()->getStyle($this->getCellRangeStr(2))
            ->getFill()->getStartColor()->setARGB($startColor);
    }

    /**
     * 设置表单里的样式
     * @param int $endIndex
     * @param array $config
     */
    public function setExcelForm(int $endIndex, array $config)
    {
        $this->phpExcel->getActiveSheet()
            ->getStyle($this->getCellRangeStr(2, $endIndex))->applyFromArray($config);
    }

    /**
     * 设置单元格宽度
     * @param array $cellWidthArr 以键值对的形式确定对应单元格的宽度， 键名为单元格位置 ，值为宽度 ['A' => 25, 'D' => 20]
     */
    public function setExcelCellWidth(array $cellWidthArr)
    {
        foreach ($cellWidthArr as $cell => $width) {
            $this->phpExcel->getActiveSheet()->getColumnDimension($cell)->setWidth($width);
        }
    }

    /**
     * 设置工作薄
     * @param int $index 下标，用于确定当前工作薄
     * @param string $titleName 工作簿的标题
     */
    public function setWorkBook(string $titleName, int $index = 0)
    {
        //设置当前工作簿为
        $this->setCurrentSheet($index);
        //设置活动工作簿的标题
        $this->phpExcel->getActiveSheet()->setTitle($titleName);
    }

    /**
     * 设置当前工作簿
     * @param int $index 下标，用于确定当前工作薄是哪个
     */
    public function setCurrentSheet(int $index)
    {
        $this->phpExcel->setActiveSheetIndex($index);
    }

    /**
     * 设置excel表单里的数据
     * @param array $data 具体数据，二维数组
     * @throws Exception
     */
    public function setExcelData(array $data, array $config = null)
    {
        $config ? $this->formCssConfig = $config : $this->setDefaultFormCssConfig();
        if (!is_array(reset($data))) {
            throw new Exception('填充excel数据必须是一个二维数组:'. json_encode($data));
        }
        $num = 3;
        foreach ($data as $index => $item) {
            for ($point = 0, $count = count($item), reset($this->cells), reset($this->cellHeadKeyNameArr);
                 $point < $count && current($this->cells) && current($this->cellHeadKeyNameArr);
                 $point++, next($this->cells), next($this->cellHeadKeyNameArr)) {
                $this->phpExcel->getActiveSheet()
                    ->setCellValue(current($this->cells) . $num, "\t". $item[current($this->cellHeadKeyNameArr)]. "\t");
            }
            $num++;
        }
        $this->setExcelForm(--$num, $this->formCssConfig);
    }

    /**
     * 真正的将数据导出到浏览器
     * @param $fileName
     */
    public function doExportToBrowser($fileName)
    {
        ob_end_clean();//清除缓冲区,避免乱码
        $name = mb_convert_encoding($fileName, 'gb2312', 'UTF-8');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $name . '.xls"');

        $objWriter = \PHPExcel_IOFactory::createWriter($this->phpExcel, 'Excel5');
        $objWriter->save('php://output');
        flush();
        ob_flush();
    }


    /**
     * 设置头部的单元格名称
     */
    private function setHeadCellValue()
    {
        reset($this->cells);
        foreach ($this->cells as $cell) {
            $this->phpExcel->getActiveSheet()->setCellValue($cell . '2', current($this->cellHeadArr));
            next($this->cellHeadArr);
        }
        reset($this->cellHeadArr);
    }

    /**
     * 判断cells是否已经被设置
     * @throws Exception
     */
    private function checkCellsExist()
    {
        if ($this->cells == null) {
            throw new Exception('必须设置单元格范围才能调用方法：' . __METHOD__);
        }
    }

    /**
     * 设置单元格总共列名 A,B,C...AA,AB,AC...ZA,ZB,ZC...AAA
     *
     * @param int $count
     */
    private function setCells(int $count)
    {
        for ($inc = 0; $inc < $count; $inc++) {
            $str = '';
            //十进制转为27进制，26个字母，所以是27进制,base_convert当为10时是a,11是b以此类推
            $convertString = base_convert($inc, 10, 27);
            //将得到的字符拆开，对每个字符转为我们要显示的实际字符1->A,a->J
            $convertArr = str_split($convertString, 1);
            //如果大于一个字符，最高位没有0，所以这里要做特殊处理
            //ASCII码 65是A，a是97
            if (count($convertArr) > 1) {
                $str .= ($convertArr[0] >= '1' && $convertArr[0] <= '9') ? chr(64 + $convertArr[0]) :
                    chr(ord($convertArr[0]) - 22);
                unset($convertArr[0]);
            }
            foreach ($convertArr as $item) {
                $str .= ($item >= '0' && $item <= '9') ? chr(65 + $item) : chr(ord($item) - 22);
            }
            $this->cells[] = $str;
        }
    }

    /**
     * 获取cell范围的字符串描述：A1:I1
     * @param int $index 开头的下标
     * @param int $endIndex 结束的下标
     * @return string
     */
    private function getCellRangeStr(int $index = 1, int $endIndex = null)
    {
        !$endIndex && $endIndex = $index;
        $returnStr = reset($this->cells) . $index . ':' . end($this->cells) . $endIndex;

        reset($this->cells);
        return $returnStr;
    }

    /**
     * 设置默认标题样式配置
     */
    private function setDefaultTitleCssConfig()
    {
        $this->titleCssConfig = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FF000000'],
            ],
            'alignment' => [
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allborders' => [
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,//细边框
                ],
            ],
        ];
    }

    /**
     * 设置默认标题样式配置
     */
    private function setDefaultHeadCssConfig()
    {
        $this->headCssConfig = [
            'font' => [
                'color' => [
                    'argb' => 'FFF0F4F7',
                ],
            ],
            'alignment' => [
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ],
        ];
    }

    /**
     * 设置默认表单的样式
     */
    private function setDefaultFormCssConfig()
    {
        $this->formCssConfig = [
            'alignment' => array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'wraptext' => true,
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,//细边框
                ),
            ),
        ];
    }
}