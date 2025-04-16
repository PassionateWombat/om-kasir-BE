<?php

namespace App\Http\Traits;

trait ApiResponseTrait
{
    protected function success($data = [], $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    protected function error($message = 'Error', $statusCode = 400, $errors = [])
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}
