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
        $perPage = in_array((int) request()->query('per_page', $perPage), [10, 20, 50, 100], true)
            ? (int) request()->query('per_page', $perPage) : $perPage;
        $sortBy = in_array(request()->query('sort_by'), ['id', 'name', 'username', 'phone'], true)
            ? request()->query('sort_by') : 'created_at';
        $sortOrder = request()->query('sort_order') === 'asc' ? 'asc' : 'desc';

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

            ->orderBy($sortBy, $sortOrder)

            ->paginate($perPage)

            ->withQueryString();
    }
}
