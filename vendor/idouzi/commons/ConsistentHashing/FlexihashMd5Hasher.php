<?php

namespace Idouzi\Commons\ConsistentHashing;


class FlexihashMd5Hasher implements FlexihashHasher
{

    /* (non-phpdoc)
    * @see Flexihash_Hasher::hash()
    */
    public function hash($string)
    {
        return substr(md5($string), 0, 8);
    }

}