<?php

use App\Http\Controllers\TicketingController;
use Illuminate\Support\Facades\Route;




// Add these routes to your routes/web.php file

// Tester routes
Route::post('/tickets/{ticketId}/test', [TicketingController::class, 'submitTestResult'])
    ->name('tickets.test');

// Optional: Get tester dashboard/view
Route::get('/tickets/my-tests', [TicketingController::class, 'getMyTestTickets'])
    ->name('tickets.my-tests');
Route::prefix($app_name)->group(function () {
    //Ticket Routes
    Route::get('/tickets', [TicketingController::class, 'showTicketForm'])->name('tickets');
    Route::post('/tickets', [TicketingController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/datatable', [TicketingController::class, 'getTicketsDataTable'])->name('tickets.datatable');

    Route::get('/tickets/{ticket}', [TicketingController::class, 'viewTicket'])->name('tickets.view');

    // Workflow Actions - Assessment Phase
    Route::post('/{ticketId}/assess', [TicketingController::class, 'assessTicket'])->name('tickets.assess');
    Route::post('/{ticketId}/return', [TicketingController::class, 'returnTicket'])->name('tickets.return');

    // Workflow Actions - Approval Phase
    Route::post('/{ticketId}/approve/dh', [TicketingController::class, 'approveDH'])->name('tickets.approve.dh');
    Route::post('/{ticketId}/approve/od', [TicketingController::class, 'approveOD'])->name('tickets.approve.od');
    Route::post('/{ticketId}/reject/dh', [TicketingController::class, 'rejectDH'])->name('tickets.reject.dh');
    Route::post('/{ticketId}/reject/od', [TicketingController::class, 'rejectOD'])->name('tickets.reject.od');
    Route::post('/{ticketId}/assign', [TicketingController::class, 'assignTicket'])->name('tickets.assign');


    // Workflow Actions - Resolution Phase
    Route::post('/{ticketId}/resolve', [TicketingController::class, 'resolveTicket'])->name('tickets.resolve');
    Route::post('/{ticketId}/close', [TicketingController::class, 'closeTicket'])->name('tickets.close');

    // Additional Actions
    Route::post('/{ticketId}/hold', [TicketingController::class, 'putOnHold'])->name('tickets.hold');
    Route::post('/{ticketId}/resume', [TicketingController::class, 'resumeTicket'])->name('tickets.resume');

    // History and Remarks
    Route::get('/{ticketId}/history', [TicketingController::class, 'getTicketHistory'])->name('tickets.history');
    Route::get('/{ticketId}/remarks', [TicketingController::class, 'getTicketRemarks'])->name('tickets.remarks');
    Route::post('/{ticketId}/remarks', [TicketingController::class, 'addRemark'])->name('tickets.remarks.add');
});
