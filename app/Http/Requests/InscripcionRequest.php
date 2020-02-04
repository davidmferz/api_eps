<?php

namespace API_EPS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InscripcionRequest extends FormRequest
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

            'idCategoria'      => 'required|integer|min:1',
            'idProducto'       => 'required|integer|min:1',
            'idUnicoMembresia' => 'required|integer',
            'idTipoCliente'    => 'required|integer|min:1',
            'idUn'             => 'required|integer|min:1',
            'idVendedor'       => 'required|integer|min:1',
            'idEntrenador'     => 'required|integer|min:1',
            'idCliente'        => 'required|integer|min:1',
            'participantes'    => 'required|integer|min:1',
            'clases'           => 'required|integer|min:1',
            'formaPago'        => 'required|integer',
            'importe'          => 'required|numeric|min:1',
            'idUnicoMembresia' => 'required|integer',
            'tipo'             => 'required|integer|min:1',

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
