<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyApiController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->noContent()->withHeaders([
                'Access-Control-Allow-Origin' => 'https://inventory.jmacxled.com',
                'Access-Control-Allow-Methods' => 'OPTIONS, GET, POST, PUT, DELETE',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Max-Age' => '3600',
            ]);
        }

        $server = $_SERVER;
        $bufferLevel = ob_get_level();
        $_SERVER['REQUEST_METHOD'] = $request->method();
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        if ($request->headers->has('Authorization')) {
            $_SERVER['HTTP_AUTHORIZATION'] = $request->header('Authorization');
        }

        ob_start();
        try {
            require base_path('api/index.php');
            $body = ob_get_clean();
            $status = http_response_code() ?: Response::HTTP_OK;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $_SERVER = $server;
            http_response_code(Response::HTTP_OK);
        }

        return response($body, $status)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Access-Control-Allow-Origin', 'https://inventory.jmacxled.com')
            ->header('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, DELETE')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }
}
