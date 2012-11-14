<?php

namespace Scrutinizer\Util;

class LocalFilesystem implements FilesystemInterface
{
    public function createTempFile($content = '')
    {
        $tempnam = tempnam(sys_get_temp_dir(), 'scrutinizer');
        if ( ! empty($content)) {
            file_put_contents($tempnam, $content);
        }
        
        return new File($this, $tempnam, $content);
    }
    
    public function write(File $file)
    {
        file_put_contents($file->getName(), $file->getContent());
    }
    
    public function delete(File $file)
    {
        unlink($file->getName());
    }
    
    public function rename($oldName, $newName)
    {
        rename($oldName, $newName);
    }
}
