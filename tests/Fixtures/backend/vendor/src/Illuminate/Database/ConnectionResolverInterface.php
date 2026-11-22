<?php

namespace Illuminate\Database;

interface ConnectionResolverInterface
{
    public function connection($name = NULL);
    public function getDefaultConnection();
    public function setDefaultConnection($name);
}
