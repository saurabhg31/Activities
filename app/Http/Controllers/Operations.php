<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Operations extends Controller
{
    public function processActivity(String $type, Request $request){
        if($request->isMethod('GET')){
            return $this->sendResponse('Test', ['t1' => $type, 't2' => $request->all()], 205);
        }
    }
}
