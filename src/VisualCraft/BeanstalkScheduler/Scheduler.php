<?php

namespace VisualCraft\BeanstalkScheduler;

use Pheanstalk\Pheanstalk;
use Pheanstalk\Job as PheanstalkJob;
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
    private $rescedule;

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
     * @param array $reschedule
     */
    public function __construct(Pheanstalk $connection, $queueName, array $reschedule = [])
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
     * @return array
     */
    public function getRescheduleParameters()
    {
        return $this->rescedule;
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
        $this->logger->log('info', 'Start processing of work queue');

        while (true) {
            if ($useJobsLimit && $processedJobs > $this->maxJobs) {
                $this->logger->log('info', 'Max jobs limit processing reached, stopping');
                break;
            }

            if ($useTimeout) {
                $timeSpent = time() - $scheduleStartTime;
                $timeLeft = $this->timeout - $timeSpent;

                if ($timeLeft <= 0) {
                    $this->logger->log('info', 'Max time limit of processing reached, stopping');
                    break;
                }

                $reserveTimeout = $timeLeft;
            } else {
                $reserveTimeout = 0;
            }

            $pheanstalkJob = $this->connection->watch($this->queueName)->reserve($reserveTimeout);

            if (!$pheanstalkJob instanceof PheanstalkJob) {
                $this->logger->log('info', 'Received invalid job, skipping');
                continue;
            }

            $job = unserialize($pheanstalkJob->getData());

            if (!$job instanceof Job) {
                $this->logger->log('info', 'Received invalid job, skipping');
                continue;
            }

            $processedJobs++;
            $job->nextAttempt();
            $this->logger->log('info', "Processing job #{$job->getId()} with beanstalk id #{$pheanstalkJob->getId()}, {$job->getAttemptsCount()} attempt");

            try {
                $this->worker->work($job);
                $this->logger->log('info', "Job #{$job->getId()} with beanstalk id #{$pheanstalkJob->getId()} performed successfully");
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
        $this->logger->log('info', "Error occurred: {$exception->getMessage()}");

        if (empty($this->rescedule)) {
            $this->logger->log('error', "Rescheduling not required as not defined in configuration.");

            return;
        }

        if (!$exception instanceof RescheduleJobException) {
            $this->logger->log('info', "Permanent error.");

            return;
        }

        if ($job->getAttemptsCount() > count($this->rescedule)) {
            $this->logger->log('info', "Exceeded the number of attempts.");

            return;
        }

        $id = $this->connection->putInTube(
            $this->queueName,
            serialize($job),
            Pheanstalk::DEFAULT_PRIORITY,
            $this->rescedule[$job->getAttemptsCount() - 1],
            3600
        );

        $this->logger->log('info', "Rescheduling job #{$job->getId()}, new beanstalk id #{$id}");
    }
}
