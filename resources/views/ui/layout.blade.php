<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LocaleService::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('common.Gestão de Avarias'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    @vite('resources/js/early-theme.js')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ route('theme.custom') }}?v={{ \App\Http\Controllers\ThemeController::cacheBuster(request()->user()?->theme) }}">

    @include('ui.partials.theme-meta')

    @include('ui.partials.locale-config')

    @stack('styles')
    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            var dark = saved ? saved === 'dark' : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>

<body data-page="@yield('page_key')"
      data-login-url="{{ route('ui.login') }}"
      data-user-admin="{{ (request()->user() && request()->user()->isAdmin()) ? '1' : '0' }}"
      data-auth-profile="{{ __('auth_box.profile') }}"
      data-auth-logout="{{ __('auth_box.logout') }}"
      data-auth-signin="{{ __('auth_box.signin') }}"
      data-auth-login-register="{{ __('auth_box.login_register') }}"
      data-room-equipment-count="{{ __('room.equipment_count') }}"
      data-room-edit="{{ __('room.edit') }}"
      data-room-details="{{ __('room.details') }}"
      data-room-empty="{{ __('room.empty') }}"
      data-stock-part-min="{{ __('stock_part.min') }}"
      data-stock-part-out="{{ __('stock_part.out') }}"
      data-stock-part-low="{{ __('stock_part.low') }}"
      data-stock-part-ok="{{ __('stock_part.ok') }}"
      data-stock-part-details="{{ __('stock_part.details') }}"
      data-stock-part-edit="{{ __('stock_part.edit') }}"
      data-stock-part-empty="{{ __('stock_part.empty') }}"
      data-stock-supplier-edit="{{ __('ui.Editar') }}"
      data-stock-supplier-delete="{{ __('ui.Eliminar') }}"
      data-pagination-previous="{{ __('pagination.previous') }}"
      data-pagination-next="{{ __('pagination.next') }}"
      data-pagination-page="{{ __('pagination.page') }}"
      data-pagination-of="{{ __('pagination.of') }}"
      data-plan-days="{{ __('maintenance_plan.days') }}"
      data-plan-usage-hours="{{ __('maintenance_plan.usage_hours') }}"
      data-plan-cycles="{{ __('maintenance_plan.cycles') }}"
      data-plan-active="{{ __('maintenance_plan.active') }}"
      data-plan-inactive="{{ __('maintenance_plan.inactive') }}"
      data-plan-parts="{{ __('maintenance_plan.parts') }}"
      data-plan-edit="{{ __('maintenance_plan.edit') }}"
      data-plan-delete="{{ __('maintenance_plan.delete') }}"
      data-plan-empty="{{ __('maintenance_plan.empty') }}"
      data-stock-dashboard-in-stock="{{ __('stock_dashboard.in_stock') }}"
      data-stock-dashboard-month="{{ __('stock_dashboard.month') }}"
      data-stock-dashboard-months="{{ __('stock_dashboard.months') }}"
      data-stock-dashboard-consumption="{{ __('stock_dashboard.consumption') }}"
      data-audit-all-events="{{ __('common.Todas as Ações') }}"
      data-audit-created="{{ __('auth.Registo Criado') }}"
      data-audit-updated="{{ __('auth.Registo Atualizado') }}"
      data-audit-deleted="{{ __('auth.Registo Eliminado') }}"
      data-analytics-resolution="{{ __('analytics.resolution') }}"
      data-analytics-waiting="{{ __('analytics.waiting') }}"
      data-analytics-open="{{ __('analytics.open') }}"
      data-analytics-resolved="{{ __('analytics.resolved') }}"
      data-analytics-mttr="{{ __('analytics.mttr') }}"
      data-analytics-assignment="{{ __('analytics.assignment') }}"
      data-analytics-active="{{ __('analytics.active') }}"
      data-analytics-completed="{{ __('analytics.completed') }}"
      data-analytics-minute="{{ __('analytics.minute') }}"
      data-analytics-minutes="{{ __('analytics.minutes') }}"
      data-analytics-hour="{{ __('analytics.hour') }}"
      data-analytics-hours="{{ __('analytics.hours') }}"
      data-analytics-day="{{ __('analytics.day') }}"
      data-analytics-days="{{ __('analytics.days') }}"
      data-analytics-tickets="{{ __('tickets.Tickets') }}"
      data-analytics-data-urgent="{{ __('analytics_data.urgent') }}"
      data-analytics-data-normal="{{ __('analytics_data.normal') }}"
      data-analytics-data-web="{{ __('analytics_data.web') }}"
      data-analytics-data-qr="{{ __('analytics_data.qr') }}"
      data-analytics-data-api="{{ __('analytics_data.api') }}"
      data-analytics-data-mobile="{{ __('analytics_data.mobile') }}"
      data-analytics-data-phone="{{ __('analytics_data.phone') }}"
      data-analytics-data-ticket-updated="{{ __('analytics_data.ticket_updated') }}"
      data-analytics-data-ticket-assigned="{{ __('analytics_data.ticket_assigned') }}"
      data-analytics-data-comment-added="{{ __('analytics_data.comment_added') }}"
      data-analytics-data-attachment-added="{{ __('analytics_data.attachment_added') }}"
      data-analytics-data-budget-request="{{ __('analytics_data.budget_request') }}"
      class="app-shell ui-shell{{ \App\Services\LocaleService::isRtl(app()->getLocale()) ? ' rtl' : '' }}">

    @php
        $user = $user ?? auth()->user() ?? request()->user();
        $userRole = $user?->profile?->name ?? null;

        // --- Inline icons (Heroicons outline — consistent with the existing convention) ---
        $icon = [
            'chart' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
            'ticket' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9.75-3v.75m3-3v.75m0 3v.75M4.5 21h15a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0019.5 3h-15A2.25 2.25 0 002.25 5.25v13.5A2.25 2.25 0 004.5 21z"/></svg>',
            'computer' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>',
            'door' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>',
            'calendar' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>',
            'box' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>',
            'users' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
            'doc' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
            'trend' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>',
            'swatch' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z"/></svg>',
            'gear' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            'book' => '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>',
        ];

        // Sidebar menu: only Admin sees Users/Auditoria/Analytics/Swagger
        $navItems = [
            ['href' => 'ui', 'active' => 'ui', 'label' => __('dashboard.Dashboard'), 'icon' => $icon['chart'], 'exact' => true],
            ['href' => 'ui/tickets', 'active' => 'ui/tickets*', 'label' => __('tickets.Tickets'), 'icon' => $icon['ticket'], 'exact' => false],
            [
                'href' => 'ui/equipments',
                'active' => 'ui/equipments*',
                'label' => __('equipment.Equipamentos'),
                'icon' => $icon['computer'],
                'exact' => false,
            ],
            ['href' => 'ui/rooms', 'active' => 'ui/rooms*', 'label' => __('room.Salas'), 'icon' => $icon['door'], 'exact' => false],
            ['href' => 'calendar', 'active' => 'calendar*', 'label' => __('common.Agenda'), 'icon' => $icon['calendar'], 'exact' => false],
        ];

        // Stock pages are gated by PartPolicy/SupplierPolicy (admin + technician).
        if ($userRole === 'admin' || $userRole === 'technician') {
            $navItems[] = [
                'href' => 'ui/stock',
                'active' => 'ui/stock*',
                'label' => __('stock.Stock'),
                'icon' => $icon['box'],
                'exact' => false,
            ];
        }

        // HR module (permission-based)
        if ($user && ($user->isAdmin() || $user->hasPermission('hr.access'))) {
            $hrItems = [];
            if ($user->isAdmin() || $user->hasPermission('hr.employees.view')) {
                $hrItems[] = ['href' => 'ui/hr/employees', 'active' => 'ui/hr/employees*', 'label' => 'Colaboradores', 'icon' => $icon['users'], 'exact' => false];
            }
            if ($user->isAdmin() || $user->hasPermission('hr.departments.view')) {
                $hrItems[] = ['href' => 'ui/hr/departments', 'active' => 'ui/hr/departments*', 'label' => 'Departamentos', 'icon' => $icon['doc'], 'exact' => false];
            }
            if ($user->isAdmin() || $user->hasPermission('hr.positions.view')) {
                $hrItems[] = ['href' => 'ui/hr/positions', 'active' => 'ui/hr/positions*', 'label' => 'Cargos', 'icon' => $icon['doc'], 'exact' => false];
            }
            if ($user->isAdmin() || $user->hasPermission('hr.attendance.view')) {
                $hrItems[] = ['href' => 'ui/hr/attendance', 'active' => 'ui/hr/attendance*', 'label' => 'Assiduidade', 'icon' => $icon['calendar'], 'exact' => false];
            }
            if ($user->isAdmin() || $user->hasPermission('hr.leaves.view')) {
                $hrItems[] = ['href' => 'ui/hr/leaves', 'active' => 'ui/hr/leaves*', 'label' => 'Ausências/Férias', 'icon' => $icon['calendar'], 'exact' => false];
            }
            $navItems = array_merge($navItems, $hrItems);
        }

        $navItems[] = [
            'href' => 'ui/settings/appearance',
            'active' => 'ui/settings/appearance*',
            'label' => __('ui.Tema'),
            'icon' => $icon['swatch'],
            'exact' => false,
        ];

        if ($userRole === 'admin') {
            $navItems = array_merge($navItems, [
                [
                    'href' => 'ui/users',
                    'active' => 'ui/users*',
                    'label' => __('common.Utilizadores'),
                    'icon' => $icon['users'],
                    'exact' => false,
                ],
                ['href' => 'admin/rbac/roles', 'active' => 'admin/rbac*', 'label' => 'Acessos & Roles', 'icon' => $icon['gear'], 'exact' => false],
                ['href' => 'ui/audits', 'active' => 'ui/audits*', 'label' => __('common.Auditoria'), 'icon' => $icon['doc'], 'exact' => false],
                [
                    'href' => 'ui/analytics',
                    'active' => 'ui/analytics*',
                    'label' => __('common.Analytics'),
                    'icon' => $icon['trend'],
                    'exact' => false,
                ],
                [
                    'href' => 'ui/settings/system',
                    'active' => 'ui/settings/system*',
                    'label' => __('common.Definições'),
                    'icon' => $icon['gear'],
                    'exact' => false,
                ],
                [
                    'href' => 'docs/openapi',
                    'active' => 'docs/openapi*',
                    'label' => 'OpenAPI (Swagger)',
                    'icon' => $icon['book'],
                    'exact' => false,
                ],
            ]);
        }
    @endphp


    <a href="#main-content" class="skip-link">
        {{ __('common.Ir para o conteúdo') }}
    </a>

    {{-- Background Gradient and Glow Effects (Glow Blobs) --}}
    <div class="fixed inset-0 -z-50 pointer-events-none">
        @include('ui.partials.background-effects')
    </div>

    @include('ui.partials.mobile-nav', ['navItems' => $navItems])

    <div class="page-wrapper">
        @include('ui.partials.desktop-sidebar', ['navItems' => $navItems])

        {{-- Main Content Area --}}
        <div id="mainWrapper" class="flex-1 transition-all duration-300">
            @include('ui.partials.topbar')

            {{-- Injected Viewport --}}
            <main id="main-content" role="main" tabindex="-1" class="page-content outline-none">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts-top')
    @stack('scripts')

    @include('ui.partials.localization-modal')
    @include('ui.partials.notifications-modal')
</body>

</html>
