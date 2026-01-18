<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'required' => ':attribute không được để trống.',
    'confirmed' => ':attribute xác nhận không khớp.',
    'min' => [
        'string' => ':attribute phải có ít nhất :min ký tự.',
    ],
    'email' => ':attribute phải là địa chỉ email hợp lệ.',
    'unique' => ':attribute đã tồn tại.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'tên',
        'email' => 'email',
        'password' => 'mật khẩu',
        'username' => 'tên đăng nhập',
    ],

];
