Beanstalk Scheduler
===================

Background jobs scheduling using Beanstalk


Install
-------

    $ composer require visual-craft/beanstalk-scheduler


Usage
-----

    // Create Beanstalkd connection
    $connection = new \Pheanstalk\Pheanstalk('127.0.0.1');
    
    
    // Add job
    $manager = new \VisualCraft\BeanstalkScheduler\Manager($connection, 'some_queue');
    $job = new \VisualCraft\BeanstalkScheduler\Job('some data');
    $manager->submit($job);
    // or
    //$manager->submit($job, 60); // with the delay of 60 seconds


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

License
-------

This code is released under the MIT license. See the complete license in the file: `LICENSE`
