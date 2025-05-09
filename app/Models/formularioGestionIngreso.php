<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class formularioGestionIngreso extends Model
{
    use HasFactory;

    protected $table = 'usr_app_formulario_ingreso';
    protected $dateFormat = 'd-m-Y H:i:s';

    /* public function fromDateTime($value)
    {
        return Carbon::parse(parent::fromDateTime($value))->format('Y-d-m');
    } */

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($registro) {

            if (!$registro->numero_radicado) {
                // Obtener el año actual
                $ano_actual = date('Y');

                // Obtener el último registro creado en el año actual
                $ultimo_registro_ano_actual = self::where('numero_radicado', 'like', '%' . $ano_actual . '%')
                    ->orderBy('id', 'desc')
                    ->first();

                // Generar el nuevo número de registro
                if ($ultimo_registro_ano_actual) {
                    $ultimo_numero = explode('-', $ultimo_registro_ano_actual->numero_radicado)[0];
                    $nuevo_numero = str_pad((int)$ultimo_numero + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    // $nuevo_numero = '0001';
                    $nuevo_numero = str_pad(1, 8, '0', STR_PAD_LEFT);
                }

                // Establecer el nuevo número de registro
                $registro->numero_radicado = $nuevo_numero . '-' . $ano_actual;

                $registro->created_at = now()->format('Y-m-d H:i:s.u');
            }
        });
    }
}