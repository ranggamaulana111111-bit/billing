<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function items(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->orderByDesc('id')->paginate(20);

        return view('inventory.items', compact('items'));
    }

    public function storeItem(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'port_count' => 'nullable|integer|min:0',
            'pon_port_count' => 'nullable|integer|min:0',
            'cable_type' => 'nullable|string|max:255',
            'unit' => 'required|string',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        InventoryItem::create($request->only([
            'category', 'name', 'type', 'brand', 'serial_number',
            'port_count', 'pon_port_count', 'cable_type', 'unit', 'stock', 'description',
        ]));

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function updateItem(Request $request, InventoryItem $inventoryItem)
    {
        $request->validate([
            'category' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'port_count' => 'nullable|integer|min:0',
            'pon_port_count' => 'nullable|integer|min:0',
            'cable_type' => 'nullable|string|max:255',
            'unit' => 'required|string',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $inventoryItem->update($request->only([
            'category', 'name', 'type', 'brand', 'serial_number',
            'port_count', 'pon_port_count', 'cable_type', 'unit', 'stock', 'description',
        ]));

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroyItem(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    public function masuk(Request $request)
    {
        $items = InventoryItem::orderBy('name')->get();

        $query = InventoryTransaction::with(['item', 'creator'])
            ->where('type', 'in');

        if ($request->filled('item_id')) {
            $query->where('inventory_item_id', $request->item_id);
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $transactions = $query->orderByDesc('date')->orderByDesc('id')->paginate(20);

        return view('inventory.masuk', compact('items', 'transactions'));
    }

    public function storeMasuk(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            InventoryTransaction::create([
                'inventory_item_id' => $request->inventory_item_id,
                'type' => 'in',
                'quantity' => $request->quantity,
                'date' => $request->date,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            InventoryItem::where('id', $request->inventory_item_id)
                ->increment('stock', $request->quantity);
        });

        return back()->with('success', 'Barang masuk berhasil dicatat. Stok bertambah '.$request->quantity.' pcs.');
    }

    public function keluar(Request $request)
    {
        $items = InventoryItem::orderBy('name')->get();
        $customers = Customer::allTenants()->where('status', 'active')->orderBy('name')->get();

        $query = InventoryTransaction::with(['item', 'customer', 'creator'])
            ->where('type', 'out');

        if ($request->filled('item_id')) {
            $query->where('inventory_item_id', $request->item_id);
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $transactions = $query->orderByDesc('date')->orderByDesc('id')->paginate(20);

        return view('inventory.keluar', compact('items', 'customers', 'transactions'));
    }

    public function storeKeluar(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity' => 'required|integer|min:1',
            'condition' => 'required|string|in:baik,terpakai,rusak',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $item = InventoryItem::findOrFail($request->inventory_item_id);

        if ($item->stock < $request->quantity) {
            return back()->with('error', 'Stok tidak mencukupi. Stok tersedia: '.$item->stock.' '.$item->unit);
        }

        DB::transaction(function () use ($request) {
            InventoryTransaction::create([
                'inventory_item_id' => $request->inventory_item_id,
                'type' => 'out',
                'quantity' => $request->quantity,
                'condition' => $request->condition,
                'date' => $request->date,
                'notes' => $request->notes,
                'customer_id' => $request->customer_id,
                'created_by' => auth()->id(),
            ]);

            InventoryItem::where('id', $request->inventory_item_id)
                ->decrement('stock', $request->quantity);
        });

        $label = InventoryTransaction::CONDITIONS[$request->condition];

        return back()->with('success', "Barang keluar ({$label}) berhasil dicatat. Stok berkurang {$request->quantity} {$item->unit}.");
    }

    public function laporanAset(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $items = InventoryItem::orderBy('category')->orderBy('name')->get();

        $categories = InventoryItem::CATEGORIES;

        $summary = [];
        foreach ($categories as $key => $label) {
            $catItems = $items->where('category', $key);
            $totalMasuk = InventoryTransaction::where('type', 'in')
                ->whereHas('item', fn ($q) => $q->where('category', $key))
                ->when($year, fn ($q) => $q->whereYear('date', $year))
                ->sum('quantity');

            $totalKeluar = InventoryTransaction::where('type', 'out')
                ->where('condition', 'baik')
                ->whereHas('item', fn ($q) => $q->where('category', $key))
                ->when($year, fn ($q) => $q->whereYear('date', $year))
                ->sum('quantity');

            $totalTerpakai = InventoryTransaction::where('type', 'out')
                ->where('condition', 'terpakai')
                ->whereHas('item', fn ($q) => $q->where('category', $key))
                ->when($year, fn ($q) => $q->whereYear('date', $year))
                ->sum('quantity');

            $totalRusak = InventoryTransaction::where('type', 'out')
                ->where('condition', 'rusak')
                ->whereHas('item', fn ($q) => $q->where('category', $key))
                ->when($year, fn ($q) => $q->whereYear('date', $year))
                ->sum('quantity');

            $stokSisa = $catItems->sum('stock');

            if ($totalMasuk > 0 || $totalKeluar > 0 || $totalTerpakai > 0 || $totalRusak > 0 || $stokSisa > 0) {
                $summary[$key] = [
                    'label' => $label,
                    'masuk' => $totalMasuk,
                    'keluar' => $totalKeluar,
                    'terpakai' => $totalTerpakai,
                    'rusak' => $totalRusak,
                    'sisa' => $stokSisa,
                ];
            }
        }

        $grandMasuk = collect($summary)->sum('masuk');
        $grandKeluar = collect($summary)->sum('keluar');
        $grandTerpakai = collect($summary)->sum('terpakai');
        $grandRusak = collect($summary)->sum('rusak');
        $grandSisa = collect($summary)->sum('sisa');

        return view('inventory.laporan-aset', compact(
            'summary', 'year',
            'grandMasuk', 'grandKeluar', 'grandTerpakai', 'grandRusak', 'grandSisa',
        ));
    }
}
