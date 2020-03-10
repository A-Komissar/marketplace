<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductCharacteristic extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_characteristics';

    protected $fillable = ['id', 'product_id', 'key', 'value'];

    public $timestamps = false;


    public function save(array $options = []) {
        $product = Product::where('id', $this->product_id)->where('approved', 1)->first();
        if ($product) {
            $change = new ProductChange();
            $change->product_id = $this->product_id;
            $change->type = 'characteristic';
            $change->key = $this->key;
            $change->before = $this->getOriginal('value');
            $change->after = $this->value;
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
            $change->type = 'characteristic';
            $change->key = $this->key;
            $change->before = $this->value;
            $change->after = null;
            $change->save();
        }
        return parent::delete();
    }

    public function default_save(array $options = []) {
        parent::save($options);
    }

    public function default_delete() {
        return parent::delete();
    }

}
