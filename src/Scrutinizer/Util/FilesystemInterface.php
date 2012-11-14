<?php

namespace Scrutinizer\Util;

/**
 * Filesystem Abstraction.
 * 
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface FilesystemInterface
{
    /**
     * @param string $content
     * 
     * @return File
     */
    function createTempFile($content = '');
    
    /**
     * Persists changes to the given file.
     * 
     * @param \Scrutinizer\Util\File $file
     * 
     * @return void
     */
    function write(File $file);
    
    /**
     * @param string $oldName
     * @param string $newName
     * 
     * @return void
     */
    function rename($oldName, $newName);
    
    /**
     * Deletes the given file.
     * 
     * @param \Scrutinizer\Util\File $file
     * 
     * @return void
     */
    function delete(File $file);
}
