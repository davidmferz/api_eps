<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class updateProfileRequest extends FormRequest
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
     *     request="updateProfileRequest",
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

            'apodo'           => 'required|string|min:1',
            'certificaciones' => 'required|string|min:1',
            'descripcion'     => 'required|string|min:1',
            'clubs'           => 'required|array|min:1',
            'disciplines'     => 'nullable|array',
            'email'           => 'required|string|min:1',

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
