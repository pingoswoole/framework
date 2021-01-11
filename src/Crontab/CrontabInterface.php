<?php
namespace Pingo\Crontab;

interface  CrontabInterface
{
    /* protected $min = '*';
    protected $hour = '*';
    protected $day = '*';
    protected $month = '*';
    protected $week = '*'; */
    
     
    public  function getMin():string;
    public  function getHour():string;
    public  function getDay():string;
    public  function getMonth():string;
    public  function getWeek():string;
    
    public  function run();

}
