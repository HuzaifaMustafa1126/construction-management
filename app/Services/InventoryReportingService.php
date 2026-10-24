<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\User;
use App\Support\Inventory\DecimalQuantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReportingService
{
    public function allowedProjectIds(User $user, int $companyId): Collection
    {
        $query = DB::table('projects')->where('company_id', $companyId)->whereNull('deleted_at');
        if (! $user->hasRole('company_admin')) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('project_user')->whereColumn('project_user.project_id', 'projects.id')->where('project_user.user_id', $user->id));
        }

        return $query->pluck('id');
    }

    public function stockQuery(int $companyId, array $projectIds, array $filters)
    {
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        $periodCondition = $start ? ' and t.transaction_date >= ?' : '';
        $periodBindings = $start ? [$start] : [];
        $query = DB::table('project_inventories as pi')
            ->join('projects as p', 'p.id', '=', 'pi.project_id')
            ->join('materials as m', 'm.id', '=', 'pi.material_id')
            ->join('material_categories as c', 'c.id', '=', 'm.material_category_id')
            ->join('measurement_units as u', 'u.id', '=', 'm.measurement_unit_id')
            ->leftJoin('inventory_transactions as t', function ($join) use ($end) {
                $join->on('t.project_inventory_id', '=', 'pi.id');
                if ($end) {
                    $join->where('t.transaction_date', '<=', $end);
                }
            })->where('pi.company_id', $companyId)->whereIn('pi.project_id', $projectIds)->whereNull('pi.deleted_at');
        if ($material = $filters['material_id'] ?? null) {
            $query->where('pi.material_id', $material);
        }
        if ($category = $filters['material_category_id'] ?? null) {
            $query->where('m.material_category_id', $category);
        }
        $columns = ['pi.id', 'pi.project_id', 'p.name', 'pi.material_id', 'm.name', 'm.code', 'c.id', 'c.name', 'u.id', 'u.name', 'u.symbol', 'u.decimal_precision', 'pi.minimum_stock_level'];
        $query->select(['pi.id as inventory_id', 'pi.project_id', 'p.name as project_name', 'pi.material_id', 'm.name as material_name', 'm.code as material_code', 'c.id as category_id', 'c.name as category_name', 'u.id as unit_id', 'u.name as unit_name', 'u.symbol as unit_symbol', 'u.decimal_precision', 'pi.minimum_stock_level'])
            ->selectRaw("coalesce(sum(case when t.transaction_type='opening_stock' then t.quantity else 0 end),0) opening_stock")
            ->selectRaw("coalesce(sum(case when t.transaction_type='material_received' then t.quantity else 0 end),0) received")
            ->selectRaw("coalesce(sum(case when t.transaction_type='material_consumed' then t.quantity else 0 end),0) consumed")
            ->selectRaw("coalesce(sum(case when t.transaction_type='adjustment_in' then t.quantity else 0 end),0) adjustment_in")
            ->selectRaw("coalesce(sum(case when t.transaction_type='adjustment_out' then t.quantity else 0 end),0) adjustment_out")
            ->selectRaw("coalesce(sum(case when t.transaction_type='material_wastage' then t.quantity else 0 end),0) wastage")
            ->selectRaw("coalesce(sum(case when t.transaction_type='inventory_correction_in' then t.quantity else 0 end),0) correction_in")
            ->selectRaw("coalesce(sum(case when t.transaction_type='inventory_correction_out' then t.quantity else 0 end),0) correction_out")
            ->selectRaw($start ? "coalesce(sum(case when t.transaction_date < ? then case when t.direction='in' then t.quantity else -t.quantity end else 0 end),0) opening_balance" : '0 opening_balance', $start ? [$start] : [])
            ->selectRaw("coalesce(sum(case when t.direction='in'{$periodCondition} then t.quantity else 0 end),0) period_in", $periodBindings)
            ->selectRaw("coalesce(sum(case when t.direction='out'{$periodCondition} then t.quantity else 0 end),0) period_out", $periodBindings)
            ->selectRaw("coalesce(sum(case when t.direction='in' then t.quantity else -t.quantity end),0) current_stock")
            ->selectRaw('max(t.transaction_date) last_movement_date')
            ->groupBy($columns);

        return $query;
    }

    public function decorate(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $stock = (string) $row->current_stock;
            $minimum = (string) $row->minimum_stock_level;
            $row->stock_status = DecimalQuantity::compare($stock, '0') < 0 ? 'negative_stock_anomaly' : (DecimalQuantity::compare($stock, '0') === 0 ? 'out_of_stock' : (DecimalQuantity::compare($minimum, '0') > 0 && DecimalQuantity::compare($stock, $minimum) <= 0 ? 'low_stock' : 'healthy'));

            return $row;
        });
    }

    public function movements(int $companyId, array $projectIds, array $filters): Builder
    {
        $query = InventoryTransaction::query()->where('company_id', $companyId)->whereIn('project_id', $projectIds)->with(['project:id,name,code', 'creator:id,name', 'projectInventory.material.measurementUnit', 'deliveryItem.delivery', 'consumptionItem.consumption', 'stockAdjustmentItem.adjustment']);
        if ($value = $filters['start_date'] ?? null) {
            $query->whereDate('transaction_date', '>=', $value);
        }
        if ($value = $filters['end_date'] ?? null) {
            $query->whereDate('transaction_date', '<=', $value);
        }
        if ($value = $filters['material_id'] ?? null) {
            $query->where('material_id', $value);
        }
        if ($value = $filters['material_category_id'] ?? null) {
            $query->whereHas('projectInventory.material', fn ($q) => $q->where('material_category_id', $value));
        }
        if ($value = $filters['transaction_type'] ?? null) {
            $query->where('transaction_type', $value);
        }
        if ($value = $filters['direction'] ?? null) {
            $query->where('direction', $value);
        }
        if ($value = $filters['actor_id'] ?? null) {
            $query->where('created_by', $value);
        }
        if ($value = $filters['source_document'] ?? null) {
            match ($value) {
                'opening_stock' => $query->where('transaction_type', 'opening_stock'),
                'material_delivery' => $query->whereNotNull('delivery_item_id'),
                'material_consumption' => $query->whereNotNull('consumption_item_id'),
                'stock_adjustment' => $query->whereNotNull('stock_adjustment_item_id'),
            };
        }

        return $query;
    }

    public function periodTotals(int $companyId, array $projectIds, array $filters): Collection
    {
        return $this->movements($companyId, $projectIds, $filters)->toBase()->select(['material_id', 'transaction_type'])->selectRaw('sum(quantity) quantity')->groupBy('material_id', 'transaction_type')->get();
    }
}
