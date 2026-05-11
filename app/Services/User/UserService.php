<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Base\BaseService;
use App\Repositories\User\UserRepository;
use Illuminate\Database\Eloquent\Model;

class UserService extends BaseService
{
    public function __construct(
        UserRepository $repository
    ) {
        parent::__construct($repository);
    }

    public function update(Model $model, array $data): Model 
    {

        if (! empty($data['password'])) {

            $data['password'] = bcrypt($data['password']);

        } else {

            unset($data['password']);
        }

        $model = $this->repository->update(
            $model,
            $data
        );

        if (isset($data['roles'])) {

            $model->syncRoles($data['roles']);
        }

        return $model;
    }
    

    public function create(array $data): User
    {
        $user = $this->repository->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => bcrypt($data['password']),
        ]);

        $user->syncRoles($data['role']);

        return $user;
    }
}