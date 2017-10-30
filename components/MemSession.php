<?php

namespace app\components;

use Yii;
use yii\web\Session as WebSession;

/**
 * <p>自定义会话数据维护类<p>
 *
 * 使用此类把会话信息存储到Memcache中，使用此方法后会替换掉PHP自己的会话管理。
 *
 * @package app\components
 */
class MemSession extends WebSession
{
    /**
     * @var string 缓存使用组件
     */
    public $memcache = 'cache';

    /**
     * @var string 键前缀
     */
    public $keyPrefix;

    /**
     * 初始化组件
     */
    public function init()
    {
        $this->memcache = Yii::$app->get($this->memcache);

        if ($this->keyPrefix === null) {
            $this->keyPrefix = substr(md5(Yii::$app->id), 0, 5);
        }
        parent::init();
    }

    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return boolean whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return true;
    }

    /**
     * Session read handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        $data = $this->memcache->get($this->calculateKey($id));
        return $data === false || $data === null ? '' : $data;
    }

    /**
     * Session write handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return boolean whether session write is successful
     */
    public function writeSession($id, $data)
    {
        return (bool)$this->memcache->set($this->calculateKey($id), $data, $this->getTimeout());
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return boolean whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        return $this->memcache->delete($this->calculateKey($id));
    }

    /**
     * Generates a unique key used for storing session data in cache.
     * @param string $id session variable name
     * @return string a safe cache key associated with the session variable name
     */
    protected function calculateKey($id)
    {
        return $this->keyPrefix . md5(json_encode([__CLASS__, $id]));
    }
}