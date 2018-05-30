<?php

namespace Idouzi\Commons;

use Yii;
use yii\helpers\Html;

/**
 * 对提交数据进行xss、sql过滤
 * Class SubmitDataUtil
 * @package Idouzi\Commons
 */
class SubmitDataUtil
{
    /**
     * 对get或者post输入进行xss过滤
     */
    public static function filterParam()
    {
        $get = Yii::$app->request->get();
        $post = Yii::$app->request->post();
        $_GET = $get ? self::pregParam($get) : [];
        Yii::$app->request->setQueryParams($_GET);
        $_POST = $post ? self::pregParam($post) : [];
        Yii::$app->request->setBodyParams($_POST);
    }

    /**
     * 对get和post输入进行html转义
     */
    public static function htmlFilterParam()
    {
        $get = Yii::$app->request->get();
        $post = Yii::$app->request->post();
        $_GET = $get ? self::htmlEncode($get) : [];
        Yii::$app->request->setQueryParams($_GET);
        $_POST = $post ? self::htmlEncode($post) : [];
        Yii::$app->request->setBodyParams($_POST);
    }

    /**
     * 进行sql过滤
     */
    public static function sqlFilterParam()
    {
        $get = Yii::$app->request->get();
        $post = Yii::$app->request->post();
        $_GET = $get ? self::addSlashesFilter($get) : [];
        Yii::$app->request->setQueryParams($_GET);
        $_POST = $post ? self::addSlashesFilter($post) : [];
        Yii::$app->request->setBodyParams($_POST);
    }

    /**
     * sql过滤的具体业务处理
     * @param $request
     * @return array|string
     */
    public static function addSlashesFilter($request)
    {
        if (is_array($request)) {
            foreach ($request as $key => $val) {
                $request[$key] = self::addSlashesFilter($val);
            }
        } else {
            //简单的sql过滤
            $request = addslashes($request);
        }
        return $request;
    }

    /**
     * 进行html编码特殊字符转义
     *
     * @param $request array|string 需要过滤的选项
     * @return array|string
     */
    public static function htmlEncode($request)
    {
        if (is_array($request)) {
            foreach ($request as $key => $val) {
                $request[$key] = self::htmlEncode($val);
            }
        } else {
            $request = Html::encode($request);
        }
        return $request;
    }

    /**
     * @param array $request 用户提供过来的数据
     * @return array|mixed 返回过滤后的数据
     */
    public static function pregParam($request, $xssClass = null)
    {
        if (!($xssClass instanceof XssHtmlUtil)) {
            $xssClass = new XssHtmlUtil();
        }

        if (is_array($request)) {
            foreach ($request as $key => $val) {
                $request[$key] = self::pregParam($val, $xssClass);
            }
        } else {
            $xssClass->filter($request);
            $request = $xssClass->getHtml();
        }
        return $request;
    }

    /**
     * iframe过滤
     *
     * @param $request
     * @return string
     */
    public static function iFrameFilter($request)
    {
        if (!stripos($request, 'iframe')) {
            return $request;
        }
        $whiteList = implode('|', Yii::$app->params['domainWhiteList']);
        $whiteList = str_replace('.', '\\.', $whiteList);
        $preg2 = "/<iframe.*\ssrc=[\"|\']{0,1}http:\/\/(\w+\.){1,2}(" . $whiteList . ")+.*(<\s*\/\s*iframe\s*>|(\/|\s*>))/is";
        //符合匹配规则需要进一步确认只有一个src
        if (preg_match($preg2, $request)) {
            if (count(explode('src', $request)) > 2) {
                $request = preg_replace($preg2, "", $request);
            }
        } else
            $request = '';
        return $request;
    }

}