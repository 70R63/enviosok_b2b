<?php

namespace App\Traits;

use Log;

trait GettersSetters
{

	public function getResponse(){
        return $this->response;
    }

    public function getNotices(){
        return $this->notices;
    }

}