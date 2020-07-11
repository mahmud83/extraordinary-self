<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JawabanPeserta extends Model
{
    /**
     * [$guarded description]
     * @var array
     */
    protected $guarded = [];

    /**
     * [$hidden description]
     * @var [type]
     */
    protected $hidden = [
        'created_at','udpated_at'
    ];

    /**
     * [$appends description]
     * @var [type]
     */
    protected $appends = [
        'similiar'
    ];

    /**
     * [pertanyaan description]
     * @return [type] [description]
     */
    public function pertanyaan()
    {
        return $this->belongsTo(Soal::class,'soal_id','id');
    }

    /**
     * [getSimiliarAttribute description]
     * @return [type] [description]
     */
    public function getSimiliarAttribute()
    {
        $text = Soal::find($this->soal_id);
        if($this->esay != null && $text->tipe_soal == 2) {
            $rujukan = strip_tags($text->rujukan);

            $jawab = strip_tags($this->esay);
            similar_text($rujukan, $jawab, $percent);

            return $percent;
        }
    }
}
