<?php

namespace App\Services\Base;

use Illuminate\Support\Str;

class BaseService
{
    protected $repository;

    public function create(array $data)
    {
        if (isset($data['name'])) {

            $data['slug'] =
                Str::slug($data['name']);
        }

        return $this->repository
            ->create($data);
    }

    public function update(
        $model,
        array $data
    ) {

        if (isset($data['name'])) {

            $data['slug'] =
                Str::slug($data['name']);
        }

        return $this->repository
            ->update(
                $model,
                $data
            );
    }

    public function delete($model)
    {
        return $this->repository
            ->delete($model);
    }

    public function restore($id)
    {
        return $this->repository
            ->restore($id);
    }
}