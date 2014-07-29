<?php

namespace VisualCraft\BeanstalkScheduler\Exception;

abstract class AbstractJobException extends \Exception
{
    public function __construct(\Exception $previous = null, $message = "", $code = 0)
    {
        parent::__construct($message, $code, $previous);
    }
}
