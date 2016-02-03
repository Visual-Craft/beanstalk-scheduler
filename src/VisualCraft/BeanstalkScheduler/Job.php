<?php

namespace VisualCraft\BeanstalkScheduler;

class Job
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var mixed
     */
    private $payload;

    /**
     * @var int
     */
    private $attemptsCount;

    /**
     * @param mixed $payload
     */
    public function __construct($payload)
    {
        $this->id = $this->generateUniqId();
        $this->payload = $payload;
        $this->attemptsCount = 0;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setAttemptsCount($value)
    {
        $this->attemptsCount = $value;

        return $this;
    }

    /**
     * @return int
     */
    public function getAttemptsCount()
    {
        return $this->attemptsCount;
    }

    /**
     * @return int
     */
    public function nextAttempt()
    {
        return ++$this->attemptsCount;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function setPayload($value)
    {
        $this->payload = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return string
     */
    private function generateUniqId()
    {
        return str_replace('.', '', uniqid('', true));
    }
}
