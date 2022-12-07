<?php

namespace App\Exceptions;

use App\Http\Controllers\ApiController;
use App\Http\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        //$msj = "ErrMsg: " . $exception->getMessage() . " File: " . $exception->getFile() . " Line: " . $exception->getLine();
        //ApiController::notificacionError($msj);

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {

        if (ENV('APP_DEBUG')) {
            return parent::render($request, $exception);
        }
        if ($exception instanceof NotFoundHttpException) {
            return $this->errorResponse('No se encontro la URL especificada', '404');
        }
        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse('Metodo especificado en la peticion no es valido ', '405');
        }

        if ($exception instanceof HttpException) {
            return $this->errorResponse($exception->getMessage(), $exception->getStatusCode());
        }
        if ($exception instanceof QueryException) {
            return $this->errorResponse('error en la base de datos codigo:' . $exception->getCode(), '500');
        }

        if ($exception instanceof ModelNotFoundException) {
            $model = $porciones = explode("\\", $exception->getModel());
            Log::debug("ErrMsg: " . $exception->getMessage());
            return $this->errorResponse('id incorrecto ' . $model[count($model) - 1], '402');
        }

        if ($exception instanceof \Exception) {
            $msj = "ErrMsg: " . $exception->getMessage() . " File: " . $exception->getFile() . " Line: " . $exception->getLine();
            ApiController::notificacionError($msj);

            return $this->errorResponse($exception->getMessage(), '500');
        }

        return $this->errorResponse('error en el servidor, Intente luego ', '500');
    }
}
