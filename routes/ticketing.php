<?php

use App\Http\Controllers\TicketingController;
use Illuminate\Support\Facades\Route;




Route::prefix($app_name)->group(function () {
    //Ticket Routes
    Route::get('/tickets', [TicketingController::class, 'showTicketForm'])->name('tickets');
});
