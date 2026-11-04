<?php

use App\Http\Controllers\ActivityFeedController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminEquipmentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PartCategoryController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StockDashboardController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\Ticket\TicketAssignmentController;
use App\Http\Controllers\Ticket\TicketCloseController;
use App\Http\Controllers\Ticket\TicketLifecycleController;
use App\Http\Controllers\Ticket\TicketScheduleController;
use App\Http\Controllers\Ticket\TicketStartController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketBudgetController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes (Open Access)
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('custom.auth')->name('api.user');

Route::post('/login', [AuthController::class, 'login'])
    ->name('api.login')
    ->middleware(['rate.limit:5,1']);

Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])
    ->name('api.password.email')
    ->middleware(['rate.limit:3,1']);

Route::get('/password/reset/{token}', [PageController::class, 'passwordResetForm'])
    ->name('api.password.reset.form');

Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])
    ->name('api.password.reset')
    ->middleware(['rate.limit:5,1']);

/*
|--------------------------------------------------------------------------
| Protected API Routes (Authenticated via X-Auth-Token)
|--------------------------------------------------------------------------
*/
Route::middleware(['custom.auth'])->group(function () {

    // Activity feed (authenticated only — exposes internal entity names and staff users)
    Route::get('/activities', [ActivityFeedController::class, 'index'])->name('api.activities');

    // Authentication
    Route::post('/password/change', [ProfileController::class, 'changePassword'])
        ->name('api.password.change')
        ->middleware('rate.limit:10,1');

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index'])->name('api.tickets.index');
    Route::get('/tickets/search', [TicketController::class, 'search'])->name('api.tickets.search');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->name('api.tickets.store')
        ->middleware('rate.limit:30,1');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('api.tickets.show');

    // Comments
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])
        ->name('api.tickets.comments.store')
        ->middleware('rate.limit:30,1');
    Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('api.tickets.comments.index');

    // Photos
    Route::post('/tickets/{ticket}/photos', [TicketAttachmentController::class, 'store'])
        ->name('api.tickets.photos.store')
        ->middleware('rate.limit:30,1');
    Route::get('/tickets/{ticket}/photos', [TicketAttachmentController::class, 'index'])->name('api.tickets.photos.index');
    Route::delete('/tickets/{ticket}/photos/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('api.tickets.photos.destroy');

    // Workflow
    Route::post('/tickets/{ticket}/reopen', [TicketLifecycleController::class, 'reopen'])->name('api.tickets.reopen');
    Route::post('/tickets/{ticket}/cancel', [TicketLifecycleController::class, 'cancel'])->name('api.tickets.cancel');
    Route::post('/tickets/{ticket}/schedule', TicketScheduleController::class)->name('api.tickets.schedule');

    // Technician
    Route::middleware(['role:technician'])->group(function () {
        Route::put('/technician/tickets/{ticket}/start', TicketStartController::class)
            ->name('api.technician.tickets.start')
            ->middleware('rate.limit:20,1');
        Route::put('/technician/tickets/{ticket}/close', [TicketCloseController::class, 'simpleClose'])
            ->name('api.technician.tickets.close')
            ->middleware('rate.limit:20,1');
        Route::put('/technician/tickets/{ticket}/close-final', [TicketCloseController::class, 'closeFinal'])
            ->name('api.technician.tickets.close-final')
            ->middleware('rate.limit:20,1');
        Route::put('/technician/tickets/{ticket}/request-budget', [TicketBudgetController::class, 'requestAuthorization'])
            ->name('api.technician.tickets.request-budget')
            ->middleware('rate.limit:20,1');
    });

    // Stock: read and movements (admins and technicians)
    Route::middleware(['role:admin,technician'])->group(function () {
        Route::get('/stock/parts', [PartController::class, 'index'])->name('api.stock.parts.index');
        Route::get('/stock/parts/{part}', [PartController::class, 'show'])->name('api.stock.parts.show');
        Route::get('/stock/suppliers', [SupplierController::class, 'index'])->name('api.stock.suppliers.index');
        Route::get('/stock/suppliers/{supplier}', [SupplierController::class, 'show'])->name('api.stock.suppliers.show');
        Route::get('/stock/movements', [StockMovementController::class, 'index'])->name('api.stock.movements.index');
        Route::post('/stock/movements', [StockMovementController::class, 'store'])->name('api.stock.movements.store');
        Route::get('/stock/dashboard/summary', [StockDashboardController::class, 'summary'])->name('api.stock.dashboard.summary');
        Route::get('/stock/dashboard/top-consumed', [StockDashboardController::class, 'topConsumed'])->name('api.stock.dashboard.top-consumed');
        Route::get('/stock/dashboard/slow-moving', [StockDashboardController::class, 'slowMoving'])->name('api.stock.dashboard.slow-moving');
        Route::get('/stock/dashboard/runout-forecast', [StockDashboardController::class, 'runoutForecast'])->name('api.stock.dashboard.runout-forecast');
        Route::get('/stock/dashboard/cost-by-equipment', [StockDashboardController::class, 'costByEquipment'])->name('api.stock.dashboard.cost-by-equipment');
        Route::get('/stock/dashboard/cost-by-ticket', [StockDashboardController::class, 'costByTicket'])->name('api.stock.dashboard.cost-by-ticket');
    });

    // Admin
    Route::middleware(['role:admin'])->group(function () {
        // Users
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('api.admin.users.index');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('api.admin.users.store');
        Route::patch('/admin/users/{targetUser}', [AdminUserController::class, 'update'])->name('api.admin.users.update');
        Route::patch('/admin/users/{targetUser}/inactive', [AdminUserController::class, 'inactivate'])->name('api.admin.users.inactivate');
        Route::delete('/admin/users/{targetUser}', [AdminUserController::class, 'destroy'])->name('api.admin.users.destroy');
        Route::get('/admin/profiles', [AdminUserController::class, 'profiles'])->name('api.admin.profiles.index');

        // Audit
        Route::get('/admin/audits', [AuditController::class, 'index'])->name('api.admin.audits.index');

        // Equipment
        Route::get('/admin/equipment', [AdminEquipmentController::class, 'index'])->name('api.admin.equipment.index');
        Route::post('/admin/equipment', [AdminEquipmentController::class, 'store'])->name('api.admin.equipment.store');
        Route::patch('/admin/equipment/{equipment}', [AdminEquipmentController::class, 'update'])->name('api.admin.equipment.update');
        Route::delete('/admin/equipment/{equipment}', [AdminEquipmentController::class, 'destroy'])->name('api.admin.equipment.destroy');

        // Rooms
        Route::get('/admin/rooms', [RoomController::class, 'indexRoom'])->name('api.admin.rooms.index');
        Route::post('/admin/rooms', [RoomController::class, 'storeRoom'])->name('api.admin.rooms.store');
        Route::patch('/admin/rooms/{room}', [RoomController::class, 'updateRoom'])->name('api.admin.rooms.update');
        Route::patch('/admin/rooms/{room}/inactive', [RoomController::class, 'inactivateRoom'])->name('api.admin.rooms.inactivate');

        // Budget and Preventive Maintenance
        Route::post('/admin/preventive', [AdminController::class, 'storePreventive'])->name('api.admin.preventive.store');
        Route::patch('/admin/tickets/{ticket}/approve-budget', [AdminController::class, 'approveBudget'])->name('api.admin.tickets.approve-budget');
        Route::patch('/admin/tickets/{ticket}/assign', TicketAssignmentController::class)
            ->name('api.admin.tickets.assign')
            ->middleware('rate.limit:20,1');

        // Stock: management (admin)
        Route::post('/admin/parts', [PartController::class, 'store'])->name('api.admin.stock.parts.store');
        Route::patch('/admin/parts/{part}', [PartController::class, 'update'])->name('api.admin.stock.parts.update');
        Route::delete('/admin/parts/{part}', [PartController::class, 'destroy'])->name('api.admin.stock.parts.destroy');

        Route::post('/admin/suppliers', [SupplierController::class, 'store'])->name('api.admin.stock.suppliers.store');
        Route::patch('/admin/suppliers/{supplier}', [SupplierController::class, 'update'])->name('api.admin.stock.suppliers.update');
        Route::delete('/admin/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('api.admin.stock.suppliers.destroy');

        Route::get('/admin/tax-rates', [TaxRateController::class, 'index'])->name('api.admin.stock.tax-rates.index');
        Route::post('/admin/tax-rates', [TaxRateController::class, 'store'])->name('api.admin.stock.tax-rates.store');
        Route::patch('/admin/tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('api.admin.stock.tax-rates.update');
        Route::delete('/admin/tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->name('api.admin.stock.tax-rates.destroy');

        Route::get('/admin/part-categories', [PartCategoryController::class, 'index'])->name('api.admin.stock.categories.index');
        Route::post('/admin/part-categories', [PartCategoryController::class, 'store'])->name('api.admin.stock.categories.store');
        Route::patch('/admin/part-categories/{category}', [PartCategoryController::class, 'update'])->name('api.admin.stock.categories.update');
        Route::delete('/admin/part-categories/{category}', [PartCategoryController::class, 'destroy'])->name('api.admin.stock.categories.destroy');

        Route::get('/admin/maintenance-plans', [MaintenancePlanController::class, 'index'])->name('api.admin.stock.plans.index');
        Route::post('/admin/maintenance-plans', [MaintenancePlanController::class, 'store'])->name('api.admin.stock.plans.store');
        Route::patch('/admin/maintenance-plans/{plan}', [MaintenancePlanController::class, 'update'])->name('api.admin.stock.plans.update');
        Route::delete('/admin/maintenance-plans/{plan}', [MaintenancePlanController::class, 'destroy'])->name('api.admin.stock.plans.destroy');

        // Stock: reports (admin)
        Route::get('/stock/reports/low-stock.csv', [StockReportController::class, 'lowStockCsv'])->name('api.stock.reports.low-stock.csv');
        Route::get('/stock/reports/inventory.csv', [StockReportController::class, 'inventoryCsv'])->name('api.stock.reports.inventory.csv');
        Route::get('/stock/reports/costs-by-equipment.pdf', [StockReportController::class, 'costsByEquipmentPdf'])->name('api.stock.reports.costs-by-equipment.pdf');
    });

    // Analytics
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/analytics/stats', [AnalyticsController::class, 'stats'])->name('api.analytics.stats');
        Route::get('/analytics/export/csv', [AnalyticsController::class, 'exportCsv'])->name('api.analytics.export.csv')->middleware('rate.limit:10,1');
        Route::get('/analytics/export/pdf', [AnalyticsController::class, 'exportPdf'])->name('api.analytics.export.pdf')->middleware('rate.limit:10,1');
        Route::get('/analytics/export/excel', [AnalyticsController::class, 'exportExcel'])->name('api.analytics.export.excel')->middleware('rate.limit:10,1');
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::patch('/notifications/{id}', [NotificationController::class, 'markAsRead'])->name('api.notifications.mark-read');
    Route::post('/notifications/test-email', [NotificationController::class, 'sendTestEmail'])
        ->name('api.notifications.test-email')
        ->middleware('rate.limit:5,1');
});
