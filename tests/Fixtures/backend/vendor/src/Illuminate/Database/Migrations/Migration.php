<?php

namespace Illuminate\Database\Migrations;

abstract class Migration
{
    protected $connection = NULL;
    public $withinTransaction = true;
    public function getConnection() {}
    public function shouldRun(): bool {}
}
