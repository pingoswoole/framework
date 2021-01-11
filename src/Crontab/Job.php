<?php
namespace Pingo\Crontab;

use Iterator;
use Pingo\Crontab\Scheduled\Task;

class Job implements Iterator
{
    private $position = 0;
    private $jobs = [];

    public function addJob(Task $task)
    {
        $this->jobs[] = $task;
    }

    public function current()
    {
        return $this->jobs[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset($this->jobs[$this->position]);
    }
}
