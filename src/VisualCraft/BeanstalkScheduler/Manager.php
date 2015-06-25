<?php

namespace VisualCraft\BeanstalkScheduler;

class Manager extends AbstractBeanstalkManager
{
    /**
     * @param Job $job
     */
    public function submit(Job $job)
    {
        $context = ['job-id' => $job->getId()];
        $this->log('info', 'Received new job', $context);
        $id = $this->putInTube($job);
        $context['beanstalk-id'] = $id;
        $this->log('info', 'Job successfully added to queue', $context);
    }
}
