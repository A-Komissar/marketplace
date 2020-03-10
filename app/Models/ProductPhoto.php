<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPhoto extends Model
{

    protected $table = 'product_photos';

    protected $fillable = ['id', 'product_id', 'photo', 'main'];

    public $timestamps = false;

    public function save(array $options = []) {
        $product = Product::where('id', $this->product_id)->where('approved', 1)->first();
        if ($product) {
            $change = new ProductChange();
            $change->product_id = $this->product_id;
            $change->type = 'photo';
            $change->key = 'photo';
            $change->after = $this->photo;
            $change->save();
        }
        return parent::save($options);
    }

    public function delete()
    {
        $product = Product::where('id', $this->product_id)->where('approved', 1)->first();
        if ($product) {
            $change = new ProductChange();
            $change->product_id = $this->product_id;
            $change->type = 'photo';
            $change->key = 'photo';
            $change->before = $this->photo;
            $change->save();
        }
        return parent::delete();
    }

}
