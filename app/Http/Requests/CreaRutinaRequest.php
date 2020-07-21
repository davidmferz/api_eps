<?php

namespace API_EPS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreaRutinaRequest extends FormRequest
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

    public function rules()
    {

        return [
            'idUn'          => 'required|integer|min:1',
            'idPersona'     => 'required|integer|min:1',
            'idRutina'      => 'required|integer|min:1',
            'fechaInicio'   => 'required|date|date_format:Y-m-d',
            'fechaFin'      => 'required|date|date_format:Y-m-d|after:fechaInicio',
            'observaciones' => 'required|string',
            'actividades'   => 'required|array|min:1',

        ];
    }
    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function attributes()
    {
        return [
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
