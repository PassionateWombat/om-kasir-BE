<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $hidden = [
        'created_at',
        'updated_at',
        'transaction_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
