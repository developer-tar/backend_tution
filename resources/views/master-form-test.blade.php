<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Form API Test Interface</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .test-section h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        button:hover {
            background: #2980b9;
        }
        
        button.danger {
            background: #e74c3c;
        }
        
        button.danger:hover {
            background: #c0392b;
        }
        
        button.success {
            background: #27ae60;
        }
        
        button.success:hover {
            background: #229954;
        }
        
        .test-result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        
        .test-result.success {
            background: #d4edda;
            border-color: #27ae60;
            color: #155724;
        }
        
        .test-result.error {
            background: #f8d7da;
            border-color: #e74c3c;
            color: #721c24;
        }
        
        .test-result.info {
            background: #d1ecf1;
            border-color: #3498db;
            color: #0c5460;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .test-summary {
            background: #34495e;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            background: white;
            border-bottom-color: #3498db;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Master Form API Test Interface</h1>
            <p>Comprehensive testing interface for all Master Form API endpoints and business rules</p>
        </div>

        <div class="test-summary" id="testSummary" style="display: none;">
            <h2>Test Results Summary</h2>
            <div class="summary-stats" id="summaryStats"></div>
        </div>

        <div class="test-section">
            <h2>Authentication Setup</h2>
            <div class="form-group">
                <label for="apiToken">JWT Token:</label>
                <input type="text" id="apiToken" placeholder="Enter your JWT token here">
            </div>
            <div class="form-group">
                <label for="baseUrl">Base URL:</label>
                <input type="text" id="baseUrl" value="{{ url('/api/admin/master-form') }}" placeholder="API Base URL">
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('automated')">Automated Tests</button>
            <button class="tab" onclick="showTab('manual')">Manual Testing</button>
            <button class="tab" onclick="showTab('business-rules')">Business Rules</button>
            <button class="tab" onclick="showTab('performance')">Performance Tests</button>
        </div>

        <!-- Automated Tests Tab -->
        <div id="automated" class="tab-content active">
            <div class="test-section">
                <h2>Automated Test Suite</h2>
                <p>Run comprehensive tests to verify all API functionality</p>
                
                <button onclick="runAllTests()" class="success">Run All Tests</button>
                <button onclick="runBasicCrud()">Test Basic CRUD</button>
                <button onclick="runValidationTests()">Test Validations</button>
                <button onclick="runBusinessRules()">Test Business Rules</button>
                <button onclick="clearResults()">Clear Results</button>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Running tests...</p>
                </div>
                
                <div id="testResults"></div>
            </div>
        </div>

        <!-- Manual Testing Tab -->
        <div id="manual" class="tab-content">
            <div class="test-grid">
                <!-- Basic CRUD Tests -->
                <div class="test-section">
                    <h2>Basic CRUD Operations</h2>
                    
                    <div class="form-group">
                        <label for="entity">Entity:</label>
                        <select id="entity">
                            <option value="schools">Schools</option>
                            <option value="roles">Roles</option>
                            <option value="genders">Genders</option>
                            <option value="regions">Regions</option>
                            <option value="formats">Formats</option>
                            <option value="target_schools">Target Schools</option>
                            <option value="days">Days</option>
                            <option value="months">Months</option>
                            <option value="weekdays">Weekdays</option>
                            <option value="years">Years</option>
                            <option value="academic_years">Academic Years</option>
                            <option value="weeks">Weeks</option>
                        </select>
                    </div>
                    
                    <button onclick="getEntities()">Get Available Entities</button>
                    <button onclick="getAllRecords()">Get All Records</button>
                    <button onclick="createRecord()">Create Record</button>
                    <button onclick="updateRecord()">Update Record</button>
                    <button onclick="deleteRecord()">Delete Record</button>
                    
                    <div id="crudResults"></div>
                </div>

                <!-- Create Forms -->
                <div class="test-section">
                    <h2>Create New Records</h2>
                    
                    <div id="simpleForm" style="display: block;">
                        <h3>Simple Entity (Name only)</h3>
                        <div class="form-group">
                            <label for="simpleName">Name:</label>
                            <input type="text" id="simpleName" placeholder="Enter name">
                        </div>
                        <button onclick="createSimpleEntity()">Create</button>
                    </div>
                    
                    <div id="academicYearForm">
                        <h3>Academic Year</h3>
                        <div class="form-group">
                            <label for="startYear">Start Year:</label>
                            <input type="number" id="startYear" placeholder="2024" min="1900" max="2100">
                        </div>
                        <div class="form-group">
                            <label for="endYear">End Year:</label>
                            <input type="number" id="endYear" placeholder="2025" min="1900" max="2100">
                        </div>
                        <button onclick="createAcademicYear()">Create Academic Year</button>
                    </div>
                    
                    <div id="weekForm">
                        <h3>Week</h3>
                        <div class="form-group">
                            <label for="academicYearId">Academic Year ID:</label>
                            <input type="number" id="academicYearId" placeholder="1">
                        </div>
                        <div class="form-group">
                            <label for="weekNumber">Week Number:</label>
                            <input type="text" id="weekNumber" placeholder="week 1">
                        </div>
                        <div class="form-group">
                            <label for="startDate">Start Date (Monday):</label>
                            <input type="datetime-local" id="startDate">
                        </div>
                        <div class="form-group">
                            <label for="endDate">End Date (Sunday 22:00):</label>
                            <input type="datetime-local" id="endDate">
                        </div>
                        <button onclick="createWeek()">Create Week</button>
                    </div>
                    
                    <div id="createResults"></div>
                </div>
            </div>
        </div>

        <!-- Business Rules Tab -->
        <div id="business-rules" class="tab-content">
            <div class="test-section">
                <h2>Week Business Rules Testing</h2>
                <p>Test specific business rules for week validation</p>
                
                <button onclick="testValidWeek()" class="success">Test Valid Week</button>
                <button onclick="testInvalidStartDay()" class="danger">Test Invalid Start Day</button>
                <button onclick="testInvalidEndTime()" class="danger">Test Invalid End Time</button>
                <button onclick="testOverlappingWeeks()" class="danger">Test Overlapping Weeks</button>
                <button onclick="testOutsideAcademicYear()" class="danger">Test Outside Academic Year</button>
                
                <div id="businessRuleResults"></div>
            </div>

            <div class="test-section">
                <h2>Academic Year Rules Testing</h2>
                
                <button onclick="testValidAcademicYear()" class="success">Test Valid Academic Year</button>
                <button onclick="testInvalidAcademicYear()" class="danger">Test Invalid Year Range</button>
                
                <div id="academicYearResults"></div>
            </div>
        </div>

        <!-- Performance Tests Tab -->
        <div id="performance" class="tab-content">
            <div class="test-section">
                <h2>Performance Testing</h2>
                
                <button onclick="testPagination()">Test Pagination</button>
                <button onclick="testLargeDataset()">Test Large Dataset</button>
                <button onclick="testConcurrentRequests()">Test Concurrent Requests</button>
                
                <div id="performanceResults"></div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let baseUrl = '{{ url("/api/admin/master-form") }}';
        let testToken = '';

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Utility functions
        function getHeaders() {
            const token = document.getElementById('apiToken').value || testToken;
            baseUrl = document.getElementById('baseUrl').value;
            
            return {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            };
        }

        function showLoading(show = true) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        function displayResult(containerId, result, type = 'info') {
            const container = document.getElementById(containerId);
            const resultDiv = document.createElement('div');
            resultDiv.className = `test-result ${type}`;
            
            let content = `<strong>Status:</strong> ${result.status || 'Unknown'}<br>`;
            content += `<strong>Message:</strong> ${result.message || 'No message'}<br>`;
            
            if (result.data) {
                content += `<strong>Data:</strong><div class="code-block">${JSON.stringify(result.data, null, 2)}</div>`;
            }
            
            if (result.error) {
                content += `<strong>Error:</strong><div class="code-block">${JSON.stringify(result.error, null, 2)}</div>`;
            }
            
            resultDiv.innerHTML = content;
            container.appendChild(resultDiv);
        }

        function makeRequest(url, method = 'GET', data = null) {
            const options = {
                method: method,
                headers: getHeaders()
            };
            
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            return fetch(url, options)
                .then(response => {
                    return response.json().then(data => ({
                        status: response.status,
                        data: data
                    }));
                })
                .catch(error => ({
                    status: 500,
                    data: { success: false, message: error.message }
                }));
        }

        // Automated test functions
        async function runAllTests() {
            showLoading(true);
            clearResults();
            
            try {
                const response = await fetch('{{ route("master-form.test.run-all") }}', {
                    method: 'POST',
                    headers: getHeaders()
                });
                
                const result = await response.json();
                showLoading(false);
                
                if (result.success) {
                    displayTestSummary(result.summary);
                    displayDetailedResults(result.results);
                } else {
                    displayResult('testResults', result, 'error');
                }
            } catch (error) {
                showLoading(false);
                displayResult('testResults', {
                    status: 'ERROR',
                    message: 'Failed to run tests: ' + error.message
                }, 'error');
            }
        }

        function displayTestSummary(summary) {
            const summaryDiv = document.getElementById('testSummary');
            const statsDiv = document.getElementById('summaryStats');
            
            statsDiv.innerHTML = `
                <div class="stat-card">
                    <div class="stat-number">${summary.total_tests}</div>
                    <div>Total Tests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #27ae60;">${summary.passed}</div>
                    <div>Passed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #e74c3c;">${summary.failed}</div>
                    <div>Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${summary.success_rate}%</div>
                    <div>Success Rate</div>
                </div>
            `;
            
            summaryDiv.style.display = 'block';
        }

        function displayDetailedResults(results) {
            const container = document.getElementById('testResults');
            
            Object.keys(results).forEach(category => {
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'test-section';
                categoryDiv.innerHTML = `<h3>${category.replace('_', ' ').toUpperCase()}</h3>`;
                
                Object.keys(results[category]).forEach(testName => {
                    const test = results[category][testName];
                    const type = test.status === 'PASS' ? 'success' : 'error';
                    displayResult(categoryDiv.id || 'testResults', test, type);
                });
                
                container.appendChild(categoryDiv);
            });
        }

        // Manual test functions
        async function getEntities() {
            const result = await makeRequest(`${baseUrl}/entities`);
            displayResult('crudResults', result.data, result.status === 200 ? 'success' : 'error');
        }

        async function getAllRecords() {
            const entity = document.getElementById('entity').value;
            const result = await makeRequest(`${baseUrl}/${entity}?per_page=5`);
            displayResult('crudResults', result.data, result.status === 200 ? 'success' : 'error');
        }

        async function createSimpleEntity() {
            const entity = document.getElementById('entity').value;
            const name = document.getElementById('simpleName').value;
            
            if (!name) {
                alert('Please enter a name');
                return;
            }
            
            const data = { name: name };
            const result = await makeRequest(`${baseUrl}/${entity}`, 'POST', data);
            displayResult('createResults', result.data, result.status === 201 ? 'success' : 'error');
        }

        async function createAcademicYear() {
            const startYear = parseInt(document.getElementById('startYear').value);
            const endYear = parseInt(document.getElementById('endYear').value);
            
            const data = { start_year: startYear, end_year: endYear };
            const result = await makeRequest(`${baseUrl}/academic_years`, 'POST', data);
            displayResult('createResults', result.data, result.status === 201 ? 'success' : 'error');
        }

        async function createWeek() {
            const academicYearId = parseInt(document.getElementById('academicYearId').value);
            const weekNumber = document.getElementById('weekNumber').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            const data = {
                academic_year_id: academicYearId,
                week_number: weekNumber,
                start_date: startDate.replace('T', ' ') + ':00',
                end_date: endDate.replace('T', ' ') + ':00'
            };
            
            const result = await makeRequest(`${baseUrl}/weeks`, 'POST', data);
            displayResult('createResults', result.data, result.status === 201 ? 'success' : 'error');
        }

        // Business rule test functions
        async function testValidWeek() {
            const data = {
                academic_year_id: 1,
                week_number: 'test week valid',
                start_date: '2024-01-01 00:00:00', // Monday
                end_date: '2024-01-07 22:00:00'   // Sunday 22:00
            };
            
            const result = await makeRequest(`${baseUrl}/weeks`, 'POST', data);
            displayResult('businessRuleResults', {
                status: result.status === 201 ? 'PASS' : 'FAIL',
                message: 'Valid week test',
                data: result.data
            }, result.status === 201 ? 'success' : 'error');
        }

        async function testInvalidStartDay() {
            const data = {
                academic_year_id: 1,
                week_number: 'test week invalid start',
                start_date: '2024-01-02 00:00:00', // Tuesday (should fail)
                end_date: '2024-01-08 22:00:00'
            };
            
            const result = await makeRequest(`${baseUrl}/weeks`, 'POST', data);
            displayResult('businessRuleResults', {
                status: result.status === 422 ? 'PASS' : 'FAIL',
                message: 'Invalid start day test (should fail)',
                data: result.data
            }, result.status === 422 ? 'success' : 'error');
        }

        async function testInvalidEndTime() {
            const data = {
                academic_year_id: 1,
                week_number: 'test week invalid end',
                start_date: '2024-01-08 00:00:00', // Monday
                end_date: '2024-01-14 20:00:00'   // Sunday 20:00 (should be 22:00)
            };
            
            const result = await makeRequest(`${baseUrl}/weeks`, 'POST', data);
            displayResult('businessRuleResults', {
                status: result.status === 422 ? 'PASS' : 'FAIL',
                message: 'Invalid end time test (should fail)',
                data: result.data
            }, result.status === 422 ? 'success' : 'error');
        }

        // Utility functions
        function clearResults() {
            document.getElementById('testResults').innerHTML = '';
            document.getElementById('crudResults').innerHTML = '';
            document.getElementById('createResults').innerHTML = '';
            document.getElementById('businessRuleResults').innerHTML = '';
            document.getElementById('academicYearResults').innerHTML = '';
            document.getElementById('performanceResults').innerHTML = '';
            document.getElementById('testSummary').style.display = 'none';
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default values
            const now = new Date();
            const monday = new Date(now.setDate(now.getDate() - now.getDay() + 1));
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            sunday.setHours(22, 0, 0);
            
            document.getElementById('startDate').value = monday.toISOString().slice(0, 16);
            document.getElementById('endDate').value = sunday.toISOString().slice(0, 16);
            document.getElementById('startYear').value = new Date().getFullYear();
            document.getElementById('endYear').value = new Date().getFullYear() + 1;
        });
    </script>
</body>
</html>
