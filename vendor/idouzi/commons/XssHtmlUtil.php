<?php

namespace Idouzi\Commons;

use Yii;

/**
 * PHP 富文本XSS过滤类
 *
 * @package XssHtml
 * @version 1.0.0
 * @link http://phith0n.github.io/XssHtml
 * @since 20140621
 * @copyright (c) Phithon All Rights Reserved
 *
 */

/**
 * 针对xss过滤的处理类
 * Class XssHtmlUtil
 * @package Idouzi\Commons
 */
class XssHtmlUtil
{
    /**
     * @var \DOMDocument
     */
    private $m_dom;
    private $m_xss;
    private $m_ok;
    private $m_AllowAttr = array('title', 'src', 'href', 'id', 'class', 'style', 'width', 'height', 'alt', 'target', 'align');
    private $m_AllowTag = array('section', 'a', 'iframe', 'img', 'br', 'strong', 'b', 'code', 'pre', 'p', 'div', 'em', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'ul', 'ol', 'tr', 'th', 'td', 'hr', 'li', 'u');
    private $entityCharAfter = array('&amp;');
    private $entityCharBefore = array('&');

    /**
     * 构造函数
     *
     * @param string $html 待过滤的文本
     * @param string $charset 文本编码，默认utf-8
     * @param array $allowTag 允许的标签，如果不清楚请保持默认，默认已涵盖大部分功能，不要增加危险标签
     */
    public function __construct($html = null, $charset = 'utf-8', $allowTag = array())
    {
        if ($html) {
            $this->filter($html, $charset, $allowTag);
        }

        //接受参数配置允许的属性
        if (isset(Yii::$app->params['xss']['whitelist']['allowAttr'])) {
            $this->m_AllowAttr = array_merge($this->m_AllowAttr, Yii::$app->params['xss']['whitelist']['allowAttr']);
        }
    }

    /**
     * 过滤标签，并把内容放进html里
     *
     * @param $html string 需要过滤的文本
     * @param string $charset 编码
     * @param array $allowTag 允许的html标签
     */
    public function filter($html, $charset = 'utf-8', $allowTag = array())
    {
        $this->m_AllowTag = empty($allowTag) ? $this->m_AllowTag : $allowTag;
        $this->m_xss = strip_tags($html, '<' . implode('><', $this->m_AllowTag) . '>');
        if ($this->m_xss === null) {
            $this->m_ok = FALSE;
            return;
        }
        $this->m_xss = "<meta http-equiv=\"Content-Type\" content=\"text/html;charset={$charset}\">" .
            '<nouse>' . $this->m_xss . '</nouse>';
        $this->m_dom = new \DOMDocument();
        $this->m_dom->strictErrorChecking = FALSE;
        $this->m_ok = @$this->m_dom->loadHTML($this->m_xss);
    }

    /**
     * 获得过滤后的内容
     */
    public function getHtml()
    {
        if (!$this->m_ok) {
            return '';
        }
        $nodeList = $this->m_dom->getElementsByTagName('*');
        for ($i = 0; $i < $nodeList->length; $i++) {
            $node = $nodeList->item($i);
            if (in_array($node->nodeName, $this->m_AllowTag)) {
                if (method_exists($this, "__node_{$node->nodeName}")) {
                    call_user_func(array($this, "__node_{$node->nodeName}"), $node);
                } else {
                    call_user_func(array($this, '__node_default'), $node);
                }
            }
        }
        $html = strip_tags($this->m_dom->saveHTML(), '<' . implode('><', $this->m_AllowTag) . '>');
        $html = preg_replace('/^\n(.*)\n$/s', '$1', $html);
        return $this->entityCharDecode($html);
    }

    /**
     * 将实体字符反转义
     *
     * @param string $html
     * @return mixed
     */
    private function entityCharDecode($html)
    {
        return str_replace($this->entityCharAfter, $this->entityCharBefore, $html);
    }

    /**
     * 返回真实的链接，带http或者https
     *
     * @param $url
     * @return string
     */
    private function __true_url($url)
    {
        if (preg_match('#^https?://.+#is', $url)) {
            return $url;
        } else {
            return 'http://' . $url;
        }
    }

    /**
     * 获取已经被过滤后的style属性
     *
     * @param $node \DOMDocument 标签节点
     * @return mixed|string
     */
    private function __get_style($node)
    {
        if ($node->attributes->getNamedItem('style')) {
            $style = $node->attributes->getNamedItem('style')->nodeValue;
            $style = str_replace('\\', ' ', $style);
            $style = str_replace(array('&#', '/*', '*/'), ' ', $style);
            $style = preg_replace('#expression#Uis', ' ', $style);
            return $style;
        } else {
            return '';
        }
    }

    /**
     * 获取某些节点里与链接属性相关的链接内容
     *
     * @param $node \DOMDocument 标签节点
     * @param $attr string 属性名
     * @return string 链接地址
     */
    private function __get_link($node, $attr)
    {
        $link = $node->attributes->getNamedItem($attr);
        if ($link) {
            return $this->__true_url($link->nodeValue);
        } else {
            return '';
        }
    }

    /**
     * 真正执行赋值
     *
     * @param $dom \DOMDocument 标签节点
     * @param $attr string 属性名
     * @param $val string|int 要设置的值
     */
    private function __setAttr($dom, $attr, $val)
    {
        if (!empty($val)) {
            $dom->setAttribute($attr, $val);
        } else {
            $dom->removeAttribute($attr);
        }
    }

    /**
     * 给属性重新设置值，如果没有则根据给予的值赋值
     *
     * @param $node \DOMDocument 标签节点
     * @param $attr string 属性名
     * @param string $default 默认值
     */
    private function __set_default_attr($node, $attr, $default = '')
    {
        $o = $node->attributes->getNamedItem($attr);
        if ($o) {
            $this->__setAttr($node, $attr, $o->nodeValue);
        } else {
            $this->__setAttr($node, $attr, $default);
        }
    }

    /**
     * 一般的属性处理
     *
     * @param $node \DOMDocument 标签节点
     */
    private function __common_attr($node)
    {
        $list = array();
        foreach ($node->attributes as $attr) {
            if (!in_array($attr->nodeName, $this->m_AllowAttr)) {
                $list[] = $attr->nodeName;
            }
        }
        foreach ($list as $attr) {
            $node->removeAttribute($attr);
        }
        $style = $this->__get_style($node);
        $this->__setAttr($node, 'style', $style);
        $this->__set_default_attr($node, 'title');
        $this->__set_default_attr($node, 'id');
        $this->__set_default_attr($node, 'class');
    }

    /**
     * 针对img标签特殊处理
     *
     * @param $node \DOMDocument 标签节点
     */
    private function __node_img($node)
    {
        $this->__common_attr($node);

        $this->__set_default_attr($node, 'src');
        $this->__set_default_attr($node, 'width');
        $this->__set_default_attr($node, 'height');
        $this->__set_default_attr($node, 'alt');
        $this->__set_default_attr($node, 'align');

    }

    /**
     * 针对a标签特殊处理
     *
     * @param $node \DOMDocument 标签节点
     */
    private function __node_a($node)
    {
        $this->__common_attr($node);
        $href = $this->__get_link($node, 'href');

        $this->__setAttr($node, 'href', $href);
    }

    /**
     * 针对iframe标签特殊处理
     *
     * @param $node \DOMDocument 标签节点
     */
    private function __node_iframe($node)
    {
        $this->__common_attr($node);

        if (isset(Yii::$app->params['xss']['whitelist']['domains'])) {
            $whiteList = implode('|', Yii::$app->params['xss']['whitelist']['domains']);
            $whiteList = str_replace('.', '\\.', $whiteList);
            $href = $this->__get_link($node, 'src');

            $preg = '/^https?:\/\/(\w+\.){1,2}' . $whiteList . '/is';
            //符合匹配规则则重新赋值
            if (preg_match($preg, $href)) {
                $this->__setAttr($node, 'src', $href);
            } else {
                $this->__setAttr($node, 'src', '');
            }
        }
    }

    /**
     * 针对embed标签特殊处理
     *
     * @param $node \DOMDocument 标签节点
     */
    private function __node_embed($node)
    {
        $this->__common_attr($node);
        $link = $this->__get_link($node, 'src');

        $this->__setAttr($node, 'src', $link);
        $this->__setAttr($node, 'allowscriptaccess', 'never');
        $this->__set_default_attr($node, 'width');
        $this->__set_default_attr($node, 'height');
    }


    /**
     * 一般标签节点处理方法
     *
     * @param $node \DOMDocument 标签节点
     */
    private function __node_default($node)
    {
        $this->__common_attr($node);
    }
}