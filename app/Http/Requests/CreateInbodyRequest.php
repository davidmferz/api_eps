<?php

namespace API_EPS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateInbodyRequest extends FormRequest
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
            'persona'           => 'required|integer|min:1',
            'rcc'               => 'nullable|numeric|min:0.01',
            'pgc'               => 'nullable|numeric|min:0.01',
            'imc'               => 'nullable|numeric|min:0.01',
            'mme'               => 'nullable|numeric|min:0.01',
            'mcg'               => 'nullable|numeric|min:0.01',
            'act'               => 'nullable|numeric|min:0.01',
            'minerales'         => 'nullable|numeric|min:0.01',
            'proteina'          => 'nullable|numeric|min:0.01',
            'estatura'          => 'required|numeric|min:0.01',
            'peso'              => 'required|numeric|min:0.01',
            'numComidas'        => 'required|integer|min:3',
            'tipoCuerpo'        => 'required|string|min:1',
            'fcresp'            => 'nullable|numeric|min:0.01',
            'idPersonaEmpleado' => 'required|integer',
        ];
    }

}
