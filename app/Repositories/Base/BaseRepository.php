<?php

namespace App\Repositories\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator {

        return $this->model
            ->query()

            ->withTrashed()

            ->when(
                request('search'),
                function ($query) {

                    $query->where(
                        'name',
                        'like',
                        '%' . request('search') . '%'
                    );
                }
            )

            ->latest()

            ->paginate($perPage)

            ->withQueryString();
    }

    public function create(array $data): Model
    {
        return $this->model
            ->create($data);
    }

    public function findById(int $id): Model
    {
        return $this->model
            ->findOrFail($id);
    }

    public function update(
        Model $model,
        array $data
    ): Model {

        $model->update($data);

        return $model;
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    public function restore(int $id): bool
    {
        $model = $this->model
            ->withTrashed()
            ->findOrFail($id);

        return $model->restore();
    }
}