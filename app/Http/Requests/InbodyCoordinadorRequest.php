<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InbodyCoordinadorRequest extends FormRequest
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
            'idPersonaSocio'      => 'required|integer|min:1',
            'idPersonaEntrenador' => 'required|integer|min:1',
            'idUn'                => 'required|integer|min:1',
            'fechaSolicitud'      => 'required|date|date_format:Y-m-d H:i:s',

        ];
    }
    public function messages()
    {
        return [
            'fechaSolicitud.date_format' => 'formato invalido',
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
