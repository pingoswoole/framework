<?php

namespace Pingo\Database\QueryBuilder;

class PostgreSQL extends SQL
{
    protected $identifierQuotes=array('"','"');//For table name and column name
}