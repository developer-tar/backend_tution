<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;

use App\Http\Requests\Api\CheckQueryDataRequest;

use App\Models\{AcdemicYear, BillingPeriod, Country, Day, Gender, Location, Mode, Month, Region, Role, School, Subject, TargetSchool, Year, WeekDay, Format, Module, CourseTimeSlot, AcdemicCourse};

use Exception;

use Illuminate\Http\Request;
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
                    'Schools' => School::class,
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
                } elseif ($param === 'Regions') {
                    // Get user's IP address
                    $userIp = $this->getUserIpAddress($request);

                    // Detect country from IP (default to UK)
                    $countryCode = $this->detectCountryFromIp($userIp);

                    // Get country by code
                    $country = Country::where('code', $countryCode)->first();

                    if ($country) {
                        // Filter regions by country and approved status
                        $data = Region::select('id', 'name')
                            ->where('country_id', $country->id)
                            ->where('status', config('constants.statuses.APPROVED'))
                            ->whereNull('deleted_at')
                            ->orderBy('name', 'asc')
                            ->get();
                    } else {
                        // Default to UK regions if country not found
                        $ukCountry = Country::where('code', 'GB')->first();
                        if ($ukCountry) {
                            $data = Region::select('id', 'name')
                                ->where('country_id', $ukCountry->id)
                                ->where('status', config('constants.statuses.APPROVED'))
                                ->whereNull('deleted_at')
                                ->orderBy('name', 'asc')
                                ->get();
                        } else {
                            // Fallback: return all regions if UK country not found
                            $data = Region::select('id', 'name')
                                ->where('status', config('constants.statuses.APPROVED'))
                                ->whereNull('deleted_at')
                                ->orderBy('name', 'asc')
                                ->get();
                        }
                    }
                } elseif ($param === 'Schools') {
                    // Filter schools by active status and exclude soft-deleted
                    $data = School::select('id', 'name')
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->orderBy('name', 'asc')
                        ->get();
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

    /**
     * Get user's IP address from request
     *
     * @param Request $request
     * @return string
     */
    private function getUserIpAddress(Request $request): string
    {
        // Check for IP in various headers (for proxies/load balancers)
        $ip = $request->header('X-Forwarded-For');
        if ($ip) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        if (!$ip) {
            $ip = $request->header('X-Real-IP');
        }

        if (!$ip) {
            $ip = $request->ip();
        }

        // Remove port if present
        if (strpos($ip, ':') !== false) {
            $ip = explode(':', $ip)[0];
        }

        return $ip ?: '127.0.0.1';
    }

    /**
     * Detect country code from IP address
     *
     * @param string $ip
     * @return string Country code (GB for UK, DU for Dubai, default GB)
     */
    private function detectCountryFromIp(string $ip): string
    {
        // Skip localhost and private IPs
        if ($ip === '127.0.0.1' || $ip === '::1' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'GB'; // Default to UK
        }

        try {
            // Use ip-api.com free service (no API key required for basic usage)
            $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3, // 3 second timeout
                    'ignore_errors' => true,
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response) {
                $data = json_decode($response, true);

                if (isset($data['status']) && $data['status'] === 'success' && isset($data['countryCode'])) {
                    $countryCode = strtoupper($data['countryCode']);

                    // Map country codes to our country codes
                    if ($countryCode === 'GB' || $countryCode === 'UK') {
                        return 'GB'; // United Kingdom
                    } elseif ($countryCode === 'AE') {
                        // For UAE, return DU for Dubai regions
                        return 'DU'; // Dubai
                    }

                    // Default to UK for any other country
                    return 'GB';
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to detect country from IP {$ip}: {$e->getMessage()}");
        }

        // Default to UK if detection fails
        return 'GB';
    }

    /**
     * Get country phone code based on user's IP address
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCountryPhoneCode(Request $request)
    {
        try {
            $userIp = $this->getUserIpAddress($request);
            $countryCode = $this->detectCountryFromIp($userIp);

            // Get country by code
            $country = Country::where('code', $countryCode)
                ->where('status', config('constants.statuses.APPROVED'))
                ->whereNull('deleted_at')
                ->first();

            if (!$country) {
                // Fallback to UK if country not found
                $country = Country::where('code', 'GB')
                    ->where('status', config('constants.statuses.APPROVED'))
                    ->whereNull('deleted_at')
                    ->first();
            }

            if ($country) {
                return sendResponse([
                    'country_code' => $country->code,
                    'phone_code' => $country->phone_code ?? '+44',
                    'phone_number_length' => $country->phone_number_length ?? 10,
                    'country_name' => $country->name,
                ], 'Country phone code retrieved successfully');
            }

            // Ultimate fallback
            return sendResponse([
                'country_code' => 'GB',
                'phone_code' => '+44',
                'phone_number_length' => 10,
                'country_name' => 'United Kingdom',
            ], 'Country phone code retrieved successfully');
        } catch (Exception $e) {
            Log::error("Failed to get country phone code: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");

            // Return UK as default on error
            return sendResponse([
                'country_code' => 'GB',
                'phone_code' => '+44',
                'phone_number_length' => 10,
                'country_name' => 'United Kingdom',
            ], 'Country phone code retrieved successfully');
        }
    }
}
