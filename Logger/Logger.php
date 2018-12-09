<?php
/**
 * @copyright Copyright (c) 2018 Saul Morales Paccheco www.saulmoralespa.com
 *
 */

namespace Smp\Neogateway\Logger;

class Logger extends \Monolog\Logger
{
    /**
     * Set logger name
     * @param $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}