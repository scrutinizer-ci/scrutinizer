<?php

namespace Scrutinizer\Logger;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class LoggableProcess extends Process
{
    /** @var LoggerInterface */
    private $logger;

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function run($callable = null)
    {
        return parent::run(
            function ($type, $data) use ($callable) {
                if (null !== $callable) {
                    call_user_func($callable, $type, $data);
                }

                if (null === $this->logger) {
                    return;
                }

                $this->logger->info($data, array(
                    'type'   => $type,
                    'cmd'    => $this->getCommandLine(),
                    'procid' => spl_object_hash($this),
                ));
            }
        );
    }
}