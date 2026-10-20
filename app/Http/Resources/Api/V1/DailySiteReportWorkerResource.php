<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailySiteReportWorkerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['worker_type' => $this->worker_type, 'workers_present' => $this->workers_present];
    }
}
