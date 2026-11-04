<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Supplier;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StockUiController extends Controller
{
    /**
     * Stock module dashboard.
     */
    public function dashboard(Request $request): View
    {
        $this->authorize('viewAny', Part::class);

        $user = $request->user();

        return view('ui.stock.dashboard', ['user' => $user]);
    }

    /**
     * Parts listing.
     */
    public function parts(Request $request): View
    {
        $this->authorize('viewAny', Part::class);

        $user = $request->user();

        return view('ui.stock.parts', [
            'user' => $user,
            'categories' => PartCategory::query()->active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Part creation form.
     */
    public function partCreate(Request $request): View
    {
        $user = $request->user();

        return view('ui.stock.parts.create', [
            'user' => $user,
            'categories' => PartCategory::query()->active()->orderBy('name')->get(),
            'taxRates' => TaxRate::query()->active()->orderBy('percent')->get(),
        ]);
    }

    /**
     * Part detail view.
     */
    public function partShow(Request $request, Part $part): View
    {
        $this->authorize('view', $part);

        $user = $request->user();

        return view('ui.stock.parts.show', [
            'user' => $user,
            'part' => $part->load(['category', 'taxRate', 'suppliers']),
        ]);
    }

    /**
     * Part edit form.
     */
    public function partEdit(Request $request, Part $part): View
    {
        $user = $request->user();

        return view('ui.stock.parts.edit', [
            'user' => $user,
            'part' => $part->load(['category', 'taxRate', 'suppliers']),
            'categories' => PartCategory::query()->active()->orderBy('name')->get(),
            'taxRates' => TaxRate::query()->active()->orderBy('percent')->get(),
        ]);
    }

    /**
     * Suppliers listing.
     */
    public function suppliers(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $user = $request->user();

        return view('ui.stock.suppliers', ['user' => $user]);
    }

    /**
     * Supplier creation form.
     */
    public function supplierCreate(Request $request): View
    {
        $user = $request->user();

        return view('ui.stock.suppliers.create', ['user' => $user]);
    }

    /**
     * Supplier edit form.
     */
    public function supplierEdit(Request $request, Supplier $supplier): View
    {
        $user = $request->user();

        return view('ui.stock.suppliers.edit', [
            'user' => $user,
            'supplier' => $supplier,
        ]);
    }

    /**
     * Stock movements listing.
     */
    public function movements(Request $request): View
    {
        $this->authorize('viewAny', Part::class);

        $user = $request->user();

        return view('ui.stock.movements', [
            'user' => $user,
            'parts' => Part::query()->active()->orderBy('name')->get(),
        ]);
    }

    /**
     * VAT rates management (admin).
     */
    public function taxRates(Request $request): View
    {
        $user = $request->user();

        return view('ui.stock.tax-rates', [
            'user' => $user,
            'taxRates' => TaxRate::query()->orderBy('percent')->get(),
        ]);
    }

    /**
     * Part categories management (admin).
     */
    public function categories(Request $request): View
    {
        $user = $request->user();

        return view('ui.stock.categories', [
            'user' => $user,
            'categories' => PartCategory::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Preventive maintenance plans.
     */
    public function plans(Request $request): View
    {
        $user = $request->user();

        return view('ui.stock.plans', [
            'user' => $user,
            'equipments' => Equipment::query()->active()->orderBy('name')->get(),
            'parts' => Part::query()->active()->orderBy('name')->get(),
        ]);
    }
}
