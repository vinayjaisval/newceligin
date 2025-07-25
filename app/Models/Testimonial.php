<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = ['title','slug','category_id', 'details', 'photo', 'source', 'views','updated_at', 'status','meta_tag','meta_description','tags'];

    protected $dates = ['created_at'];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function category()
    {
    	return $this->belongsTo('App\Models\TestimonialCategory','category_id')->withDefault();
    }

  public function blogs()
    {
        return $this->hasMany('App\Models\Testimonial','category_id');
    }
    

}
