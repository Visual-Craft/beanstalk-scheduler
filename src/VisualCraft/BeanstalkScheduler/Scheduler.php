<?php

namespace VisualCraft\BeanstalkScheduler;

use Pheanstalk\Pheanstalk;
use VisualCraft\BeanstalkScheduler\Exception\RescheduleJobException;

class Scheduler extends AbstractBeanstalkManager
{
    /**
     * @var WorkerInterface
     */
    private $worker;

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
     * {@inheritDoc}
     */
    public function __construct(Pheanstalk $connection, $queueName)
    {
        parent::__construct($connection, $queueName);
        $this->timeout = 0;
        $this->maxJobs = 0;
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
     * @return $this
     */
    public function setTimeout($value)
    {
        $this->timeout = (int) $value;

        return $this;
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
     * @return $this
     */
    public function setMaxJobs($value)
    {
        $this->maxJobs = (int) $value;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxJobs()
    {
        return $this->maxJobs;
    }

    /**
     * @param WorkerInterface $worker
     * @return $this
     */
    public function setWorker(WorkerInterface $worker)
    {
        $this->worker = $worker;

        return $this;
    }

    /**
     * @return WorkerInterface
     */
    public function getWorker()
    {
        return $this->worker;
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
                $this->log('info', 'Received invalid job, skipping', [
                    'beanstalk-id' => $pheanstalkJob->getId(),
                ]);
                continue;
            }

            $processedJobs++;
            $job->nextAttempt();
            $loggingContext = [
                'job-id' => $job->getId(),
                'beanstalk-id' => $pheanstalkJob->getId(),
            ];
            $this->log('info', "Processing job, attempt #{$job->getAttemptsCount()}", $loggingContext);

            try {
                $this->worker->work($job);
                $this->log('info', 'Job performed successfully', $loggingContext);
            } catch (\Exception $exception) {
                $this->handleException($exception, $job, $loggingContext);
            } finally {
                $this->connection->delete($pheanstalkJob);
            }
        }
    }

    /**
     * @param \Exception $exception
     * @param Job $job
     * @param array $loggingContext
     */
    private function handleException(\Exception $exception, Job $job, array $loggingContext)
    {
        $this->logException($exception, $loggingContext);

        if (empty($this->reschedule)) {
            $this->log('info', 'Rescheduling is not required.', $loggingContext);

            return;
        }

        if (!$exception instanceof RescheduleJobException) {
            $this->log('info', 'Rescheduling isn\'t performed as error is permanent.', $loggingContext);

            return;
        }

        if ($job->getAttemptsCount() > count($this->reschedule)) {
            $this->log('info', 'Rescheduling isn\'t performed as the number of attempts is exceeded.', $loggingContext);

            return;
        }

        $id = $this->putInTube($job, $this->reschedule[$job->getAttemptsCount() - 1]);
        $this->log('info', "Rescheduling job.", array_replace($loggingContext, [
            'new-beanstalk-id' => $id,
        ]));
    }
}
