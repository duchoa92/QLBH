<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\Base\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator {

        return $this->model
            ->query()

            ->with('roles')

            ->when(
                request('search'),
                function ($query) {

                    $query->where(function ($q) {

                        $q->where(
                            'name',
                            'like',
                            '%' . request('search') . '%'
                        )

                        ->orWhere(
                            'username',
                            'like',
                            '%' . request('search') . '%'
                        )

                        ->orWhere(
                            'phone',
                            'like',
                            '%' . request('search') . '%'
                        );
                    });
                }
            )

            ->latest()

            ->paginate($perPage)

            ->withQueryString();
    }
}