<?php

namespace VisualCraft\BeanstalkScheduler;

use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class AbstractBeanstalkManager
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $queueName;

    /**
     * @var Pheanstalk
     */
    protected $connection;

    /**
     * @var int
     */
    protected $ttr;

    /**
     * @param Pheanstalk $connection
     * @param string $queueName
     */
    public function __construct(Pheanstalk $connection, $queueName)
    {
        $this->connection = $connection;
        $this->queueName = $queueName;
        $this->logger = new NullLogger();
        $this->ttr = 3600;
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
     * @param int $value
     * @return $this
     */
    public function setTtr($value)
    {
        $this->ttr = (int) $value;

        return $this;
    }

    /**
     * @return int
     */
    public function getTtr()
    {
        return $this->ttr;
    }

    /**
     * @param Job $job
     * @param int $delay
     * @return int
     */
    public function putInTube(Job $job, $delay = 0)
    {
        return $this->connection->putInTube($this->queueName, serialize($job), Pheanstalk::DEFAULT_PRIORITY, $delay, $this->ttr);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    protected function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, array_replace(['queue' => $this->queueName], $context));
    }

    /**
     * @param \Exception $exception
     * @param array $context
     */
    protected function logException(\Exception $exception, array $context = [])
    {
        $buildExceptionMessage = function (\Exception $e) {
            return sprintf("class '%s', message '%s'", get_class($e), $e->getMessage());
        };
        $message = 'Exception occurred: ' . $buildExceptionMessage($exception);

        if (($previousException = $exception->getPrevious()) !== null) {
            $message .= ', previous exception: ' . $buildExceptionMessage($previousException);
        }

        $this->log('error', $message, array_replace(['queue' => $this->queueName], $context));
    }
}
