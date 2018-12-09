<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 8/08/18
 * Time: 10:17 AM
 */

namespace Smp\Neogateway\Logger;


class Handler extends \Magento\Framework\Logger\Handler\Base
{
    protected $fileName = '/var/log/smp/neogateway/info.log';
    protected $loggerType = \Monolog\Logger::INFO;
}