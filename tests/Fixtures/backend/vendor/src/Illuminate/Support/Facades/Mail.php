<?php

namespace Illuminate\Support\Facades;

class Mail extends \Illuminate\Support\Facades\Facade
{
    /** @return \Illuminate\Support\Testing\Fakes\MailFake */
    public static function fake() {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
