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

    /**
     * @param bool $ignoreErrors
     * @throws \Exception
     */
    public function clearQueue($ignoreErrors = false)
    {
        $methods = [
            'peekReady',
            'peekDelayed',
            'peekBuried',
        ];

        try {
            foreach ($methods as $method) {
                while ($job = $this->connection->{$method}($this->queueName)) {
                    if ($ignoreErrors) {
                        try {
                            $this->connection->delete($job);
                        } catch (\Exception $e) {}
                    } else {
                        $this->connection->delete($job);
                    }
                }
            }
        } catch (\Exception $e) {
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }
}
