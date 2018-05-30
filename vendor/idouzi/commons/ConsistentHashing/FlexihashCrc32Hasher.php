<?php

namespace Idouzi\Commons\ConsistentHashing;


class FlexihashCrc32Hasher implements FlexihashHasher
{

    /* (non-phpdoc)
    * @see Flexihash_Hasher::hash()
    */
    public function hash($string)
    {
        return crc32($string);
    }

}