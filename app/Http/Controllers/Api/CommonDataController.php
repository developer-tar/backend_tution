<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;

use App\Http\Requests\Api\CheckQueryDataRequest;
use App\Models\AcdemicYear;
use App\Models\Location;
use App\Models\Mode;
use App\Models\Role;

use App\Models\Subject;
use Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class CommonDataController extends Controller
{

    public function commonApi(CheckQueryDataRequest $request)
    {
        try {
            $data = collect();
            if ($request->filled('param') && $request->param == 'Roles') {
                $data = Role::select('id', 'name')->whereNot('name', config('constants.roles.ADMIN'))->get();
            }
            if ($request->filled('param') && $request->param == 'Subjects') {
                $data = Subject::select('id', 'name')->get();
            }
            if ($request->filled('param') && $request->param == 'AcdemicYears') {
                $data = AcdemicYear::all();
            }
            if ($request->filled('param') && $request->param == 'Locations') {
                $data = Location::select('id','name')->get();
            }
              if ($request->filled('param') && $request->param == 'Modes') {
                $data = Mode::select('id','name')->get();
            }
            if ($data->isNotEmpty()) {
                $response = [
                    'success' => true,
                    'data' => $data,
                    'message' => $request->param . ' Fetched Successfully!!',
                ];
                return Response::json($response, 200);
            } else {
                return sendError('Error', ['error' => 'No Record found'], 404);
            }

        } catch (Exception $e) {
            Log::error("fetching  records. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendError('Error', ['error' => 'An error is occured.'], 500);
        }
    }
}
