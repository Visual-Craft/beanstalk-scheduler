<?php

namespace VisualCraft\BeanstalkScheduler;

use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use VisualCraft\BeanstalkScheduler\Exceptions\RescheduleJobException;

class Scheduler
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var WorkerInterface
     */
    private $worker;

    /**
     * @var Pheanstalk
     */
    private $connection;

    /**
     * @var array
     */
    private $reschedule;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $maxJobs;

    /**
     * @param $queueName
     * @param Pheanstalk $connection
     */
    public function __construct(Pheanstalk $connection, $queueName)
    {
        $this->connection = $connection;
        $this->queueName = $queueName;
        $this->timeout = 0;
        $this->maxJobs = 0;
        $this->logger = new NullLogger();
    }

    /**
     * @return Pheanstalk
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return string
     */
    public function getQueueName()
    {
        return $this->queueName;
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setReschedule(array $value)
    {
        $this->reschedule = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getReschedule()
    {
        return $this->reschedule;
    }

    /**
     * @param int $value
     */
    public function setTimeout($value)
    {
        $this->timeout = (int) $value;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $value
     */
    public function setMaxJobs($value)
    {
        $this->maxJobs = (int) $value;
    }

    /**
     * @return int
     */
    public function getMaxJobs()
    {
        return $this->maxJobs;
    }

    /**
     *
     */
    public function process()
    {
        $scheduleStartTime = time();
        $processedJobs = 0;
        $useTimeout = $this->timeout > 0;
        $useJobsLimit = $this->maxJobs > 0;
        $this->log('info', 'Start processing of work queue');
        $reserveTimeout = 120;

        while (true) {
            if ($useJobsLimit && $processedJobs > $this->maxJobs) {
                $this->log('info', 'Max jobs limit processing reached, stopping');
                break;
            }

            if ($useTimeout) {
                $timeSpent = time() - $scheduleStartTime;
                $timeLeft = $this->timeout - $timeSpent;

                if ($timeLeft <= 0) {
                    $this->log('info', 'Max time limit of processing reached, stopping');
                    break;
                }

                $reserveTimeout = $timeLeft;
            }

            $pheanstalkJob = $this->connection->watch($this->queueName)->reserve($reserveTimeout);

            if (!$pheanstalkJob) {
                continue;
            }

            $job = unserialize($pheanstalkJob->getData());

            if (!$job instanceof Job) {
                $this->log('info', 'Received invalid job, skipping');
                continue;
            }

            $processedJobs++;
            $job->nextAttempt();
            $this->log('info', "Processing job #{$job->getId()}({$pheanstalkJob->getId()}), {$job->getAttemptsCount()} attempt");

            try {
                $this->worker->work($job);
                $this->log('info', "Job #{$job->getId()}({$pheanstalkJob->getId()}) performed successfully");
            } catch (\Exception $exception) {
                $this->handleException($exception, $job);
            } finally {
                $this->connection->delete($pheanstalkJob);
            }
        }
    }

    /**
     * @param WorkerInterface $worker
     */
    public function registerWorker(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @param \Exception $exception
     * @param Job $job
     */
    private function handleException(\Exception $exception, Job $job)
    {
        $this->log('error', sprintf("Exception occurred: class '%s', message %s", get_class($exception), $exception->getMessage()));

        if (empty($this->reschedule)) {
            $this->log('info', "Rescheduling not required as not defined in configuration.");

            return;
        }

        if (!$exception instanceof RescheduleJobException) {
            $this->log('info', "Permanent error.");

            return;
        }

        if ($job->getAttemptsCount() > count($this->reschedule)) {
            $this->log('info', "Exceeded the number of attempts.");

            return;
        }

        $id = $this->connection->putInTube(
            $this->queueName,
            serialize($job),
            Pheanstalk::DEFAULT_PRIORITY,
            $this->reschedule[$job->getAttemptsCount() - 1],
            3600
        );

        $this->log('info', "Rescheduling job #{$job->getId()}, new beanstalk id #{$id}");
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    private function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, array_replace(['queue' => $this->queueName], $context));
    }
}
