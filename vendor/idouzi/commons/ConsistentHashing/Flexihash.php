<?php

namespace Idouzi\Commons\ConsistentHashing;


class Flexihash
{

    /**
     * The number of positions to hash each target to.
     *
     * @var int
     * @comment 虚拟节点数,解决节点分布不均的问题
     */
    private $_replicas = 64;

    /**
     * The hash algorithm, encapsulated in a Flexihash_Hasher implementation.
     * @var object Flexihash_Hasher
     * @comment 使用的hash方法 : md5,crc32
     */
    private $_hasher;

    /**
     * Internal counter for current number of targets.
     * @var int
     * @comment 节点记数器
     */
    private $_targetCount = 0;

    /**
     * Internal map of positions (hash outputs) to targets
     * @var array { position => target, ... }
     * @comment 位置对应节点,用于lookup中根据位置确定要访问的节点
     */
    private $_positionToTarget = array();

    /**
     * Internal map of targets to lists of positions that target is hashed to.
     * @var array { target => [ position, position, ... ], ... }
     * @comment 节点对应位置,用于删除节点
     */
    private $_targetToPositions = array();

    /**
     * Whether the internal map of positions to targets is already sorted.
     * @var boolean
     * @comment 是否已排序
     */
    private $_positionToTargetSorted = false;

    /**
     * Constructor
     * @param object $hasher Flexihash_Hasher
     * @param int $replicas Amount of positions to hash each target to.
     * @comment 构造函数,确定要使用的hash方法和需拟节点数,虚拟节点数越多,分布越均匀,但程序的分布式运算越慢
     */
    public function __construct(Flexihash_Hasher $hasher = null, $replicas = null)
    {
        $this->_hasher = $hasher ? $hasher : new FlexihashCrc32Hasher();
        if (!empty($replicas)) {
            $this->_replicas = $replicas;
        }
    }

    /**
     * Add a list of targets.
     * @param array $targets
     * @chainable
     */
    public function addTargets($targets)
    {
        foreach ($targets as $target) {
            $this->addTarget($target);
        }

        return $this;
    }

    /**
     * Add a target.
     * @param string $target
     * @chainable
     * @comment 添加节点,根据虚拟节点数,将节点分布到多个虚拟位置上
     */
    public function addTarget($target)
    {
        if (isset($this->_targetToPositions[$target])) {
            throw new FlexihashException("Target '$target' already exists.");
        }

        $this->_targetToPositions[$target] = array();

        // hash the target into multiple positions
        for ($i = 0; $i < $this->_replicas; $i++) {
            $position = $this->_hasher->hash($target . $i);
            $this->_positionToTarget[$position] = $target; // lookup
            $this->_targetToPositions[$target] [] = $position; // target removal
        }

        $this->_positionToTargetSorted = false;
        $this->_targetCount++;

        return $this;
    }

    /**
     * Remove a target.
     * @param string $target
     * @chainable
     */
    public function removeTarget($target)
    {
        if (!isset($this->_targetToPositions[$target])) {
            throw new FlexihashException("Target '$target' does not exist.");
        }

        foreach ($this->_targetToPositions[$target] as $position) {
            unset($this->_positionToTarget[$position]);
        }

        unset($this->_targetToPositions[$target]);

        $this->_targetCount--;

        return $this;
    }

    /**
     * Looks up the target for the given resource.
     * @param string $resource
     * @return string
     */
    public function lookup($resource)
    {
        $targets = $this->lookupList($resource, 1);
        if (empty($targets)) {
            throw new FlexihashException('No targets exist');
        }
        return $targets[0];
    }

    /**
     * Get a list of targets for the resource, in order of precedence.
     * Up to $requestedCount targets are returned, less if there are fewer in total.
     *
     * @param string $resource
     * @param int $requestedCount The length of the list to return
     * @return array List of targets
     * @comment 查找当前的资源对应的节点,
     *          节点为空则返回空,节点只有一个则返回该节点,
     *          对当前资源进行hash,对所有的位置进行排序,在有序的位置列上寻找当前资源的位置
     *          当全部没有找到的时候,将资源的位置确定为有序位置的第一个(形成一个环)
     *          返回所找到的节点
     */
    public function lookupList($resource, $requestedCount)
    {
        if (!$requestedCount) {
            throw new FlexihashException('Invalid count requested');
        }

        // handle no targets
        if (empty($this->_positionToTarget)) {
            return array();
        }

        // optimize single target
        if ($this->_targetCount == 1) {
            return array_unique(array_values($this->_positionToTarget));
        }

        // hash resource to a position
        $resourcePosition = $this->_hasher->hash($resource);

        $results = array();
        $collect = false;

        $this->_sortPositionTargets();

        // search values above the resourcePosition
        foreach ($this->_positionToTarget as $key => $value) {
            // start collecting targets after passing resource position
            if (!$collect && $key > $resourcePosition) {
                $collect = true;
            }

            // only collect the first instance of any target
            if ($collect && !in_array($value, $results)) {
                $results [] = $value;
            }

            // return when enough results, or list exhausted
            if (count($results) == $requestedCount || count($results) == $this->_targetCount) {
                return $results;
            }
        }

        // loop to start - search values below the resourcePosition
        foreach ($this->_positionToTarget as $key => $value) {
            if (!in_array($value, $results)) {
                $results [] = $value;
            }

            // return when enough results, or list exhausted
            if (count($results) == $requestedCount || count($results) == $this->_targetCount) {
                return $results;
            }
        }

        // return results after iterating through both "parts"
        return $results;
    }

    /**
     * Sorts the internal mapping (positions to targets) by position
     */
    private function _sortPositionTargets()
    {
        // sort by key (position) if not already
        if (!$this->_positionToTargetSorted) {
            ksort($this->_positionToTarget, SORT_REGULAR);
            $this->_positionToTargetSorted = true;
        }
    }

    public function __toString()
    {
        return sprintf(
            '%s{targets:[%s]}',
            get_class($this),
            implode(',', $this->getAllTargets())
        );
    }

    // ----------------------------------------
    // private methods

    /**
     * A list of all potential targets
     * @return array
     */
    public function getAllTargets()
    {
        return array_keys($this->_targetToPositions);
    }

}