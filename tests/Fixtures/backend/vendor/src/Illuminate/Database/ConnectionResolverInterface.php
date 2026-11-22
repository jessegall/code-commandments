<?php

namespace Illuminate\Database;

interface ConnectionResolverInterface
{
    /** @return \Illuminate\Database\ConnectionInterface */
    public function connection($name = NULL);
    /** @return string */
    public function getDefaultConnection();
    /** @return void */
    public function setDefaultConnection($name);
}
