<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\Azuracast\Azuracast;

Route::get('/azuracast/test', [Azuracast::class, 'testConfig']);