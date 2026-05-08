<?php

namespace App\Repositories\Base;

use Illuminate\Database\Eloquent\Model;

class BaseRepository
{
    protected Model $model;

    public function paginate($perPage = 10)
    {
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

    public function create(array $data)
    {
        return $this->model
            ->create($data);
    }

    public function findById($id)
    {
        return $this->model
            ->findOrFail($id);
    }

    public function update(
        Model $model,
        array $data
    ) {

        $model->update($data);

        return $model;
    }

    public function delete(Model $model)
    {
        return $model->delete();
    }

    public function restore($id)
    {
        $model = $this->model
            ->withTrashed()
            ->findOrFail($id);

        return $model->restore();
    }
}