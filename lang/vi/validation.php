<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute phải được chấp nhận.',
    'accepted_if' => ':attribute phải được chấp nhận khi :other là :value.',
    'active_url' => ':attribute phải là một URL hợp lệ.',
    'after' => ':attribute phải là một ngày sau :date.',
    'after_or_equal' => ':attribute phải là một ngày sau hoặc bằng :date.',
    'alpha' => ':attribute chỉ được chứa các chữ cái.',
    'alpha_dash' => ':attribute chỉ được chứa các chữ cái, số, gạch ngang và gạch dưới.',
    'alpha_num' => ':attribute chỉ được chứa các chữ cái và số.',
    'any_of' => ':attribute không hợp lệ.',
    'array' => ':attribute phải là một mảng.',
    'ascii' => ':attribute chỉ được chứa các ký tự alphanumERIC đơn byte và ký hiệu.',
    'before' => ':attribute phải là một ngày trước :date.',
    'before_or_equal' => ':attribute phải là một ngày trước hoặc bằng :date.',
    'between' => [
        'array' => ':attribute phải có từ :min đến :max mục.',
        'file' => ':attribute phải từ :min đến :max kilobytes.',
        'numeric' => ':attribute phải từ :min đến :max.',
        'string' => ':attribute phải từ :min đến :max ký tự.',
    ],
    'boolean' => ':attribute phải là true hoặc false.',
    'can' => ':attribute chứa giá trị không được ủy quyền.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'contains' => ':attribute thiếu giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':attribute phải là một ngày hợp lệ.',
    'date_equals' => ':attribute phải là một ngày bằng :date.',
    'date_format' => ':attribute phải đúng định dạng :format.',
    'decimal' => ':attribute phải có :decimal chữ số thập phân.',
    'declined' => ':attribute phải là một giá trị bị từ chối.',
    'declined_if' => ':attribute phải là một giá trị bị từ chối khi :other là :value.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải là :digits chữ số.',
    'digits_between' => ':attribute phải có từ :min đến :max chữ số.',
    'dimensions' => ':attribute có kích thước hình ảnh không hợp lệ.',
    'distinct' => ':attribute có giá trị trùng lặp.',
    'doesnt_contain' => ':attribute không được chứa bất kỳ giá trị nào trong số sau: :values.',
    'doesnt_end_with' => ':attribute không được kết thúc bằng bất kỳ giá trị nào trong số sau: :values.',
    'doesnt_start_with' => ':attribute không được bắt đầu bằng bất kỳ giá trị nào trong số sau: :values.',
    'email' => ':attribute phải là một địa chỉ email hợp lệ.',
    'encoding' => ':attribute phải được mã hóa trong :encoding.',
    'ends_with' => ':attribute phải kết thúc bằng một trong các giá trị sau: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'extensions' => ':attribute phải có một trong các phần mở rộng sau: :values.',
    'file' => ':attribute phải là một tệp tin.',
    'filled' => ':attribute phải có một giá trị.',
    'gt' => [
        'array' => ':attribute phải có nhiều hơn :value mục.',
        'file' => ':attribute phải lớn hơn :value kilobytes.',
        'numeric' => ':attribute phải lớn hơn :value.',
        'string' => ':attribute phải lớn hơn :value ký tự.',
    ],
    'gte' => [
        'array' => ':attribute phải có :value mục hoặc nhiều hơn.',
        'file' => ':attribute phải lớn hơn hoặc bằng :value kilobytes.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :value.',
        'string' => ':attribute phải lớn hơn hoặc bằng :value ký tự.',
    ],
    'hex_color' => ':attribute phải là một màu thập lục phân hợp lệ.',
    'image' => ':attribute phải là một hình ảnh.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field must exist in :other.',
    'in_array_keys' => 'The :attribute field must contain at least one of the following keys: :values.',
    'integer' => ':attribute phải là một số nguyên.',
    'ip' => ':attribute phải là một địa chỉ IP hợp lệ.',
    'ipv4' => ':attribute phải là một địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':attribute phải là một địa chỉ IPv6 hợp lệ.',
    'json' => ':attribute phải là một chuỗi JSON hợp lệ.',
    'list' => ':attribute phải là một danh sách.',
    'lowercase' => ':attribute phải là chữ thường.',
    'lt' => [
        'array' => ':attribute phải có ít hơn :value mục.',
        'file' => ':attribute phải nhỏ hơn :value kilobytes.',
        'numeric' => ':attribute phải nhỏ hơn :value.',
        'string' => ':attribute phải nhỏ hơn :value ký tự.',
    ],
    'lte' => [
        'array' => ':attribute không được có nhiều hơn :value mục.',
        'file' => ':attribute phải nhỏ hơn hoặc bằng :value kilobytes.',
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => ':attribute phải nhỏ hơn hoặc bằng :value ký tự.',
    ],
    'mac_address' => ':attribute phải là một địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => ':attribute không được có nhiều hơn :max mục.',
        'file' => ':attribute không được lớn hơn :max kilobytes.',
        'numeric' => ':attribute không được lớn hơn :max.',
        'string' => ':attribute không được lớn hơn :max ký tự.',
    ],
    'max_digits' => ':attribute không được có nhiều hơn :max chữ số.',
    'mimes' => ':attribute phải là một tệp có loại: :values.',
    'mimetypes' => ':attribute phải là một tệp có loại: :values.',
    'min' => [
        'array' => ':attribute phải có ít nhất :min mục.',
        'file' => ':attribute phải có ít nhất :min kilobytes.',
        'numeric' => ':attribute phải là một số không nhỏ hơn :min.',
        'string' => ':attribute phải là một chuỗi không ngắn hơn :min ký tự.',
    ],
    'min_digits' => ':attribute phải có ít nhất :min chữ số.',
    'missing' => ':attribute phải vắng mặt.',
    'missing_if' => ':attribute phải vắng mặt khi :other là :value.',
    'missing_unless' => ':attribute phải vắng mặt trừ khi :other là :value.',
    'missing_with' => ':attribute phải vắng mặt khi :values là present.',
    'missing_with_all' => ':attribute phải vắng mặt khi :values là present.',
    'multiple_of' => ':attribute phải là bội số của :value.',
    'not_in' => ':attribute đã chọn không hợp lệ.',
    'not_regex' => ':attribute định dạng không hợp lệ.',
    'numeric' => ':attribute phải là một số.',
    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    'present' => ':attribute phải được hiện thị.',
    'present_if' => ':attribute phải được hiện thị khi :other là :value.',
    'present_unless' => ':attribute phải được hiện thị trừ khi :other là :value.',
    'present_with' => ':attribute phải được hiện thị khi :values là present.',
    'present_with_all' => ':attribute phải được hiện thị khi :values là present.',
    'prohibited' => ':attribute bị cấm.',
    'prohibited_if' => ':attribute bị cấm khi :other là :value.',
    'prohibited_if_accepted' => ':attribute bị cấm khi :other là đã chấp nhận.',
    'prohibited_if_declined' => ':attribute bị cấm khi :other là đã từ chối.',
    'prohibited_unless' => ':attribute bị cấm trừ khi :other là trong :values.',
    'prohibits' => ':attribute cấm :other khỏi being present.',
    'regex' => ':attribute định dạng không hợp lệ.',
    'required' => ':attribute là bắt buộc.',
    'required_array_keys' => ':attribute phải chứa các mục cho: :values.',
    'required_if' => ':attribute là bắt buộc khi :other là :value.',
    'required_if_accepted' => ':attribute là bắt buộc khi :other là đã chấp nhận.',
    'required_if_declined' => ':attribute là bắt buộc khi :other là đã từ chối.',
    'required_unless' => ':attribute là bắt buộc trừ khi :other là trong :values.',
    'required_with' => ':attribute là bắt buộc khi :values là present.',
    'required_with_all' => ':attribute là bắt buộc khi :values là present.',
    'required_without' => ':attribute là bắt buộc khi :values không present.',
    'required_without_all' => ':attribute là bắt buộc khi none of :values are present.',
    'same' => ':attribute phải khớp với :other.',
    'size' => [
        'array' => ':attribute phải chứa :size mục.',
        'file' => ':attribute phải là :size kilobytes.',
        'numeric' => ':attribute phải là :size.',
        'string' => ':attribute phải là :size ký tự.',
    ],
    'starts_with' => ':attribute phải bắt đầu bằng một trong những giá trị sau: :values.',
    'string' => ':attribute phải là chuỗi.',
    'timezone' => ':attribute phải là múi giờ hợp lệ.',
    'unique' => ':attribute đã được sử dụng.',
    'uploaded' => ':attribute tải lên thất bại.',
    'uppercase' => ':attribute phải là chữ hoa.',
    'url' => ':attribute phải là URL hợp lệ.',
    'ulid' => ':attribute phải là ULID hợp lệ.',
    'uuid' => ':attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'Tên thương hiệu',
        'category_id' => 'Danh mục',
    ],

];
