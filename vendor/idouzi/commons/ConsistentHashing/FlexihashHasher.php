<?php

namespace Idouzi\Commons\ConsistentHashing;


interface FlexihashHasher
{

    /**
     * Hashes the given string into a 32bit address space.
     *
     * Note that the output may be more than 32bits of raw data, for example
     * hexidecimal characters representing a 32bit value.
     *
     * The data must have 0xFFFFFFFF possible values, and be sortable by
     * PHP sort functions using SORT_REGULAR.
     *
     * @param string
     * @return mixed A sortable format with 0xFFFFFFFF possible values
     */
    public function hash($string);

}