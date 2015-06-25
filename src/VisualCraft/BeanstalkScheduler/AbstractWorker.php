<?php

namespace VisualCraft\BeanstalkScheduler;

abstract class AbstractWorker implements WorkerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isReschedulableException(\Exception $exception)
    {
        return false;
    }
}
