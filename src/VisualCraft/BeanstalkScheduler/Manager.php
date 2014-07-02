<?php

namespace VisualCraft\BeanstalkScheduler;

use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class Manager
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var Pheanstalk
     */
    private $connection;

    /**
     * @param Pheanstalk $connection
     * @param string $queueName
     */
    public function __construct(Pheanstalk $connection, $queueName)
    {
        $this->connection = $connection;
        $this->queueName = $queueName;
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
     * @param Job $job
     */
    public function submit(Job $job)
    {
        $this->logger->log('info', "Submitted new job #{$job->getId()}");
        $id = $this->connection->putInTube($this->queueName, serialize($job), Pheanstalk::DEFAULT_PRIORITY, 0, 3600);
        $this->logger->log('info', "Job #{$job->getId()} with beanstalk id #{$id} successfully added to queue");
    }
}
