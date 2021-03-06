<?php

namespace App\Models\Alumns;

use Illuminate\Database\Eloquent\Model;

class Debit extends Model
{

    protected $table = "debit";

    protected $fillable = [
    	'id',
        'debit_type_id',
        'description',
        'amount',
        'admin_id',
        'id_alumno',
        'status',
        'created_at',
        'updated_at',
        'enrollment',
        'alumn_name',
        'alumn_last_name',
        'alumn_second_last_name'
    ];
    
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    protected $with = ['admin', 'debitType', 'Alumn'];

    public function admin() {
        return $this->belongsTo('\App\Models\AdminUsers\AdminUser', "admin_id", "id");
    }

    public function debitType() {
        return $this->belongsTo('\App\Models\Alumns\DebitType', "debit_type_id", "id");
    }

    public function Alumn() {
        return $this->belongsTo('\App\Models\Sicoes\Alumno', "id_alumno", "AlumnoId");
    }

    public function getDebit() {
        return User::find($this->id_alumno);
    }
}
