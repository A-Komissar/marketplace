<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    protected $fillable = ['id', 'seller_id', 'brand', 'rozetka_category_id', 'rozetka_product_id', 'rozetla_product_url', 'is_active_at_rozetka',
        'prom_category_id', 'article', 'name_ru', 'description_ru', 'name_ua', 'description_ua', 'state',
        'warranty', 'country_origin', 'country_brand', 'comment', 'keywords', 'delivery_message',
        'price', 'price_old', 'price_promo', 'stock', 'disabled', 'new', 'approved', 'is_updated', 'do_not_update_price'];

    protected $not_logged_keys = ['id', 'seller_id', 'rozetka_product_id', 'rozetka_product_url', 'is_active_at_rozetka',
        'prom_category_id', 'disabled', 'new', 'approved', 'is_updated', 'do_not_update_price', 'created_at', 'updated_at'
    ]; // keep in mind you can't log changes of prom_category_id because it is changed by update() method, not by save()

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id')->select(array('id', 'name', 'company_name', 'email', 'prefix', 'use_prefix', 'balance', 'is_hidden'));
    }

    public function rozetka_category()
    {
        return $this->belongsTo('App\Models\Category', 'rozetka_category_id')->select(array('category_id', 'parent_id', 'name'));
    }

    public function characteristics()
    {
        return $this->hasMany('App\Models\ProductCharacteristic', 'product_id')->select(array('id', 'key', 'value'));
    }

    public function photos()
    {
        return $this->hasMany('App\Models\ProductPhoto', 'product_id')->select(array('id', 'photo', 'main'));
    }

    // log changes (another way to log changes is to use events and observers https://laravel.com/docs/master/eloquent#events)
    public function save(array $options = []) {
        if ($this->approved) {
            $dirty = $this->getDirty();
            if ($dirty && $this->exists) {
                $original = $this->getOriginal();
                foreach ($dirty as $key => $value) {
                    if (in_array($key, $this->not_logged_keys)) {
                        continue;
                    } else {
                        $change = new ProductChange();
                        $change->product_id = $this->id;
                        $change->type = 'property';
                        $change->key = $key;
                        $change->before = $original[$key];
                        $change->after = $value;
                        $change->save();
                        if ($key == 'rozetka_category_id') {
                            $prod_with_prom_category = Product::where('rozetka_category_id', $value)->where('prom_category_id', '<>', null)->first();
                            if ($prod_with_prom_category) {
                                $this->prom_category_id = $prod_with_prom_category->prom_category_id;
                            } else {
                                $this->prom_category_id = null;
                            }
                        }
                    }
                }
            }
        }
        parent::save($options);
    }

    public function default_save(array $options = []) {
        parent::save($options);
    }

}
