<?php

namespace Smp\Neogateway\Logger\Handler;

use Monolog\Logger;

class System extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/neogateway.log';
}