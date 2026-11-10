<?php

use App\Http\Controllers\Admin\Hr\AttendanceController as HrAttendanceController;
use App\Http\Controllers\Admin\Hr\DepartmentController as HrDepartmentController;
use App\Http\Controllers\Admin\Hr\EmployeeController as HrEmployeeController;
use App\Http\Controllers\Admin\Hr\LeaveController as HrLeaveController;
use App\Http\Controllers\Admin\Hr\PositionController as HrPositionController;
use App\Http\Controllers\Admin\RbacController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminEquipmentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PartCategoryController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StockDashboardController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\StockUiController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\Ticket\TicketAssignmentController;
use App\Http\Controllers\Ticket\TicketCloseController;
use App\Http\Controllers\Ticket\TicketLifecycleController;
use App\Http\Controllers\Ticket\TicketScheduleController;
use App\Http\Controllers\Ticket\TicketStartController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketBudgetController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UiController;
use App\Support\ExportArtifact;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Open Access)
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/lang/{locale}', [PageController::class, 'switchLang'])->name('lang.switch');
Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/ui/login', [PageController::class, 'login'])->name('ui.login');
Route::get('/theme/custom.css', [ThemeController::class, 'customCss'])->name('theme.custom');
Route::get('/test-email', [PageController::class, 'testEmail'])->name('test.email');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login')
    ->middleware(['rate.limit:5,1'])
    ->withoutMiddleware([PreventRequestForgery::class]);

/*
|--------------------------------------------------------------------------
| Public Ticket Reporting Routes (via QR Code)
|--------------------------------------------------------------------------
*/
Route::get('/ticket/new', [PublicTicketController::class, 'create'])
    ->name('ticket.public.create')
    ->middleware(['rate.limit:20,1']);
Route::post('/ticket/store', [PublicTicketController::class, 'store'])
    ->name('ticket.public.store')
    ->middleware(['rate.limit:5,1']);
// Signed, not just numeric: the confirmation page prints the reference, the equipment and the
// priority of the ticket, and ticket ids are sequential. Without a signature any anonymous
// visitor could walk the range and read reports created by internal users.
Route::get('/ticket/success/{ticket}', [PublicTicketController::class, 'success'])
    ->name('ticket.public.success')
    ->where('ticket', '[0-9]+')
    ->middleware(['signed', 'rate.limit:20,1']);

// --- User Preferences (Language, Currency, Date Format) ---
Route::get('/preferences', [PreferencesController::class, 'edit'])
    ->name('preferences.edit');
Route::post('/preferences/language', [PreferencesController::class, 'updateLanguage'])
    ->name('preferences.update_language')
    ->withoutMiddleware([PreventRequestForgery::class]);
Route::post('/preferences/currency', [PreferencesController::class, 'updateCurrency'])
    ->name('preferences.update_currency')
    ->withoutMiddleware([PreventRequestForgery::class]);
Route::post('/preferences/date-format', [PreferencesController::class, 'updateDateFormat'])
    ->name('preferences.update_date_format')
    ->withoutMiddleware([PreventRequestForgery::class]);
Route::post('/preferences/time-format', [PreferencesController::class, 'updateTimeFormat'])
    ->name('preferences.update_time_format')
    ->withoutMiddleware([PreventRequestForgery::class]);
Route::post('/preferences/number-format', [PreferencesController::class, 'updateNumberFormat'])
    ->name('preferences.update_number_format')
    ->withoutMiddleware([PreventRequestForgery::class]);
Route::post('/preferences', [PreferencesController::class, 'updateAll'])
    ->name('preferences.update_all')
    ->withoutMiddleware([PreventRequestForgery::class]);

/*
|--------------------------------------------------------------------------
| Protected Routes (Require Valid Auth Token via custom.auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['custom.auth'])->group(function () {

    // --- Account and Profile ---
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('auth.logout')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::post('/password/change', [ProfileController::class, 'changePassword'])
        ->name('auth.password.change')
        ->middleware('rate.limit:10,1');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])
        ->name('auth.profile.update')
        ->middleware('rate.limit:10,1');

    // --- Generated export artifacts (owner only) ---
    Route::get('/exports/{userId}/{filename}', ExportDownloadController::class)
        ->where(['userId' => '\d+', 'filename' => ExportArtifact::FILENAME_PATTERN])
        ->name('exports.download');

    // --- Notifications ---
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{id}', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-read')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::post('/notifications/test-email', [NotificationController::class, 'sendTestEmail'])
        ->name('notifications.test-email')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->middleware('rate.limit:5,1');

    // --- Web Interface (UI) ---
    Route::get('/ui', [UiController::class, 'index'])->name('ui.index');
    Route::get('/ui/profile', [UiController::class, 'profile'])->name('ui.profile');
    Route::get('/ui/tickets', [UiController::class, 'tickets'])->name('ui.tickets');
    Route::get('/ui/tickets/create', [UiController::class, 'ticketCreate'])
        ->name('ui.tickets.create')
        ->middleware('role:admin,user');
    Route::get('/ui/tickets/{id}', [UiController::class, 'ticketDetail'])->name('ui.tickets.show');
    Route::get('/ui/equipments', [UiController::class, 'equipments'])->name('ui.equipments');
    Route::get('/equipments', [UiController::class, 'getEquipments'])->name('equipments.list');
    Route::get('/dashboard/picket', [UiController::class, 'getTechnicalPicket'])->name('dashboard.picket');
    Route::get('/dashboard/tickets-active', [UiController::class, 'getActiveTickets'])->name('dashboard.tickets-active');

    // --- Theme / appearance: available to any authenticated user ---
    Route::get('/ui/settings/appearance', [UiController::class, 'themeAppearance'])->name('ui.settings.appearance');
    Route::post('/ui/settings/appearance', [UiController::class, 'themeAppearanceUpdate'])
        ->name('ui.settings.appearance.update')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::post('/theme/switch', [ThemeController::class, 'switchTheme'])
        ->name('theme.switch')
        ->withoutMiddleware([PreventRequestForgery::class]);

    // --- Equipment: create, detail, and edit pages ---
    Route::get('/ui/equipments/create', [UiController::class, 'equipmentCreate'])
        ->name('ui.equipments.create')
        ->middleware('role:admin');
    Route::get('/ui/equipments/{equipment}', [UiController::class, 'equipmentDetail'])
        ->name('ui.equipments.show')
        ->where('equipment', '[0-9]+');
    Route::get('/ui/equipments/{equipment}/edit', [UiController::class, 'equipmentEdit'])
        ->name('ui.equipments.edit')
        ->middleware('role:admin');

    // --- Rooms ---
    Route::get('/ui/rooms', [UiController::class, 'rooms'])->name('ui.rooms');
    Route::get('/ui/rooms/{room}', [UiController::class, 'roomDetail'])
        ->name('ui.rooms.show')
        ->where('room', '[0-9]+');

    // --- Stock: Web Interface ---
    Route::get('/ui/stock', [StockUiController::class, 'dashboard'])->name('ui.stock.dashboard');
    Route::get('/ui/stock/parts', [StockUiController::class, 'parts'])->name('ui.stock.parts');
    Route::get('/ui/stock/parts/create', [StockUiController::class, 'partCreate'])
        ->name('ui.stock.parts.create')
        ->middleware('role:admin');
    Route::get('/ui/stock/parts/{part}', [StockUiController::class, 'partShow'])
        ->name('ui.stock.parts.show')
        ->where('part', '[0-9]+');
    Route::get('/ui/stock/parts/{part}/edit', [StockUiController::class, 'partEdit'])
        ->name('ui.stock.parts.edit')
        ->middleware('role:admin')
        ->where('part', '[0-9]+');
    Route::get('/ui/stock/suppliers', [StockUiController::class, 'suppliers'])->name('ui.stock.suppliers');
    Route::get('/ui/stock/suppliers/create', [StockUiController::class, 'supplierCreate'])
        ->name('ui.stock.suppliers.create')
        ->middleware('role:admin');
    Route::get('/ui/stock/suppliers/{supplier}/edit', [StockUiController::class, 'supplierEdit'])
        ->name('ui.stock.suppliers.edit')
        ->middleware('role:admin')
        ->where('supplier', '[0-9]+');
    Route::get('/ui/stock/movements', [StockUiController::class, 'movements'])->name('ui.stock.movements');
    Route::get('/ui/stock/tax-rates', [StockUiController::class, 'taxRates'])
        ->name('ui.stock.tax-rates')
        ->middleware('role:admin');
    Route::get('/ui/stock/categories', [StockUiController::class, 'categories'])
        ->name('ui.stock.categories')
        ->middleware('role:admin');
    Route::get('/ui/stock/plans', [StockUiController::class, 'plans'])
        ->name('ui.stock.plans')
        ->middleware('role:admin');

    // --- Rooms API ---
    Route::get('/api/rooms', [RoomController::class, 'indexRoom'])->name('rooms.index');
    Route::post('/api/rooms', [RoomController::class, 'storeRoom'])
        ->name('rooms.store')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::put('/api/rooms/{room}', [RoomController::class, 'updateRoom'])
        ->name('rooms.update')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::patch('/api/rooms/{room}', [RoomController::class, 'updateRoom'])
        ->name('rooms.update-patch')
        ->withoutMiddleware([PreventRequestForgery::class]);

    // --- Tickets ---
    Route::get('/tickets/search', [TicketController::class, 'search'])->name('tickets.search');
    Route::get('/tickets/most-urgent', [TicketController::class, 'getMostUrgentOpenTicket'])->name('tickets.most-urgent');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->name('tickets.store')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->middleware('rate.limit:30,1');

    // --- Tickets: Comments ---
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])
        ->name('tickets.comments.store')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->middleware('rate.limit:30,1');
    Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('tickets.comments.index');

    // --- Tickets: Photos ---
    Route::post('/tickets/{ticket}/photos', [TicketAttachmentController::class, 'store'])
        ->name('tickets.photos.store')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->middleware('rate.limit:30,1');
    Route::get('/tickets/{ticket}/photos', [TicketAttachmentController::class, 'index'])->name('tickets.photos.index');
    Route::delete('/tickets/{ticket}/photos/{attachment}', [TicketAttachmentController::class, 'destroy'])
        ->name('tickets.photos.destroy')
        ->withoutMiddleware([PreventRequestForgery::class]);

    // --- Tickets: Status Workflow ---
    Route::post('/tickets/{ticket}/reopen', [TicketLifecycleController::class, 'reopen'])
        ->name('tickets.reopen')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::post('/tickets/{ticket}/cancel', [TicketLifecycleController::class, 'cancel'])
        ->name('tickets.cancel')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::post('/tickets/{ticket}/schedule', TicketScheduleController::class)
        ->name('tickets.schedule')
        ->withoutMiddleware([PreventRequestForgery::class]);

    // --- Tickets: Budget Workflow ---
    Route::post('/tickets/{ticket}/budget', [TicketBudgetController::class, 'submitEstimate'])
        ->name('tickets.budget')
        ->withoutMiddleware([PreventRequestForgery::class]);
    Route::post('/tickets/{ticket}/close', [TicketCloseController::class, 'closeFinal'])
        ->name('tickets.close')
        ->withoutMiddleware([PreventRequestForgery::class]);

    // --- Calendar ---
    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.view');
    Route::patch('/calendar/events/{ticket}', [CalendarController::class, 'reschedule'])->name('calendar.events.reschedule');

    // --- Technician Area ---
    Route::middleware(['role:technician'])->group(function () {
        Route::put('/technician/tickets/{ticket}/start', TicketStartController::class)
            ->name('technician.tickets.start')
            ->withoutMiddleware([PreventRequestForgery::class])
            ->middleware('rate.limit:20,1');
        Route::put('/technician/tickets/{ticket}/close', [TicketCloseController::class, 'simpleClose'])
            ->name('technician.tickets.close')
            ->withoutMiddleware([PreventRequestForgery::class])
            ->middleware('rate.limit:20,1');
        Route::put('/technician/tickets/{ticket}/request-budget', [TicketBudgetController::class, 'requestAuthorization'])
            ->name('technician.tickets.request-budget')
            ->withoutMiddleware([PreventRequestForgery::class])
            ->middleware('rate.limit:20,1');
    });

    // --- Stock: read and register movements (admins and technicians) ---
    Route::middleware(['role:admin,technician'])->group(function () {
        Route::get('/stock/parts', [PartController::class, 'index'])->name('stock.parts.index');
        Route::get('/stock/parts/{part}', [PartController::class, 'show'])->name('stock.parts.show');
        Route::get('/stock/suppliers', [SupplierController::class, 'index'])->name('stock.suppliers.index');
        Route::get('/stock/suppliers/{supplier}', [SupplierController::class, 'show'])->name('stock.suppliers.show');
        Route::get('/stock/movements', [StockMovementController::class, 'index'])->name('stock.movements.index');
        Route::post('/stock/movements', [StockMovementController::class, 'store'])
            ->name('stock.movements.store')
            ->withoutMiddleware([PreventRequestForgery::class]);
        Route::get('/stock/dashboard/summary', [StockDashboardController::class, 'summary'])->name('stock.dashboard.summary');
        Route::get('/stock/dashboard/top-consumed', [StockDashboardController::class, 'topConsumed'])->name('stock.dashboard.top-consumed');
        Route::get('/stock/dashboard/slow-moving', [StockDashboardController::class, 'slowMoving'])->name('stock.dashboard.slow-moving');
        Route::get('/stock/dashboard/runout-forecast', [StockDashboardController::class, 'runoutForecast'])->name('stock.dashboard.runout-forecast');
        Route::get('/stock/dashboard/cost-by-equipment', [StockDashboardController::class, 'costByEquipment'])->name('stock.dashboard.cost-by-equipment');
        Route::get('/stock/dashboard/cost-by-ticket', [StockDashboardController::class, 'costByTicket'])->name('stock.dashboard.cost-by-ticket');
    });

    // --- Administration Area ---
    Route::middleware(['role:admin'])->group(function () {

        // Admin UI
        Route::get('/ui/users', [UiController::class, 'users'])->name('ui.users');
        Route::get('/ui/audits', [UiController::class, 'audits'])->name('ui.audits');
        Route::get('/ui/users/create', [UiController::class, 'userCreate'])->name('ui.users.create');
        Route::get('/ui/users/{targetUser}/edit', [UiController::class, 'userEdit'])->name('ui.users.edit');
        Route::get('/ui/users/{targetUser}', [UiController::class, 'userDetail'])->name('ui.users.show');
        Route::get('/ui/rooms/create', [UiController::class, 'roomCreate'])->name('ui.rooms.create');
        Route::get('/ui/rooms/{room}/edit', [UiController::class, 'roomEdit'])->name('ui.rooms.edit');
        Route::get('/ui/analytics', [UiController::class, 'analytics'])->name('ui.analytics');
        Route::get('/ui/settings/system', [SystemSettingsController::class, 'index'])->name('ui.settings.system');
        Route::post('/ui/settings/system', [SystemSettingsController::class, 'update'])->name('ui.settings.system.update');
        Route::post('/calendar/maintenance', [CalendarController::class, 'scheduleMaintenance'])->name('calendar.maintenance');

        // Open tickets
        Route::get('/technician/tickets/open', [TicketController::class, 'openTickets'])->name('technician.tickets.open');
        Route::post('/tickets/{ticket}/assign-technician', [TicketAssignmentController::class, '__invoke'])
            ->name('tickets.assign-technician')
            ->withoutMiddleware([PreventRequestForgery::class])
            ->middleware('rate.limit:20,1');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'stats'])->name('analytics.stats');
        // Each export renders the whole dataset into a spreadsheet/PDF, so they are capped
        // separately from the cheap stats endpoint.
        Route::get('/analytics/export/csv', [AnalyticsController::class, 'exportCsv'])->name('analytics.export.csv')->middleware('rate.limit:10,1');
        Route::get('/analytics/export/pdf', [AnalyticsController::class, 'exportPdf'])->name('analytics.export.pdf')->middleware('rate.limit:10,1');
        Route::get('/analytics/export/excel', [AnalyticsController::class, 'exportExcel'])->name('analytics.export.excel')->middleware('rate.limit:10,1');

        // User registration
        Route::post('/admin/users/register', [RegisterController::class, '__invoke'])
            ->name('admin.users.register')
            ->middleware(['rate.limit:5,1']);

        // AI
        Route::get('/admin/tickets/{ticket}', [TicketController::class, 'show'])->name('admin.tickets.show');
        Route::patch('/admin/tickets/{ticket}/assign', [TicketAssignmentController::class, '__invoke'])
            ->name('admin.tickets.assign')
            ->withoutMiddleware([PreventRequestForgery::class]);

        // Audit
        Route::get('/admin/audits', [AuditController::class, 'index'])->name('admin.audits.index');

        // User Management
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('/admin/users/{targetUser}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::patch('/admin/users/{targetUser}/inactive', [AdminUserController::class, 'inactivate'])->name('admin.users.inactivate');
        Route::delete('/admin/users/{targetUser}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('/admin/profiles', [AdminUserController::class, 'profiles'])->name('admin.profiles.index');

        // Equipment Management
        Route::get('/admin/equipment', [AdminEquipmentController::class, 'index'])->name('admin.equipment.index');
        Route::post('/admin/equipment', [AdminEquipmentController::class, 'store'])->name('admin.equipment.store');
        Route::patch('/admin/equipment/{equipment}', [AdminEquipmentController::class, 'update'])->name('admin.equipment.update');
        Route::delete('/admin/equipment/{equipment}', [AdminEquipmentController::class, 'destroy'])->name('admin.equipment.destroy');

        // Equipment QR Codes
        Route::get('/ui/equipments/{equipment}/qr', [QrCodeController::class, 'show'])
            ->name('ui.equipments.qr')
            ->where('equipment', '[0-9]+');
        Route::get('/ui/equipments/{equipment}/qr/download', [QrCodeController::class, 'download'])
            ->name('ui.equipments.qr.download')
            ->where('equipment', '[0-9]+');
        Route::get('/ui/equipments/qr/export', [QrCodeController::class, 'exportPdf'])
            ->name('ui.equipments.qr.export')
            ->middleware('rate.limit:10,1');

        // Room Management
        Route::get('/admin/rooms', [RoomController::class, 'indexRoom'])->name('admin.rooms.index');
        Route::post('/admin/rooms', [RoomController::class, 'storeRoom'])->name('admin.rooms.store');
        Route::patch('/admin/rooms/{room}', [RoomController::class, 'updateRoom'])->name('admin.rooms.update');
        Route::patch('/admin/rooms/{room}/inactive', [RoomController::class, 'inactivateRoom'])->name('admin.rooms.inactivate');

        // Budget and Preventive Maintenance
        Route::post('/admin/preventive', [AdminController::class, 'storePreventive'])->name('admin.preventive.store');
        Route::patch('/admin/tickets/{ticket}/approve-budget', [AdminController::class, 'approveBudget'])
            ->name('admin.tickets.approve-budget')
            ->withoutMiddleware([PreventRequestForgery::class]);

        // --- Stock: Management (admin) ---
        Route::post('/admin/parts', [PartController::class, 'store'])->name('admin.stock.parts.store');
        Route::patch('/admin/parts/{part}', [PartController::class, 'update'])->name('admin.stock.parts.update');
        Route::delete('/admin/parts/{part}', [PartController::class, 'destroy'])->name('admin.stock.parts.destroy');

        Route::post('/admin/suppliers', [SupplierController::class, 'store'])->name('admin.stock.suppliers.store');
        Route::patch('/admin/suppliers/{supplier}', [SupplierController::class, 'update'])->name('admin.stock.suppliers.update');
        Route::delete('/admin/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('admin.stock.suppliers.destroy');

        Route::post('/admin/tax-rates', [TaxRateController::class, 'store'])->name('admin.stock.tax-rates.store');
        Route::patch('/admin/tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('admin.stock.tax-rates.update');
        Route::delete('/admin/tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->name('admin.stock.tax-rates.destroy');

        Route::post('/admin/part-categories', [PartCategoryController::class, 'store'])->name('admin.stock.categories.store');
        Route::patch('/admin/part-categories/{category}', [PartCategoryController::class, 'update'])->name('admin.stock.categories.update');
        Route::delete('/admin/part-categories/{category}', [PartCategoryController::class, 'destroy'])->name('admin.stock.categories.destroy');

        Route::get('/admin/maintenance-plans', [MaintenancePlanController::class, 'index'])->name('admin.stock.plans.index');
        Route::post('/admin/maintenance-plans', [MaintenancePlanController::class, 'store'])->name('admin.stock.plans.store');
        Route::patch('/admin/maintenance-plans/{plan}', [MaintenancePlanController::class, 'update'])->name('admin.stock.plans.update');
        Route::delete('/admin/maintenance-plans/{plan}', [MaintenancePlanController::class, 'destroy'])->name('admin.stock.plans.destroy');
        Route::get('/admin/maintenance-plans/{plan}', [MaintenancePlanController::class, 'show'])->name('admin.stock.plans.show');

        // --- Stock: Reports and Exports ---
        Route::get('/stock/reports/low-stock.csv', [StockReportController::class, 'lowStockCsv'])->name('stock.reports.low-stock.csv');
        Route::get('/stock/reports/inventory.csv', [StockReportController::class, 'inventoryCsv'])->name('stock.reports.inventory.csv');
        Route::get('/stock/reports/costs-by-equipment.pdf', [StockReportController::class, 'costsByEquipmentPdf'])->name('stock.reports.costs-by-equipment.pdf');

        // --- RBAC Management (Admin only) ---
        Route::get('/admin/rbac/roles', [RbacController::class, 'roles'])->name('ui.admin.rbac.roles');
        Route::post('/admin/rbac/roles', [RbacController::class, 'storeRole'])->name('ui.admin.rbac.roles.store');
        Route::get('/admin/rbac/roles/{role}/edit', [RbacController::class, 'editRole'])->name('ui.admin.rbac.roles.edit');
        Route::patch('/admin/rbac/roles/{role}', [RbacController::class, 'updateRole'])->name('ui.admin.rbac.roles.update');
        Route::get('/admin/rbac/user-roles', [RbacController::class, 'userRoles'])->name('ui.admin.rbac.user-roles');
        Route::patch('/admin/rbac/user-roles/{user}', [RbacController::class, 'updateUserRole'])->name('ui.admin.rbac.user-roles.update');
    });

    // --- HR Module (permission-based access) ---
    Route::middleware(['permission:hr.access'])->group(function () {
        // Departments
        Route::get('/ui/hr/departments', [HrDepartmentController::class, 'index'])->name('ui.hr.departments.index');
        Route::get('/ui/hr/departments/create', [HrDepartmentController::class, 'create'])->name('ui.hr.departments.create');
        Route::post('/ui/hr/departments', [HrDepartmentController::class, 'store'])->name('ui.hr.departments.store');
        Route::get('/ui/hr/departments/{department}/edit', [HrDepartmentController::class, 'edit'])->name('ui.hr.departments.edit');
        Route::patch('/ui/hr/departments/{department}', [HrDepartmentController::class, 'update'])->name('ui.hr.departments.update');
        Route::delete('/ui/hr/departments/{department}', [HrDepartmentController::class, 'destroy'])->name('ui.hr.departments.destroy');

        // Positions
        Route::get('/ui/hr/positions', [HrPositionController::class, 'index'])->name('ui.hr.positions.index');
        Route::get('/ui/hr/positions/create', [HrPositionController::class, 'create'])->name('ui.hr.positions.create');
        Route::post('/ui/hr/positions', [HrPositionController::class, 'store'])->name('ui.hr.positions.store');
        Route::get('/ui/hr/positions/{position}/edit', [HrPositionController::class, 'edit'])->name('ui.hr.positions.edit');
        Route::patch('/ui/hr/positions/{position}', [HrPositionController::class, 'update'])->name('ui.hr.positions.update');
        Route::delete('/ui/hr/positions/{position}', [HrPositionController::class, 'destroy'])->name('ui.hr.positions.destroy');

        // Employees
        Route::get('/ui/hr/employees', [HrEmployeeController::class, 'index'])->name('ui.hr.employees.index');
        Route::get('/ui/hr/employees/create', [HrEmployeeController::class, 'create'])->name('ui.hr.employees.create');
        Route::post('/ui/hr/employees', [HrEmployeeController::class, 'store'])->name('ui.hr.employees.store');
        Route::get('/ui/hr/employees/{employee}', [HrEmployeeController::class, 'show'])->name('ui.hr.employees.show');
        Route::get('/ui/hr/employees/{employee}/edit', [HrEmployeeController::class, 'edit'])->name('ui.hr.employees.edit');
        Route::patch('/ui/hr/employees/{employee}', [HrEmployeeController::class, 'update'])->name('ui.hr.employees.update');
        Route::delete('/ui/hr/employees/{employee}', [HrEmployeeController::class, 'destroy'])->name('ui.hr.employees.destroy');

        // Attendance
        Route::get('/ui/hr/attendance', [HrAttendanceController::class, 'index'])->name('ui.hr.attendance.index');
        Route::post('/ui/hr/attendance', [HrAttendanceController::class, 'store'])->name('ui.hr.attendance.store');
        Route::delete('/ui/hr/attendance/{attendance}', [HrAttendanceController::class, 'destroy'])->name('ui.hr.attendance.destroy');

        // Leaves
        Route::get('/ui/hr/leaves', [HrLeaveController::class, 'index'])->name('ui.hr.leaves.index');
        Route::post('/ui/hr/leaves', [HrLeaveController::class, 'store'])->name('ui.hr.leaves.store');
        Route::post('/ui/hr/leaves/types', [HrLeaveController::class, 'storeType'])->name('ui.hr.leaves.types.store');
        Route::patch('/ui/hr/leaves/{leave}/status', [HrLeaveController::class, 'updateStatus'])->name('ui.hr.leaves.status');
    });
});
