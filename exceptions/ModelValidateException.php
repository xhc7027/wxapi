<?php

namespace app\exceptions;

use Exception;

/**
 * 模型校验失败错误
 *
 * @package app\exceptions
 */
class ModelValidateException extends Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'ModelValidateException';
    }
}