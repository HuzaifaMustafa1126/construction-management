<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserAccountService
{
    private const ATTRIBUTION_REFERENCES = [
        'projects' => 'created_by',
        'daily_site_reports' => 'created_by',
        'site_photos' => 'uploaded_by',
        'material_categories' => 'created_by',
        'materials' => 'created_by',
        'project_inventories' => 'created_by',
        'inventory_transactions' => 'created_by',
        'suppliers' => 'created_by',
        'material_deliveries' => 'created_by',
        'material_consumptions' => 'created_by',
        'material_stock_adjustments' => 'created_by',
        'material_requests' => 'requested_by',
        'inventory_transfers' => 'created_by',
    ];

    public function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }

    public function permanentlyDelete(User $user): void
    {
        try {
            DB::transaction(function () use ($user): void {
                $target = User::query()
                    ->whereKey($user->getKey())
                    ->where('company_id', $user->company_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->revokeAccess($target);
                DB::table('password_reset_tokens')->where('email', $target->email)->delete();
                DB::table('project_user')->where('assigned_by', $target->id)->update(['assigned_by' => null]);
                DB::table('project_user')->where('user_id', $target->id)->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $target->id)->delete();
                DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $target->id)->delete();

                foreach (self::ATTRIBUTION_REFERENCES as $tableName => $columnName) {
                    DB::table($tableName)->where($columnName, $target->id)->update([$columnName => null]);
                }

                $target->forceDelete();
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ConflictHttpException(
                    'This user cannot be permanently deleted because a required business record still depends on the account.',
                    $exception,
                );
            }

            throw $exception;
        }
    }
}
