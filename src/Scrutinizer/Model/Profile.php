<?php

namespace Scrutinizer\Model;

use Scrutinizer\Analyzer\AnalyzerInterface;

class Profile
{
    private $checkpoints = array();

    public function getCheckPoints()
    {
        return $this->checkpoints;
    }

    public function start()
    {
        $this->check('start');
    }

    public function beforeAnalysis(AnalyzerInterface $analyzer)
    {
        $this->check('pass.'.$analyzer->getName().'.start');
    }

    public function afterAnalysis(AnalyzerInterface $analyzer)
    {
        $this->check('pass.'.$analyzer->getName().'.end');
    }

    public function check($label)
    {
        $this->checkpoints[$label] = microtime(true);
    }

    public function stop()
    {
        $this->check('stop');
    }
}