<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Util\FilesystemInterface;

interface FilesystemAwareInterface
{
    /**
     * @param \Scrutinizer\Util\FilesystemInterface $fs
     * 
     * @return void
     */
    function setFilesystem(FilesystemInterface $fs);
}
