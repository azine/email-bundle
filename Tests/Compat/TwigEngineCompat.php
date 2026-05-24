<?php

namespace Azine\EmailBundle\Tests\Compat;

use Symfony\Component\HttpFoundation\Response;

class TwigEngineCompat
{
    public function renderResponse($view, array $parameters = array(), Response $response = null)
    {
        return new Response('');
    }
}
