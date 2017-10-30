<?php

namespace app\exceptions;

use Exception;

/**
 * 系统错误
 *
 * @package app\exceptions
 */
class SystemException extends Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'SystemException';
    }
}