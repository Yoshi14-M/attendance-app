<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalBreak extends Model
{
    use HasFactory;

    /**
     * 一括代入の許可項目（ホワイトリスト）
     */
    protected $fillable = [
        'application_id',
        'break_in',
        'break_out',
    ];

    /**
     * 複数の修正案が１つの申請に属する（多対１）
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
