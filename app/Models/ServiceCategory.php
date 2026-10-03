<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id')->orderBy('name');
    }

    /** Id della categoria e delle sue sottocategorie (per filtrare i servizi). */
    public function selfAndChildrenIds(): array
    {
        return $this->children()->pluck('id')->prepend($this->id)->all();
    }

    /** "Estetica > Viso" per le sottocategorie, "Estetica" per le principali. */
    public function path(): string
    {
        return $this->parent ? $this->parent->name . ' > ' . $this->name : $this->name;
    }
}
