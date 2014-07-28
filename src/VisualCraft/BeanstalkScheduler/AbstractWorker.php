<?php

namespace VisualCraft\BeanstalkScheduler;

use VisualCraft\BeanstalkScheduler\Exception\PermanentJobException;
use VisualCraft\BeanstalkScheduler\Exception\RescheduleJobException;

abstract class AbstractWorker implements WorkerInterface
{
    /**
     * @param string $message
     * @throws RescheduleJobException
     */
    protected function reschedule($message = '')
    {
        throw new RescheduleJobException($message);
    }

    /**
     * @param string $message
     * @throws PermanentJobException
     */
    protected function fail($message = '')
    {
        throw new PermanentJobException($message);
    }
}
