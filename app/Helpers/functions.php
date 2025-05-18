<?php

use Illuminate\Support\Facades\Response;


/**
 * Success response method
 *
 * @param $result
 * @param $message
 * @return \Illuminate\Http\JsonResponse
 */

function sendResponse($result = 'delete', $message, $code = 200)
{

    $response = [
        'success' => true,
        'data' => $result,
        'message' => $message,
    ];
    if ($response['data'] == 'delete')
        unset($repsonse['data']);
    return Response::json($response, $code);
}
/**
 * Return error response
 *
 * @param       $error
 * @param array $errorMessages
 * @param int   $code
 * @return \Illuminate\Http\JsonResponse
 */
function sendError($error, $errorMessages = [], $code = 404)
{
    $response = [
        'success' => false,
        'message' => $error,
    ];

    !empty($errorMessages) ? $response['data'] = $errorMessages : null;
    return Response::json($response, $code);

}


function getFileType($extension)
{
    return match ($extension) {
        'jpg', 'jpeg', 'png', 'gif' => config('constants.path.image'),
        'mp4', 'avi', 'mkv' => config('constants.path.video'),
        'pdf' => config('constants.path.pdf'),
        default => config('constants.path.others'),
    };
}
