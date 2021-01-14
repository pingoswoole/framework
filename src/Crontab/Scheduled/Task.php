<?php
namespace Pingo\Crontab\Scheduled;

define('CRONTAB_FIRST_MIN', 0);
define('CRONTAB_LAST_MIN', 59);
define('CRONTAB_FIRST_HOUR', 0);
define('CRONTAB_LAST_HOUR', 23);
define('CRONTAB_FIRST_DAY', 1);
define('CRONTAB_LAST_DAY', 31);
define('CRONTAB_FIRST_MONTH', 1);
define('CRONTAB_LAST_MONTH', 12);
define('CRONTAB_FIRST_WEEK', 0);
define('CRONTAB_LAST_WEEK', 6);
 
use Pingo\Crontab\CrontabInterface;

class Task
{
    private $taskObj;

    private $min;

    private $hour;

    private $day;

    private $month;

    private $week;

    private $command;

    private $process;

    private $runTime;

    /**
     * @var string $taskObj example: 10 * * * * php example.php
     */
    public function __construct(CrontabInterface $taskObj)
    {
        $this->taskObj = $taskObj;
        $this->runTime = time();
        $this->initialize();
    }

    /**
     * 初始化任务配置
     */
    private function initialize()
    {
        
        $this->min = $this->format($this->taskObj->getMin(), 'min');
        $this->hour= $this->format($this->taskObj->getHour(), 'hour');
        $this->day = $this->format($this->taskObj->getDay(), 'day');
        $this->month = $this->format($this->taskObj->getMonth(), 'month');
        $this->week= $this->format($this->taskObj->getWeek(), 'week');
        //$this->command = array_slice($rule, 5);
    }

    private function format($value, $field)
    {
        if ($value === '*') {
            return $value;
        }
        if (is_numeric($value)) {
            return [$this->checkFieldRule($value, $field)];
        }
        $steps = explode(',', $value);
        $scope = [];
        foreach ($steps as $step) {
            if (strpos($step, '-') !== false) {
                $range = explode('-', $step);
                $scope = array_merge($scope, range(
                    $this->checkFieldRule($range[0], $field),
                    $this->checkFieldRule($range[1], $field)
                ));
                continue;
            }
            if (strpos($step, '/') !== false) {
                $inter = explode('/', $step);
                $confirmInter = isset($inter[1]) ? $inter[1] : $inter[0];
                if ($confirmInter === '/') {
                    $confirmInter = 1; 
                }
                $scope = array_merge($scope, range(
                    constant('CRONTAB_FIRST_' . strtoupper($field)),
                    constant('CRONTAB_LAST_' . strtoupper($field)),
                    $confirmInter
                ));
                continue;
            }
            $scope[] = $step;
        }
        return $scope;
    }

    private function checkFieldRule($value, $field)
    {
        $first = constant('CRONTAB_FIRST_' . strtoupper($field));
        $last  = constant('CRONTAB_LAST_' . strtoupper($field));
        if ($value < $first) {
            return $first;
        }
        if ($value > $last) {
            return $last;
        }
        return (int) $value;
    }

    public function getTimeAttribute($attribute)
    {
        if (!in_array($attribute, ['min', 'hour', 'day', 'month', 'week', 'runTime'])) return null;
        return $this->{$attribute} ?? null;
    }

    public function setRunTime($time)
    {
        $this->runTime = $time;
    }

    public function run()
    {
        //添加任务
        add_task('crontab', ['class' => get_class($this->taskObj)]);

    }
}