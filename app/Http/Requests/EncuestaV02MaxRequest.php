<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EncuestaV02MaxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'idPersona' => 'required',
            'idPersonaEmpleado' => 'required',
            'idCatPushUp' => 'required',
            'idCatAbdominales' => 'required',
            'idCatFlexibilidad' => 'required',
            'vo2Max' => 'required',
            'nombrePruebaVo2Max' => 'required'
        ];
    }
}
