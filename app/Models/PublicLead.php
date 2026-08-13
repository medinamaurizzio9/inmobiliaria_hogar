<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicLead extends Model
{
    protected $fillable = ['nombre', 'celular', 'email', 'urbanizacion_id', 'lote_id', 'mensaje', 'origen', 'estado'];
}
