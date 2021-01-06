<?php

namespace Pingo\Database\QueryBuilder;

class MySQL extends SQL
{
    protected $identifierQuotes=array("`","`");//For table name and column name
}