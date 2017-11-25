<?php

include(__DIR__ . '/webdata/init.inc.php');

Pix_Controller::addCommonHelpers();
class MyDispatcher extends Pix_Controller_Dispatcher
{
    public function dispatch($path)
    {
        if (preg_match('#^/v0/collections/#', $path)) {
            return array('collections', 'index');
        }
        return null;
    }
}

Pix_Controller::addDispatcher(new MyDispatcher);
Pix_Controller::dispatch(__DIR__ . '/webdata/');
