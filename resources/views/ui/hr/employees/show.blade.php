@extends('ui.layout')
@section('page_key', 'hr-employees')
@section('content')
<x-ui.partials.page-header title="{{ $employee->full_name }}" subtitle="Ficha de colaborador" badge="RH">
    <x-slot:actions>
        <a href="{{ route('ui.hr.employees.contracts.index', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Contratos</a>
        <a href="{{ route('ui.hr.employees.schedules.index', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Horários/Escalas</a>
        <a href="{{ route('ui.hr.employees.documents.index', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Documentos</a>
        <a href="{{ route('ui.hr.employees.edit', $employee) }}" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Editar</a>
        <a href="{{ route('ui.hr.employees.index') }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Voltar</a>
    </x-slot:actions>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6 space-y-3 text-sm">
            <h3 class="font-bold text-base mb-2">Dados gerais</h3>
            <p><span class="text-[var(--text-soft)]">N.º:</span> {{ $employee->employee_number ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Email:</span> {{ $employee->email ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Telefone:</span> {{ $employee->phone ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">NIF:</span> {{ $employee->tax_id ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Departamento:</span> {{ $employee->department?->name ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Cargo:</span> {{ $employee->position?->name ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Utilizador ligado:</span> {{ $employee->user?->name ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Admissão:</span> {{ optional($employee->hire_date)->format('Y-m-d') ?? '-' }}</p>
            <p><span class="text-[var(--text-soft)]">Estado:</span> {{ $employee->status }}</p>
            <p><span class="text-[var(--text-soft)]">Morada:</span> {{ $employee->address ?? '-' }}</p>
            @if($employee->notes)<p><span class="text-[var(--text-soft)]">Notas:</span> {{ $employee->notes }}</p>@endif
        </div>

        <div class="space-y-6">
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-base">Contratos</h3>
                    <a href="{{ route('ui.hr.employees.contracts.index', $employee) }}" class="text-primary text-xs font-semibold">Gerir</a>
                </div>
                @if($employee->contracts->isEmpty())
                    <p class="text-sm text-[var(--text-soft)]">Sem contratos.</p>
                @else
                    <ul class="text-sm space-y-2">
                        @foreach($employee->contracts->take(5) as $c)
                            <li>{{ $c->type }} — {{ optional($c->start_date)->format('Y-m-d') }} @if($c->end_date) a {{ optional($c->end_date)->format('Y-m-d') }} @endif {{ $c->active ? '(ativo)' : '(inativo)' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-base">Horários/Escalas</h3>
                    <a href="{{ route('ui.hr.employees.schedules.index', $employee) }}" class="text-primary text-xs font-semibold">Gerir</a>
                </div>
                @if($employee->schedules->isEmpty())
                    <p class="text-sm text-[var(--text-soft)]">Sem horários definidos.</p>
                @else
                    <p class="text-sm text-[var(--text-soft)]">{{ $employee->schedules->count() }} horário(s) definido(s).</p>
                @endif
            </div>
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <h3 class="font-bold text-base mb-3">Assiduidade recente</h3>
                @if($employee->attendanceRecords->isEmpty())
                    <p class="text-sm text-[var(--text-soft)]">Sem registos.</p>
                @else
                    <ul class="text-sm space-y-2">
                        @foreach($employee->attendanceRecords as $r)
                            <li>{{ optional($r->date)->format('Y-m-d') }} — {{ $r->status }} @if($r->check_in)({{ $r->check_in->format('H:i') }}@if($r->check_out)-{{ $r->check_out->format('H:i') }}@endif)@endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <h3 class="font-bold text-base mb-3">Pedidos de ausência/férias</h3>
                @if($employee->leaveRequests->isEmpty())
                    <p class="text-sm text-[var(--text-soft)]">Sem pedidos.</p>
                @else
                    <ul class="text-sm space-y-2">
                        @foreach($employee->leaveRequests as $lr)
                            <li>{{ optional($lr->start_date)->format('Y-m-d') }} a {{ optional($lr->end_date)->format('Y-m-d') }} — {{ $lr->status }} ({{ $lr->days }} dias)</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
