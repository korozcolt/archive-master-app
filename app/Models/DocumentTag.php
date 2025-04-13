<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentTag extends Pivot
{
    use LogsActivity;

    protected $table = 'document_tags';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['document_id', 'tag_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
