<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellPackageRequest extends FormRequest
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
     * @OA\RequestBody(
     *     request="SellPackageRequest",
     *     required=true,
     *      @OA\MediaType(
     *         mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property( property="clientId", type="integer", example=""),
     *              @OA\Property( property="instructorId", type="integer", example=""),
     *              @OA\Property( property="productoId", type="integer", example=""),
     *              @OA\Property( property="clubId", type="integer", example=""),
     *              @OA\Property( property="responsableId", type="integer", example=""),
     *              @OA\Property( property="precioId", type="integer", example=""),
     *              @OA\Property( property="unidades", type="integer", example=""),
     *          )
     *     )
     * )
     */
    public function rules()
    {
        return [

            'clientId'      => 'required|integer|min:1',
            'instructorId'  => 'required|integer|min:1',
            'productoId'    => 'required|integer|min:1',
            'clubId'        => 'required|integer|min:1',
            'responsableId' => 'required|integer|min:1',
            'precioId'      => 'required|integer|min:1',
            'unidades'      => 'required|integer|min:1',

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
            throw new HttpResponseException(response($response, $response['code']));
        }
    }
}
