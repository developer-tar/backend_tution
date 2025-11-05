/**
 * Master Form API Test Runner
 * Comprehensive JavaScript testing framework for Master Form API
 */

class MasterFormTestRunner {
    constructor(baseUrl, token) {
        this.baseUrl = baseUrl;
        this.token = token;
        this.results = [];
        this.testCount = 0;
        this.passCount = 0;
        this.failCount = 0;
    }

    // Set authentication token
    setToken(token) {
        this.token = token;
    }

    // Get request headers
    getHeaders() {
        return {
            'Authorization': `Bearer ${this.token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
    }

    // Make HTTP request
    async makeRequest(endpoint, method = 'GET', data = null) {
        const url = `${this.baseUrl}${endpoint}`;
        const options = {
            method: method,
            headers: this.getHeaders()
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const responseData = await response.json();
            
            return {
                status: response.status,
                success: response.ok,
                data: responseData
            };
        } catch (error) {
            return {
                status: 500,
                success: false,
                data: { message: error.message }
            };
        }
    }

    // Log test result
    logResult(testName, passed, message, data = null) {
        this.testCount++;
        if (passed) {
            this.passCount++;
        } else {
            this.failCount++;
        }

        const result = {
            test: testName,
            status: passed ? 'PASS' : 'FAIL',
            message: message,
            data: data,
            timestamp: new Date().toISOString()
        };

        this.results.push(result);
        console.log(`${result.status}: ${testName} - ${message}`);
        
        return result;
    }

    // Test suite: Basic CRUD operations
    async testBasicCrud() {
        console.log('Running Basic CRUD Tests...');
        const results = [];

        // Test 1: Get available entities
        try {
            const response = await this.makeRequest('/entities');
            results.push(this.logResult(
                'Get Available Entities',
                response.success && Array.isArray(response.data.data),
                response.success ? 'Entities retrieved successfully' : 'Failed to get entities',
                response.data
            ));
        } catch (error) {
            results.push(this.logResult('Get Available Entities', false, error.message));
        }

        // Test 2: Create school
        try {
            const schoolData = { name: `Test School ${Date.now()}` };
            const response = await this.makeRequest('/schools', 'POST', schoolData);
            results.push(this.logResult(
                'Create School',
                response.status === 201,
                response.success ? 'School created successfully' : 'Failed to create school',
                response.data
            ));

            // Store school ID for further tests
            if (response.success && response.data.data) {
                this.testSchoolId = response.data.data.id;
            }
        } catch (error) {
            results.push(this.logResult('Create School', false, error.message));
        }

        // Test 3: Get schools
        try {
            const response = await this.makeRequest('/schools?per_page=5');
            results.push(this.logResult(
                'Get Schools',
                response.success && response.data.data,
                response.success ? 'Schools retrieved successfully' : 'Failed to get schools',
                { count: response.data.data?.data?.length || 0 }
            ));
        } catch (error) {
            results.push(this.logResult('Get Schools', false, error.message));
        }

        // Test 4: Update school (if we have an ID)
        if (this.testSchoolId) {
            try {
                const updateData = { name: `Updated School ${Date.now()}` };
                const response = await this.makeRequest(`/schools/${this.testSchoolId}`, 'PUT', updateData);
                results.push(this.logResult(
                    'Update School',
                    response.success,
                    response.success ? 'School updated successfully' : 'Failed to update school',
                    response.data
                ));
            } catch (error) {
                results.push(this.logResult('Update School', false, error.message));
            }
        }

        // Test 5: Delete school (if we have an ID)
        if (this.testSchoolId) {
            try {
                const response = await this.makeRequest(`/schools/${this.testSchoolId}`, 'DELETE');
                results.push(this.logResult(
                    'Delete School',
                    response.success,
                    response.success ? 'School deleted successfully' : 'Failed to delete school',
                    response.data
                ));
            } catch (error) {
                results.push(this.logResult('Delete School', false, error.message));
            }
        }

        return results;
    }

    // Test suite: Validation rules
    async testValidationRules() {
        console.log('Running Validation Tests...');
        const results = [];

        // Test 1: Required field validation
        try {
            const response = await this.makeRequest('/schools', 'POST', {});
            results.push(this.logResult(
                'Required Field Validation',
                response.status === 422,
                response.status === 422 ? 'Required validation working' : 'Required validation failed',
                response.data
            ));
        } catch (error) {
            results.push(this.logResult('Required Field Validation', false, error.message));
        }

        // Test 2: String length validation
        try {
            const longName = 'A'.repeat(300);
            const response = await this.makeRequest('/schools', 'POST', { name: longName });
            results.push(this.logResult(
                'String Length Validation',
                response.status === 422,
                response.status === 422 ? 'Length validation working' : 'Length validation failed',
                response.data
            ));
        } catch (error) {
            results.push(this.logResult('String Length Validation', false, error.message));
        }

        // Test 3: Academic year validation
        try {
            const invalidYear = { start_year: 2025, end_year: 2024 };
            const response = await this.makeRequest('/academic_years', 'POST', invalidYear);
            results.push(this.logResult(
                'Academic Year Range Validation',
                response.status === 422,
                response.status === 422 ? 'Year range validation working' : 'Year range validation failed',
                response.data
            ));
        } catch (error) {
            results.push(this.logResult('Academic Year Range Validation', false, error.message));
        }

        return results;
    }

    // Test suite: Week business rules
    async testWeekBusinessRules() {
        console.log('Running Week Business Rules Tests...');
        const results = [];

        // First, create an academic year for testing
        let academicYearId = null;
        try {
            const yearData = { start_year: 2024, end_year: 2025 };
            const response = await this.makeRequest('/academic_years', 'POST', yearData);
            if (response.success && response.data.data) {
                academicYearId = response.data.data.id;
            }
        } catch (error) {
            console.log('Failed to create academic year for testing:', error.message);
        }

        if (!academicYearId) {
            results.push(this.logResult('Week Business Rules Setup', false, 'Could not create academic year for testing'));
            return results;
        }

        // Test 1: Valid week (Monday to Sunday 22:00)
        try {
            const validWeek = {
                academic_year_id: academicYearId,
                week_number: `test week valid ${Date.now()}`,
                start_date: '2024-01-01 00:00:00', // Monday
                end_date: '2024-01-07 22:00:00'   // Sunday 22:00
            };
            const response = await this.makeRequest('/weeks', 'POST', validWeek);
            results.push(this.logResult(
                'Valid Week Creation',
                response.status === 201,
                response.success ? 'Valid week created successfully' : 'Valid week creation failed',
                response.data
            ));

            // Store week ID for cleanup
            if (response.success && response.data.data) {
                this.testWeekId = response.data.data.id;
            }
        } catch (error) {
            results.push(this.logResult('Valid Week Creation', false, error.message));
        }

        // Test 2: Invalid start day (Tuesday instead of Monday)
        try {
            const invalidWeek = {
                academic_year_id: academicYearId,
                week_number: `test week invalid start ${Date.now()}`,
                start_date: '2024-01-02 00:00:00', // Tuesday
                end_date: '2024-01-08 22:00:00'
            };
            const response = await this.makeRequest('/weeks', 'POST', invalidWeek);
            results.push(this.logResult(
                'Invalid Start Day Validation',
                response.status === 422,
                response.status === 422 ? 'Start day validation working' : 'Start day validation failed',
                response.data
            ));
        } catch (error) {
            results.push(this.logResult('Invalid Start Day Validation', false, error.message));
        }

        // Test 3: Invalid end time (20:00 instead of 22:00)
        try {
            const invalidWeek = {
                academic_year_id: academicYearId,
                week_number: `test week invalid end ${Date.now()}`,
                start_date: '2024-01-08 00:00:00', // Monday
                end_date: '2024-01-14 20:00:00'   // Sunday 20:00 (should be 22:00)
            };
            const response = await this.makeRequest('/weeks', 'POST', invalidWeek);
            results.push(this.logResult(
                'Invalid End Time Validation',
                response.status === 422,
                response.status === 422 ? 'End time validation working' : 'End time validation failed',
                response.data
            ));
        } catch (error) {
            results.push(this.logResult('Invalid End Time Validation', false, error.message));
        }

        // Test 4: Overlapping weeks
        if (this.testWeekId) {
            try {
                const overlappingWeek = {
                    academic_year_id: academicYearId,
                    week_number: `test week overlap ${Date.now()}`,
                    start_date: '2024-01-03 00:00:00', // Overlaps with first week
                    end_date: '2024-01-09 22:00:00'
                };
                const response = await this.makeRequest('/weeks', 'POST', overlappingWeek);
                results.push(this.logResult(
                    'Overlapping Week Validation',
                    response.status === 422,
                    response.status === 422 ? 'Overlap validation working' : 'Overlap validation failed',
                    response.data
                ));
            } catch (error) {
                results.push(this.logResult('Overlapping Week Validation', false, error.message));
            }
        }

        // Cleanup: Delete test week and academic year
        if (this.testWeekId) {
            try {
                await this.makeRequest(`/weeks/${this.testWeekId}`, 'DELETE');
            } catch (error) {
                console.log('Failed to cleanup test week:', error.message);
            }
        }

        try {
            await this.makeRequest(`/academic_years/${academicYearId}`, 'DELETE');
        } catch (error) {
            console.log('Failed to cleanup test academic year:', error.message);
        }

        return results;
    }

    // Test suite: Performance tests
    async testPerformance() {
        console.log('Running Performance Tests...');
        const results = [];

        // Test 1: Pagination performance
        try {
            const startTime = performance.now();
            const response = await this.makeRequest('/schools?per_page=50&page=1');
            const endTime = performance.now();
            const responseTime = endTime - startTime;

            results.push(this.logResult(
                'Pagination Performance',
                response.success && responseTime < 2000, // Should respond within 2 seconds
                `Response time: ${responseTime.toFixed(2)}ms`,
                { response_time_ms: responseTime, success: response.success }
            ));
        } catch (error) {
            results.push(this.logResult('Pagination Performance', false, error.message));
        }

        // Test 2: Concurrent requests
        try {
            const startTime = performance.now();
            const promises = [];
            
            // Make 5 concurrent requests
            for (let i = 0; i < 5; i++) {
                promises.push(this.makeRequest('/entities'));
            }
            
            const responses = await Promise.all(promises);
            const endTime = performance.now();
            const totalTime = endTime - startTime;
            
            const allSuccessful = responses.every(r => r.success);
            
            results.push(this.logResult(
                'Concurrent Requests',
                allSuccessful && totalTime < 5000, // Should complete within 5 seconds
                `5 concurrent requests completed in ${totalTime.toFixed(2)}ms`,
                { total_time_ms: totalTime, all_successful: allSuccessful }
            ));
        } catch (error) {
            results.push(this.logResult('Concurrent Requests', false, error.message));
        }

        return results;
    }

    // Run all test suites
    async runAllTests() {
        console.log('Starting Master Form API Test Suite...');
        this.results = [];
        this.testCount = 0;
        this.passCount = 0;
        this.failCount = 0;

        const startTime = performance.now();

        try {
            // Run all test suites
            await this.testBasicCrud();
            await this.testValidationRules();
            await this.testWeekBusinessRules();
            await this.testPerformance();

            const endTime = performance.now();
            const totalTime = endTime - startTime;

            // Generate summary
            const summary = {
                total_tests: this.testCount,
                passed: this.passCount,
                failed: this.failCount,
                success_rate: this.testCount > 0 ? ((this.passCount / this.testCount) * 100).toFixed(2) : 0,
                execution_time_ms: totalTime.toFixed(2)
            };

            console.log('\n=== TEST SUMMARY ===');
            console.log(`Total Tests: ${summary.total_tests}`);
            console.log(`Passed: ${summary.passed}`);
            console.log(`Failed: ${summary.failed}`);
            console.log(`Success Rate: ${summary.success_rate}%`);
            console.log(`Execution Time: ${summary.execution_time_ms}ms`);

            return {
                success: true,
                summary: summary,
                results: this.results
            };

        } catch (error) {
            console.error('Test suite execution failed:', error);
            return {
                success: false,
                error: error.message,
                results: this.results
            };
        }
    }

    // Get test results
    getResults() {
        return {
            summary: {
                total_tests: this.testCount,
                passed: this.passCount,
                failed: this.failCount,
                success_rate: this.testCount > 0 ? ((this.passCount / this.testCount) * 100).toFixed(2) : 0
            },
            results: this.results
        };
    }

    // Clear results
    clearResults() {
        this.results = [];
        this.testCount = 0;
        this.passCount = 0;
        this.failCount = 0;
    }
}

// Export for use in browser
if (typeof window !== 'undefined') {
    window.MasterFormTestRunner = MasterFormTestRunner;
}

// Export for Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MasterFormTestRunner;
}
