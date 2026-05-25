<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CustomerImageService
{
    public function upload(
        Customer $customer,
        UploadedFile $file,
        bool $isPrimary = false
    ): CustomerImage {

        $path = $file->store(
            "customers/{$customer->id}",
            'public'
        );

        if ($isPrimary) {

            CustomerImage::where('customer_id', $customer->id)
                ->update([
                    'is_primary' => false
                ]);
        }

        return CustomerImage::create([
            'customer_id' => $customer->id,

            'type' => 'customer',

            'path' => $path,

            'mime_type' => $file->getMimeType(),

            'size' => $file->getSize(),

            'is_primary' => $isPrimary,
        ]);
    }

    /*
    |--------------------------------------------------
    | DELETE IMAGE
    |--------------------------------------------------
    */
    public function delete(CustomerImage $image): void
    {
        Storage::disk('public')
            ->delete($image->path);

        $image->delete();
    }

    /*
    |--------------------------------------------------
    | SET PRIMARY IMAGE
    |--------------------------------------------------
    */
    public function setPrimary(CustomerImage $image): void
    {
        CustomerImage::where(
            'customer_id',
            $image->customer_id
        )->update([
            'is_primary' => false
        ]);

        $image->update([
            'is_primary' => true
        ]);
    }
}