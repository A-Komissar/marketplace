<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'categories';

    protected $fillable = ['id', 'market_id', 'category_id', 'parent_id', 'name'];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'category_id')->select(array('id', 'market_id', 'category_id', 'name'));
    }

    public function parent(){
        return $this->belongsTo( self::class, 'category_id', 'parent_id')->select(array('id', 'market_id', 'category_id', 'name'));
    }

    public function commission(){
        return $this->hasOne( 'App\Models\Commission', 'category_id', 'id')->select(array('id', 'market_id', 'value'));
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
