<?php

namespace Scrutinizer\Analyzer\Custom;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Model\Project;

abstract class AbstractRunner implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    abstract public function run(Project $project, array $commandData);
}