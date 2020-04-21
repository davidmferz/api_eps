<?php

namespace API_EPS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InscribirProgramaRequest extends FormRequest
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

            'idCliente'        => 'required|integer|min:1',
            'idCategoria'      => 'required|integer|min:1',
            'idProducto'       => 'required|integer|min:1',
            'idTipoCliente'    => 'required|integer|min:1',
            'idEsquemaPago'    => 'required|integer|min:1',
            'idUn'             => 'required|integer|min:1',
            'idVendedor'       => 'required|integer|min:1',
            'formaPago'        => 'required|integer|min:1',
            'importe'          => 'required|integer|min:1',
            'tipo'             => 'required|integer|min:1',
            'idUnicoMembresia' => 'required|integer',
            'demo'             => 'required|integer',
            'cantidad'         => 'required|integer|min:1',

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
