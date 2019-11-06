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
            'rcc'               => 'required|numeric|min:0.01',
            'pgc'               => 'required|numeric|min:0.01',
            'imc'               => 'required|numeric|min:0.01',
            'mme'               => 'required|numeric|min:0.01',
            'mcg'               => 'required|numeric|min:0.01',
            'act'               => 'required|numeric|min:0.01',
            'minerales'         => 'required|numeric|min:0.01',
            'proteina'          => 'required|numeric|min:0.01',
            'estatura'          => 'required|numeric|min:0.01',
            'peso'              => 'required|numeric|min:0.01',
            'fcresp'            => 'required|numeric|min:0.01',
            'idPersonaEmpleado' => 'nullable|integer',
        ];
    }

}
