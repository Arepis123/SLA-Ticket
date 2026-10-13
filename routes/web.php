<?php

use App\Http\Controllers\TicketAttachmentController;
use App\Livewire\Admin;
use App\Livewire\Dashboard;
use App\Livewire\Manual;
use App\Livewire\Tickets;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('tickets', Tickets\Index::class)->name('tickets.index');
    Route::livewire('tickets/create', Tickets\Create::class)->name('tickets.create');
    Route::livewire('tickets/{ticket}', Tickets\Show::class)->name('tickets.show');
    Route::get('tickets/{ticket}/attachments/{attachment}', TicketAttachmentController::class)->name('tickets.attachments.show');

    Route::livewire('manual', Manual::class)->name('manual');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('companies', Admin\Companies::class)->name('companies');
        Route::livewire('projects', Admin\Projects::class)->name('projects');
        Route::livewire('users', Admin\Users::class)->name('users');
        Route::livewire('sla-policies', Admin\SlaPolicies::class)->name('sla-policies');
        Route::livewire('reports', Admin\Reports::class)->name('reports');
    });
});

require __DIR__.'/settings.php';
