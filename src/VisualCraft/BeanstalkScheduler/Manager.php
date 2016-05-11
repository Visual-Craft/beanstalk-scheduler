<?php

namespace VisualCraft\BeanstalkScheduler;

use Pheanstalk\Exception\ServerException;

class Manager extends AbstractBeanstalkManager
{
    /**
     * @param Job $job
     * @throws \Exception
     */
    public function submit(Job $job)
    {
        $context = ['job-id' => $job->getId()];
        $this->log('info', 'Received new job', $context);

        try {
            $id = $this->putInTube($job);
        } catch (\Exception $e) {
            $this->log('error', 'Unable to add job to queue.', $context);
            $this->logException($e, $context);

            throw $e;
        }

        $context['beanstalk-id'] = $id;
        $this->log('info', 'Job successfully added to queue', $context);
    }

    /**
     *
     */
    public function clearQueue()
    {
        $getJob = function ($method) {
            try {
                return $this->connection->{$method}($this->queueName);
            } catch (ServerException $e) {
                if (strpos($e->getMessage(), 'NOT_FOUND:') === 0) {
                    return null;
                }

                throw $e;
            }
        };

        foreach (['peekReady', 'peekDelayed', 'peekBuried'] as $method) {
            while ($job = $getJob($method)) {
                $this->connection->delete($job);
            }
        }
    }
}
