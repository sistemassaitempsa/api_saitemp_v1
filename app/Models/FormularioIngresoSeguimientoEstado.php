<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FormularioIngresoSeguimientoEstado extends Model
{
    use HasFactory;

    protected $table = 'usr_app_formulario_ingreso_seguimiento_estado';

    public function fromDateTime($value)
    {
        return Carbon::parse(parent::fromDateTime($value))->format('Y-d-m H:i:s');
    }
}
