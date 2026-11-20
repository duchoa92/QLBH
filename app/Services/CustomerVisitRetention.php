<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerVisit;

/** Deletes expired visit records and their private snapshots as one policy. */
class CustomerVisitRetention
{
    public function __construct(private readonly FaceImageStorage $images)
    {
    }

    public function days(): int
    {
        return min(365, max(1, (int) setting('face_visit_retention_days', 30)));
    }

    public function prune(): int
    {
        $cutoff = now()->subDays($this->days());
        $deleted = 0;

        CustomerVisit::query()
            ->where('last_seen_at', '<', $cutoff)
            ->select(['id', 'image_path'])
            ->orderBy('id')
            ->chunkById(200, function ($visits) use (&$deleted): void {
                $ids = $visits->pluck('id')->all();
                $paths = $visits->pluck('image_path')->all();
                $deleted += CustomerVisit::query()->whereIn('id', $ids)->delete();
                $this->images->deleteMany($paths);
            });

        return $deleted;
    }
}
