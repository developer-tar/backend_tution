<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;

use App\Http\Requests\Api\CheckQueryDataRequest;

use App\Models\{AcdemicYear, BillingPeriod, Day, Gender, Location, Mode, Month, Region, Role, Subject, TargetSchool, Year, WeekDay};

use Exception;

use Illuminate\Support\Facades\{Log, Response};
class CommonDataController extends Controller
{

    public function commonApi(CheckQueryDataRequest $request)
    {
        try {
            $data = collect();
            if ($request->filled('param')) {
                $param = $request->param;

                $modelMap = [
                    'WeekDays' => WeekDay::class,
                    'Days' => Day::class,
                    'Months' => Month::class,
                    'Years' => Year::class,
                    'Subjects' => Subject::class,
                    'AcdemicYears' => AcdemicYear::class,
                    'Locations' => Location::class,
                    'Modes' => Mode::class,
                    'Regions' => Region::class,
                    'Genders' => Gender::class,
                    'TargetSchools' => TargetSchool::class,
                    'BillingPeriods' => BillingPeriod::class,
                ];
                if ($param === 'Roles') {
                    $data = Role::select('id', 'name')
                        ->whereNot('name', config('constants.roles.ADMIN'))
                        ->get();
                }
                 if ($param === 'AcdemicYears') {
                      $data = AcdemicYear::all();
                }
                 elseif (array_key_exists($param, $modelMap)) {
                    $model = $modelMap[$param];
                    $data = $model::select('id', 'name')->get();
                }
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
