<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignClassRequest extends FormRequest
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
     *
     *
     * @OA\RequestBody(
     *     request="AssignClassRequest",
     *     required=true,
     *      @OA\MediaType(
     *         mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property( property="id", type="integer", example=""),
     *              @OA\Property( property="instructorId", type="integer", example=""),
     *              @OA\Property(property="fechaClase", type="array",
     *                  example={"01-01-2023", "02-01-2023"},
     *                  @OA\Items(
     *                      type="string"
     *                      )
     *                  ),
     *              @OA\Property( property="horaClase", type="string", example=""),
     *              @OA\Property( property="asistentes", type="integer", example=""),
     *          )
     *     )
     * )
     */
    public function rules()
    {
        return [
            'id'           => 'required|integer|min:1',
            'instructorId' => 'required|integer|min:1',
            'fechaClase'   => 'required|array|min:1',
            'horaClase'    => 'required|string|min:1',
            'asistentes'   => 'required|integer|min:1',

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
