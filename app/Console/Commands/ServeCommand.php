<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'serve')]
class ServeCommand extends \Illuminate\Foundation\Console\ServeCommand
{
    protected function serverCommand()
    {
        $command = parent::serverCommand();

        // PHP must accept the multipart request before Laravel can enforce the
        // 5 MB image/document rules and retain existing 50 MB product videos.
        return [$command[0], '-d', 'upload_max_filesize=64M', '-d', 'post_max_size=160M',
            '-d', 'max_file_uploads=50', ...array_slice($command, 1)];
    }
}
