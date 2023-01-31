<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportsUsersRequest extends FormRequest
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
     *     request="ReportsUsersRequest",
     *     required=true,
     *      @OA\MediaType(
     *         mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="users", type="array",
     *                  example={123,234},
     *                  @OA\Items(
     *                      type="integer"
     *                      )
     *                  ),
     *          )
     *     )
     * )
     */
    public function rules()
    {
        return [

            'users' => 'nullable|array|min:1',

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
