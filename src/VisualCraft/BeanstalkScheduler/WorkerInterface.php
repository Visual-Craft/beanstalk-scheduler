<?php

namespace VisualCraft\BeanstalkScheduler;

interface WorkerInterface
{
    /**
     * @param Job $job
     */
    public function work(Job $job);

    /**
     * @param \Exception $exception
     * @return bool
     */
    public function isReschedulableException(\Exception $exception);
}
