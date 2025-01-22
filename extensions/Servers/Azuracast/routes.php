<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\AzuraCast\AzuraCast;

Route::get('/azuracast/test', [AzuraCast::class, 'testConfig']);