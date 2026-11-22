<?php

namespace Illuminate\Contracts\Support;

interface Responsable
{
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function toResponse($request);
}
