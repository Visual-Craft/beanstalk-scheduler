# Beanstalk Scheduler
Background jobs scheduling using Beanstalk


## Install:

    $ composer require visual-craft/beanstalk-scheduler


## Use:

    // Create Beanstalkd connection
    $connection = new \Pheanstalk\Pheanstalk('127.0.0.1');
    
    
    // Add job
    $manager = new \VisualCraft\BeanstalkScheduler\Manager($connection, 'some_queue');
    $job = new \VisualCraft\BeanstalkScheduler\Job('some data');
    $manager->add($job);


    // Define worker
    class SomeWorker implements \VisualCraft\BeanstalkScheduler\WorkerInterface
    {
        public function work(Job $job)
        {
            // do some work
            // $job->getPayload() returns 'some data'
            
            // you can reschedule failed job:
            // throw new \VisualCraft\BeanstalkScheduler\Exception\RescheduleJobException();
        }

        public function isReschedulableException(\Exception $exception)
        {
            // Another way of rescheduling failed job
            // Put some logic here
            return true;
        }

        public function fail(Job $job)
        {
            // Optionally you can do something with failed job
        }
    }


    // Process job
    $scheduler = new \VisualCraft\BeanstalkScheduler\Scheduler($connection, 'some_queue');
    $scheduler
        ->setWorker(new SomeWorker())
        // Define rescheduling configuration:
        ->setReschedule([20, 30])
    ;
    $scheduler->process();
