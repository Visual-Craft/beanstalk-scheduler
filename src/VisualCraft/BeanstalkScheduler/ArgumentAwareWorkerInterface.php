<?php

namespace VisualCraft\BeanstalkScheduler;

interface ArgumentAwareWorkerInterface extends WorkerInterface
{
    /**
     * @param Job $job
     * @param mixed $arg
     * @return
     */
    public function work(Job $job, $arg = null);
}
