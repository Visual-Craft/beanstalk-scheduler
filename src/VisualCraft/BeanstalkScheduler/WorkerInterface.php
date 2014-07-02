<?php

namespace VisualCraft\BeanstalkScheduler;

interface WorkerInterface
{
    /**
     * @param Job $job
     */
    public function work(Job $job);
}
