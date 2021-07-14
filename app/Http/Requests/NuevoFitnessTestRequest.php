<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NuevoFitnessTestRequest extends FormRequest
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
            'idPersona'          => 'required',
            'idPersonaEmpleado'  => 'required',
            'peso'               => 'required',
            'tiempo'             => '',
            'distanciaMetros'    => '',
            'frecuenciaCardiaca' => '',
            'edad'               => 'required|integer|min:16|max:110',
            'rockportEncuesta'   => '',
            'sexo'               => 'required',
            'flexiones'          => 'required',
            'flexibilidad'       => 'required',
            'abdominales'        => 'required',
        ];
    }
    public function messages()
    {
        return [

        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        if ($validator->fails()) {
            $response = mensaje([$validator->errors()->first(), 422]);
            throw new HttpResponseException(response()->json($response, $response['code']));
        }
    }
}
