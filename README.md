# Beanstalk Scheduler


## Install:

In composer.json add:

    {
        ...
        "repositories": [
            {
                "type": "vcs",
                "url": "git@gitlab.visual-craft.com:global/beanstalk-scheduler.git"
            },
            ...
        ],
        ...
    }

Then:

    $ composer require visual-craft/beanstalk-scheduler


## Use:

    $connection = new \Pheanstalk\Pheanstalk('127.0.0.1');
    
    
    // Add job
    $manager = new \VisualCraft\BeanstalkScheduler\Manager($connection, 'some_queue');
    $job = new \VisualCraft\BeanstalkScheduler\Job('some data');
    $manager->add($job);


    // Process job
    class SomeWorker implements \VisualCraft\BeanstalkScheduler\AbstractWorker
    {
        public function work(Job $job)
        {
            // do some work
            
            // reschedule failed job:
            // $this->reschedule();
        }
    }
    
    $scheduler = new \VisualCraft\BeanstalkScheduler\Scheduler($connection, 'some_queue');
    $scheduler->registerWorker(new SomeWorker());
    $scheduler->process();
