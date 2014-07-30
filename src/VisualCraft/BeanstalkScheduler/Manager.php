<?php

namespace VisualCraft\BeanstalkScheduler;

class Manager extends AbstractBeanstalkManager
{
    /**
     * @param Job $job
     */
    public function submit(Job $job)
    {
        $this->log('info', "Received new job #{$job->getId()}");
        $id = $this->putInTube($job);
        $this->log('info', "Job #{$job->getId()} with beanstalk id #{$id} successfully added to queue");
    }
}
