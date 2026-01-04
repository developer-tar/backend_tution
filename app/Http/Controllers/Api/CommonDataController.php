<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;

use App\Http\Requests\Api\CheckQueryDataRequest;

use App\Models\{AcdemicYear, BillingPeriod, Day, Gender, Location, Mode, Month, Region, Role, Subject, TargetSchool, Year, WeekDay, Format, Module, CourseTimeSlot, AcdemicCourse};

use Exception;

use Illuminate\Support\Facades\{Log, Response, Schema};

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
                    'Formats' => Format::class,
                    'ModuleModes' => Module::class,
                ];
                if ($param === 'Roles') {
                    // Check if roles table exists
                    if (!Schema::hasTable('roles')) {
                        errorLog("Roles table doesn't exist when trying to fetch roles.");
                        return sendError('Error', ['error' => 'Roles table not found. Please run the migration: php artisan migrate'], 500);
                    }

                    // Get only active (non-deleted) roles, excluding ADMIN, TUTOR, and SCHOOL
                    $data = Role::select('id', 'name')
                        ->whereNot('name', config('constants.roles.ADMIN'))
                        ->whereNot('name', config('constants.roles.TUTOR'))
                        ->whereNot('name', config('constants.roles.SCHOOL'))
                        ->whereNull('deleted_at') // Only active (non-deleted) roles
                        ->orderBy('name', 'asc')
                        ->get();
                } 
                
                if ($param === 'AcdemicYears') {
                    $data = AcdemicYear::all();
                } elseif ($param === 'ModuleModes') {
                    // Get active module modes
                    $data = Module::active()
                        ->select('id', 'name', 'description')
                        ->orderBy('name', 'asc')
                        ->get();
                } elseif ($param === 'Classes') {
                    // Handle Classes with params (course_id and academic_year_id)
                    $courseId = $request->input('course_id');
                    $academicYearId = $request->input('academic_year_id');

                    if (!$courseId || !$academicYearId) {
                        return sendError('Error', ['error' => 'course_id and academic_year_id are required for Classes'], 422);
                    }

                    // Find the academic_course_id by matching course_id and academic_year_id
                    $academicCourse = AcdemicCourse::where('course_id', $courseId)
                        ->where('acdemic_id', $academicYearId)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$academicCourse) {
                        $data = collect([]);
                    } else {
                        // Fetch classes from course_time_slots where course_id and academic_course_id match
                        $data = CourseTimeSlot::where('course_id', $courseId)
                            ->where('academic_course_id', $academicCourse->id)
                            ->whereNull('deleted_at')
                            ->select('id', 'class_name as name')
                            ->distinct()
                            ->orderBy('class_name', 'asc')
                            ->get();
                    }
                } elseif (array_key_exists($param, $modelMap)) {
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
            return errorLog("Failed to fetch records: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
