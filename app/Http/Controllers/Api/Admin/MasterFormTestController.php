<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\School;
use App\Models\Role;
use App\Models\Gender;
use App\Models\AcdemicYear;
use App\Models\Week;
use Carbon\Carbon;

class MasterFormTestController extends Controller
{
    /**
     * Display the test interface
     */
    public function index()
    {
        return view('master-form-test');
    }

    /**
     * Run all test cases and return results
     */
    public function runAllTests(): JsonResponse
    {
        $results = [];
        
        try {
            // Test 1: Basic CRUD Operations
            $results['basic_crud'] = $this->testBasicCrud();
            
            // Test 2: Validation Rules
            $results['validation'] = $this->testValidation();
            
            // Test 3: Academic Year Business Rules
            $results['academic_year'] = $this->testAcademicYear();
            
            // Test 4: Week Business Rules
            $results['week_business_rules'] = $this->testWeekBusinessRules();
            
            // Test 5: Transaction Rollback
            $results['transactions'] = $this->testTransactions();
            
            // Test 6: Error Handling
            $results['error_handling'] = $this->testErrorHandling();
            
            // Test 7: Pagination
            $results['pagination'] = $this->testPagination();
            
            // Test 8: Soft Deletes
            $results['soft_deletes'] = $this->testSoftDeletes();

            return response()->json([
                'success' => true,
                'message' => 'All tests completed',
                'results' => $results,
                'summary' => $this->generateSummary($results)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test execution failed: ' . $e->getMessage(),
                'results' => $results
            ], 500);
        }
    }

    /**
     * Test basic CRUD operations
     */
    private function testBasicCrud(): array
    {
        $tests = [];
        
        try {
            // Test Create
            $school = School::create(['name' => 'Test School CRUD ' . time()]);
            $tests['create'] = [
                'status' => 'PASS',
                'message' => 'School created successfully',
                'data' => ['id' => $school->id, 'name' => $school->name]
            ];
            
            // Test Read
            $foundSchool = School::find($school->id);
            $tests['read'] = [
                'status' => $foundSchool ? 'PASS' : 'FAIL',
                'message' => $foundSchool ? 'School retrieved successfully' : 'School not found',
                'data' => $foundSchool ? ['id' => $foundSchool->id] : null
            ];
            
            // Test Update
            $school->update(['name' => 'Updated Test School CRUD ' . time()]);
            $tests['update'] = [
                'status' => 'PASS',
                'message' => 'School updated successfully',
                'data' => ['new_name' => $school->fresh()->name]
            ];
            
            // Test Delete
            $school->delete();
            $tests['delete'] = [
                'status' => 'PASS',
                'message' => 'School deleted successfully',
                'data' => ['deleted_at' => $school->fresh()->deleted_at]
            ];
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'CRUD test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test validation rules
     */
    private function testValidation(): array
    {
        $tests = [];
        
        try {
            // Test required field validation
            try {
                School::create([]);
                $tests['required_validation'] = [
                    'status' => 'FAIL',
                    'message' => 'Required validation should have failed'
                ];
            } catch (\Exception $e) {
                $tests['required_validation'] = [
                    'status' => 'PASS',
                    'message' => 'Required validation working correctly'
                ];
            }
            
            // Test string length validation
            try {
                School::create(['name' => str_repeat('A', 300)]);
                $tests['length_validation'] = [
                    'status' => 'FAIL',
                    'message' => 'String length validation should have failed'
                ];
            } catch (\Exception $e) {
                $tests['length_validation'] = [
                    'status' => 'PASS',
                    'message' => 'String length validation working correctly'
                ];
            }
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Validation test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test academic year business rules
     */
    private function testAcademicYear(): array
    {
        $tests = [];
        
        try {
            // Test valid academic year
            $academicYear = AcdemicYear::create([
                'start_year' => 2024,
                'end_year' => 2025
            ]);
            $tests['valid_creation'] = [
                'status' => 'PASS',
                'message' => 'Valid academic year created',
                'data' => ['id' => $academicYear->id]
            ];
            
            // Test invalid academic year (end < start)
            try {
                AcdemicYear::create([
                    'start_year' => 2025,
                    'end_year' => 2024
                ]);
                $tests['invalid_year_range'] = [
                    'status' => 'FAIL',
                    'message' => 'Invalid year range should have been rejected'
                ];
            } catch (\Exception $e) {
                $tests['invalid_year_range'] = [
                    'status' => 'PASS',
                    'message' => 'Invalid year range correctly rejected'
                ];
            }
            
            // Clean up
            $academicYear->delete();
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Academic year test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test week business rules
     */
    private function testWeekBusinessRules(): array
    {
        $tests = [];
        
        try {
            // Create academic year for testing
            $academicYear = AcdemicYear::create([
                'start_year' => 2024,
                'end_year' => 2025
            ]);
            
            // Test 1: Valid week (Monday to Sunday 22:00)
            $validWeek = Week::create([
                'academic_year_id' => $academicYear->id,
                'week_number' => 'test week 1',
                'start_date' => '2024-01-01 00:00:00', // Monday
                'end_date' => '2024-01-07 22:00:00'   // Sunday 22:00
            ]);
            $tests['valid_week'] = [
                'status' => 'PASS',
                'message' => 'Valid week created successfully',
                'data' => ['id' => $validWeek->id]
            ];
            
            // Test 2: Invalid start date (not Monday)
            try {
                Week::create([
                    'academic_year_id' => $academicYear->id,
                    'week_number' => 'test week 2',
                    'start_date' => '2024-01-02 00:00:00', // Tuesday
                    'end_date' => '2024-01-08 22:00:00'
                ]);
                $tests['invalid_start_day'] = [
                    'status' => 'FAIL',
                    'message' => 'Non-Monday start date should have been rejected'
                ];
            } catch (\Exception $e) {
                $tests['invalid_start_day'] = [
                    'status' => 'PASS',
                    'message' => 'Non-Monday start date correctly rejected'
                ];
            }
            
            // Test 3: Invalid end date (not Sunday 22:00)
            try {
                Week::create([
                    'academic_year_id' => $academicYear->id,
                    'week_number' => 'test week 3',
                    'start_date' => '2024-01-08 00:00:00', // Monday
                    'end_date' => '2024-01-14 20:00:00'   // Sunday 20:00 (wrong time)
                ]);
                $tests['invalid_end_time'] = [
                    'status' => 'FAIL',
                    'message' => 'Wrong end time should have been rejected'
                ];
            } catch (\Exception $e) {
                $tests['invalid_end_time'] = [
                    'status' => 'PASS',
                    'message' => 'Wrong end time correctly rejected'
                ];
            }
            
            // Test 4: Overlapping weeks
            try {
                Week::create([
                    'academic_year_id' => $academicYear->id,
                    'week_number' => 'test week 4',
                    'start_date' => '2024-01-03 00:00:00', // Overlaps with first week
                    'end_date' => '2024-01-09 22:00:00'
                ]);
                $tests['overlapping_weeks'] = [
                    'status' => 'FAIL',
                    'message' => 'Overlapping weeks should have been rejected'
                ];
            } catch (\Exception $e) {
                $tests['overlapping_weeks'] = [
                    'status' => 'PASS',
                    'message' => 'Overlapping weeks correctly rejected'
                ];
            }
            
            // Test 5: Week outside academic year
            try {
                Week::create([
                    'academic_year_id' => $academicYear->id,
                    'week_number' => 'test week 5',
                    'start_date' => '2023-12-25 00:00:00', // Before academic year
                    'end_date' => '2023-12-31 22:00:00'
                ]);
                $tests['outside_academic_year'] = [
                    'status' => 'FAIL',
                    'message' => 'Week outside academic year should have been rejected'
                ];
            } catch (\Exception $e) {
                $tests['outside_academic_year'] = [
                    'status' => 'PASS',
                    'message' => 'Week outside academic year correctly rejected'
                ];
            }
            
            // Clean up
            $validWeek->delete();
            $academicYear->delete();
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Week business rules test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test transaction rollback
     */
    private function testTransactions(): array
    {
        $tests = [];
        
        try {
            $initialCount = School::count();
            
            // Simulate transaction rollback
            DB::beginTransaction();
            try {
                School::create(['name' => 'Transaction Test School']);
                // Force an error
                throw new \Exception('Simulated error for rollback test');
            } catch (\Exception $e) {
                DB::rollBack();
            }
            
            $finalCount = School::count();
            
            $tests['rollback'] = [
                'status' => ($initialCount === $finalCount) ? 'PASS' : 'FAIL',
                'message' => ($initialCount === $finalCount) ? 'Transaction rollback working correctly' : 'Transaction rollback failed',
                'data' => [
                    'initial_count' => $initialCount,
                    'final_count' => $finalCount
                ]
            ];
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Transaction test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test error handling
     */
    private function testErrorHandling(): array
    {
        $tests = [];
        
        try {
            // Test model not found
            try {
                School::findOrFail(99999);
                $tests['not_found'] = [
                    'status' => 'FAIL',
                    'message' => 'Model not found should throw exception'
                ];
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $tests['not_found'] = [
                    'status' => 'PASS',
                    'message' => 'Model not found exception handled correctly'
                ];
            }
            
            // Test database constraint violation
            try {
                // Try to create week with non-existent academic year
                Week::create([
                    'academic_year_id' => 99999,
                    'week_number' => 'test week',
                    'start_date' => '2024-01-01 00:00:00',
                    'end_date' => '2024-01-07 22:00:00'
                ]);
                $tests['constraint_violation'] = [
                    'status' => 'FAIL',
                    'message' => 'Foreign key constraint should have failed'
                ];
            } catch (\Exception $e) {
                $tests['constraint_violation'] = [
                    'status' => 'PASS',
                    'message' => 'Foreign key constraint correctly enforced'
                ];
            }
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Error handling test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test pagination
     */
    private function testPagination(): array
    {
        $tests = [];
        
        try {
            // Create multiple schools for pagination test
            $schools = [];
            for ($i = 1; $i <= 25; $i++) {
                $schools[] = School::create(['name' => "Pagination Test School {$i}"]);
            }
            
            // Test pagination
            $page1 = School::where('name', 'like', 'Pagination Test School%')->paginate(10);
            $page2 = School::where('name', 'like', 'Pagination Test School%')->paginate(10, ['*'], 'page', 2);
            
            $tests['pagination_structure'] = [
                'status' => (isset($page1->total) && isset($page1->per_page)) ? 'PASS' : 'FAIL',
                'message' => (isset($page1->total) && isset($page1->per_page)) ? 'Pagination structure correct' : 'Pagination structure missing',
                'data' => [
                    'page1_count' => $page1->count(),
                    'page2_count' => $page2->count(),
                    'total' => $page1->total()
                ]
            ];
            
            $tests['pagination_logic'] = [
                'status' => ($page1->count() === 10 && $page2->count() === 10) ? 'PASS' : 'FAIL',
                'message' => ($page1->count() === 10 && $page2->count() === 10) ? 'Pagination logic working correctly' : 'Pagination logic failed'
            ];
            
            // Clean up
            foreach ($schools as $school) {
                $school->delete();
            }
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Pagination test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test soft deletes
     */
    private function testSoftDeletes(): array
    {
        $tests = [];
        
        try {
            // Create and soft delete a school
            $school = School::create(['name' => 'Soft Delete Test School']);
            $school->delete();
            
            // Test soft delete
            $tests['soft_delete'] = [
                'status' => ($school->fresh()->deleted_at !== null) ? 'PASS' : 'FAIL',
                'message' => ($school->fresh()->deleted_at !== null) ? 'Soft delete working correctly' : 'Soft delete failed',
                'data' => ['deleted_at' => $school->fresh()->deleted_at]
            ];
            
            // Test restore
            $school->restore();
            $tests['restore'] = [
                'status' => ($school->fresh()->deleted_at === null) ? 'PASS' : 'FAIL',
                'message' => ($school->fresh()->deleted_at === null) ? 'Restore working correctly' : 'Restore failed',
                'data' => ['deleted_at' => $school->fresh()->deleted_at]
            ];
            
            // Clean up
            $school->forceDelete();
            
        } catch (\Exception $e) {
            $tests['error'] = [
                'status' => 'FAIL',
                'message' => 'Soft delete test failed: ' . $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Generate test summary
     */
    private function generateSummary(array $results): array
    {
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        
        foreach ($results as $category => $tests) {
            foreach ($tests as $test) {
                if (isset($test['status'])) {
                    $totalTests++;
                    if ($test['status'] === 'PASS') {
                        $passedTests++;
                    } else {
                        $failedTests++;
                    }
                }
            }
        }
        
        return [
            'total_tests' => $totalTests,
            'passed' => $passedTests,
            'failed' => $failedTests,
            'success_rate' => $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0
        ];
    }

    /**
     * Get test case examples for frontend
     */
    public function getTestCases(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'test_cases' => [
                'basic_crud' => [
                    'create_school' => [
                        'method' => 'POST',
                        'endpoint' => '/api/admin/master-form/schools',
                        'data' => ['name' => 'Test School'],
                        'expected_status' => 201
                    ],
                    'get_schools' => [
                        'method' => 'GET',
                        'endpoint' => '/api/admin/master-form/schools',
                        'expected_status' => 200
                    ],
                    'update_school' => [
                        'method' => 'PUT',
                        'endpoint' => '/api/admin/master-form/schools/{id}',
                        'data' => ['name' => 'Updated School'],
                        'expected_status' => 200
                    ],
                    'delete_school' => [
                        'method' => 'DELETE',
                        'endpoint' => '/api/admin/master-form/schools/{id}',
                        'expected_status' => 200
                    ]
                ],
                'validation_tests' => [
                    'required_field' => [
                        'method' => 'POST',
                        'endpoint' => '/api/admin/master-form/schools',
                        'data' => [],
                        'expected_status' => 422
                    ],
                    'invalid_academic_year' => [
                        'method' => 'POST',
                        'endpoint' => '/api/admin/master-form/academic_years',
                        'data' => ['start_year' => 2025, 'end_year' => 2024],
                        'expected_status' => 422
                    ]
                ],
                'week_business_rules' => [
                    'valid_week' => [
                        'method' => 'POST',
                        'endpoint' => '/api/admin/master-form/weeks',
                        'data' => [
                            'academic_year_id' => 1,
                            'week_number' => 'week 1',
                            'start_date' => '2024-01-01 00:00:00',
                            'end_date' => '2024-01-07 22:00:00'
                        ],
                        'expected_status' => 201
                    ],
                    'invalid_start_day' => [
                        'method' => 'POST',
                        'endpoint' => '/api/admin/master-form/weeks',
                        'data' => [
                            'academic_year_id' => 1,
                            'week_number' => 'week 2',
                            'start_date' => '2024-01-02 00:00:00',
                            'end_date' => '2024-01-08 22:00:00'
                        ],
                        'expected_status' => 422
                    ]
                ]
            ]
        ]);
    }
}
