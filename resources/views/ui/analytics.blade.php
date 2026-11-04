@extends('ui.layout')

@section('page_key', 'analytics')

@section('content')
<x-ui.partials.page-header
    :title="__('common.Centro Analítico')"
    :subtitle="__('common.Monitorização operacional da plataforma de gestão de avarias.')"
>
    <x-slot:actions>
        <x-ui.analytics.export-actions />
    </x-slot:actions>

    <div class="space-y-8">
        <x-ui.analytics.hero />

        <section>
            <x-ui.analytics.section-heading
                :eyebrow="__('dashboard.Indicadores Operacionais')"
                :title="__('common.Resumo da Plataforma')"
                :description="__('messages.Indicadores principais da atividade do sistema. Os valores são atualizados automaticamente a partir da base de dados.')"
            >
                <x-slot:aside>
                    <x-ui.analytics.aside-card :label="__('common.Atualização')" :value="__('common.Tempo Real')" class="px-5 py-3" />
                </x-slot:aside>
            </x-ui.analytics.section-heading>

            <div id="kpiPanel" class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                @for ($i = 0; $i < 4; $i++)
                    <article class="overflow-hidden rounded-3xl border border-[var(--border)] bg-[var(--surface)]">
                        <div class="p-6">
                            <div class="h-4 w-28 animate-pulse rounded-full bg-[var(--surface-2)]"></div>
                            <div class="mt-6 h-10 w-24 animate-pulse rounded-xl bg-[var(--surface-2)]"></div>
                            <div class="mt-6 h-3 w-40 animate-pulse rounded-full bg-[var(--surface-2)]"></div>
                            <div class="mt-8 flex items-center justify-between">
                                <div class="h-8 w-20 animate-pulse rounded-full bg-[var(--surface-2)]"></div>
                                <div class="h-8 w-8 animate-pulse rounded-2xl bg-[var(--surface-2)]"></div>
                            </div>
                        </div>
                    </article>
                @endfor
            </div>
        </section>

        <section class="grid gap-8 2xl:grid-cols-[1.2fr_0.8fr]">
            <x-ui.analytics.chart-card
                :eyebrow="__('common.Desempenho')"
                :title="__('tickets.Tickets por Estado')"
                :description="__('tickets.Distribuição atual das ocorrências de manutenção registadas na plataforma.')"
                canvas_id="statusChart"
                height_class="h-[420px]"
            >
                <x-slot:aside>
                    <x-ui.analytics.aside-card :label="__('common.Atualização')" :value="__('common.Em tempo real')" />
                </x-slot:aside>
            </x-ui.analytics.chart-card>

            <x-ui.analytics.equipment-distribution-card />
        </section>

        <section class="grid gap-8 xl:grid-cols-2">
            <x-ui.analytics.chart-card
                :eyebrow="__('common.Evolução')"
                :title="__('tickets.Tickets nos Últimos Meses')"
                :description="__('tickets.Comparação entre tickets abertos, em curso e fechados.')"
                canvas_id="trendChart"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('common.Custos')"
                :title="__('common.Custo Mensal')"
                :description="__('common.Despesas acumuladas por intervenção concluída.')"
                canvas_id="costChart"
            />
        </section>

        <section class="grid gap-8 xl:grid-cols-2">
            <x-ui.analytics.chart-card
                :eyebrow="__('common.SLA')"
                :title="__('common.SLA Mensal')"
                :description="__('common.Percentagem de intervenções concluídas dentro do tempo previsto.')"
                canvas_id="slaChart"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('common.MTTR')"
                :title="__('common.Tempo Médio de Resolução')"
                :description="__('tickets.Evolução do tempo médio (em minutos) para resolver cada ocorrência.')"
                canvas_id="mttrChart"
            />
        </section>

        <section class="grid gap-8 2xl:grid-cols-2">
            <x-ui.analytics.chart-card
                :eyebrow="__('common.Distribuição')"
                :title="__('tickets.Tickets por Sala')"
                :description="__('tickets.Salas com maior número de ocorrências registadas.')"
                canvas_id="roomChart"
                height_class="h-[360px]"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('common.Custos')"
                :title="__('equipment.Custo por Equipamento')"
                :description="__('equipment.Custo acumulado de intervenções atribuídas a cada equipamento.')"
                canvas_id="costByEquipmentChart"
                height_class="h-[360px]"
            />
        </section>

        <section class="grid gap-8 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.analytics.chart-card
                :eyebrow="__('common.Prioridade')"
                :title="__('common.Urgência')"
                :description="__('tickets.Ocorrências marcadas como urgentes.')"
                canvas_id="urgencyChart"
                height_class="h-[300px]"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('common.Orçamento')"
                :title="__('common.Estado do Orçamento')"
                :description="__('common.Distribuição das decisões de orçamento.')"
                canvas_id="budgetChart"
                height_class="h-[300px]"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('common.Origem')"
                :title="__('tickets.Origem dos Tickets')"
                :description="__('tickets.Canal pelo qual as ocorrências foram registadas.')"
                canvas_id="sourceChart"
                height_class="h-[300px]"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('common.Notificações')"
                :title="__('common.Notificações por Tipo')"
                :description="__('messages.Distribuição dos alertas gerados pelo sistema.')"
                canvas_id="notificationsChart"
                height_class="h-[300px]"
            />
        </section>

        <section class="grid gap-8 2xl:grid-cols-2">
            <x-ui.analytics.chart-card
                :eyebrow="__('stock.Stock')"
                :title="__('stock.Movimentos de Stock')"
                :description="__('stock.Entradas e saídas de peças nos últimos meses.')"
                canvas_id="stockMonthlyChart"
            />

            <x-ui.analytics.chart-card
                :eyebrow="__('stock.Stock')"
                :title="__('stock.Peças com Stock Baixo')"
                :description="__('stock.Peças cujo stock atual está no limite ou abaixo do mínimo.')"
                canvas_id="lowStockChart"
                height_class="h-[360px]"
            />
        </section>

        <section>
            <x-ui.analytics.chart-card
                :eyebrow="__('common.Equipa')"
                :title="__('common.Utilizadores por Perfil')"
                :description="__('equipment.Distribuição dos utilizadores ativos por perfil de acesso.')"
                canvas_id="usersByRoleChart"
                height_class="h-[320px]"
            />
        </section>

        <section>
            <x-ui.analytics.section-heading
                :eyebrow="__('messages.Estado do Sistema')"
                :title="__('dashboard.Indicadores Operacionais')"
                :description="__('common.Tempo médio de resolução, SLA, disponibilidade e tempo de espera até a atribuição.')"
            />

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.analytics.metric-card :eyebrow="__('common.MTTR')" value_id="metricMttr" :description="__('tickets.Tempo médio necessário para resolver uma ocorrência.')" icon_bg_class="bg-success/10">
                    <svg class="h-7 w-7 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" /></svg>
                </x-ui.analytics.metric-card>
                <x-ui.analytics.metric-card :eyebrow="__('common.Espera')" value_id="metricWaiting" :description="__('common.Tempo médio até um técnico assumir a intervenção.')" icon_bg_class="bg-info/10">
                    <svg class="h-7 w-7 text-info" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" /></svg>
                </x-ui.analytics.metric-card>
                <x-ui.analytics.metric-card :eyebrow="__('common.Disponibilidade')" value_id="metricAvailability" default_value="99.9%" :description="__('common.Disponibilidade estimada da plataforma.')" icon_bg_class="bg-purple-500/10">
                    <svg class="h-7 w-7 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </x-ui.analytics.metric-card>
                <x-ui.analytics.metric-card :eyebrow="__('common.SLA')" value_id="metricSla" :description="__('common.Percentagem de intervenções dentro do tempo previsto.')" icon_bg_class="bg-warning/10">
                    <svg class="h-7 w-7 text-warning" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" /></svg>
                </x-ui.analytics.metric-card>
            </div>
        </section>

        <section>
            <x-ui.analytics.section-heading
                :eyebrow="__('common.Histórico')"
                :title="__('dashboard.Atividade Recente')"
                :description="__('dashboard.Últimas ações registadas na plataforma para acompanhar rapidamente a atividade operacional.')"
            >
                <x-slot:aside>
                    <x-ui.analytics.aside-pill :label="__('common.Últimas 24 horas')" />
                </x-slot:aside>
            </x-ui.analytics.section-heading>

            <x-ui.analytics.activity-timeline-card />
        </section>

        <section>
            <x-ui.analytics.section-heading
                :eyebrow="__('analytics_data.Estatísticas')"
                :title="__('common.Resumo Operacional')"
                :description="__('equipment.Consulte rapidamente os equipamentos mais afetados, as salas mais recorrentes e os técnicos mais ativos.')"
            />

            <div class="grid gap-8 xl:grid-cols-3">
                <x-ui.analytics.list-card :title="__('equipment.Equipamentos com Mais Avarias')" :description="__('equipment.Ranking dos equipamentos mais intervencionados.')" container_id="topEquipments" />
                <x-ui.analytics.list-card :title="__('room.Salas Mais Afetadas')" :description="__('tickets.Locais com maior número de ocorrências.')" container_id="topRooms" />
                <x-ui.analytics.list-card :title="__('equipment.Técnicos Mais Ativos')" :description="__('common.Colaboradores com maior número de intervenções.')" container_id="topTechnicians" />
            </div>
        </section>

        <div id="analyticsMessage" class="hidden rounded-2xl border border-[var(--border)] px-5 py-4 text-sm font-medium text-(--text-muted)"></div>
    </div>
</x-ui.partials.page-header>
@endsection
