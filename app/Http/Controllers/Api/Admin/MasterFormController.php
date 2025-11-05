<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\MasterForm\MasterFormRequestFactory;

class MasterFormController extends Controller
{
    /**
     * Model mappings for different entities
     */
    private array $modelMappings = [
        'schools' => \App\Models\School::class,
        'roles' => \App\Models\Role::class,
        'genders' => \App\Models\Gender::class,
        'regions' => \App\Models\Region::class,
        'formats' => \App\Models\Format::class,
        'target_schools' => \App\Models\TargetSchool::class,
        'days' => \App\Models\Day::class,
        'months' => \App\Models\Month::class,
        'weekdays' => \App\Models\WeekDay::class,
        'years' => \App\Models\Year::class,
        'academic_years' => \App\Models\AcdemicYear::class,
        'weeks' => \App\Models\Week::class,
        'locations' => \App\Models\Location::class,
        'subjects' => \App\Models\Subject::class,
    ];


    /**
     * Get all records for a specific entity with advanced filtering, sorting, and pagination
     */
    public function index(Request $request, string $entity): JsonResponse
    {
        try {
            $modelClass = $this->getModelClass($entity);
            $model = new $modelClass();
            
            // Start building query
            $query = $model->newQuery();
            
            // Handle soft deletes
            $includeTrashedParam = $request->get('include_trashed');
            $includeTrashed = filter_var($includeTrashedParam, FILTER_VALIDATE_BOOLEAN) || $includeTrashedParam === '1' || $includeTrashedParam === 'true';
            
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model))) {
                if ($includeTrashed) {
                    $query = $modelClass::withTrashed();
                } elseif ($request->get('only_trashed')) {
                    $query = $modelClass::onlyTrashed();
                }
            }
            
            // Apply filters
            $this->applyFilters($query, $request, $entity);
            
            // Apply search
            if ($request->filled('search')) {
                $this->applySearch($query, $request->get('search'), $entity);
            }
            
            // Apply sorting
            $this->applySorting($query, $request);
            
            // Pagination parameters
            $perPage = $request->integer('per_page', 15);
            $perPage = min($perPage, 100); // Limit max per page to 100
            
            // Get paginated results
            $records = $query->paginate($perPage);
            
            // Add metadata for frontend
            $metadata = [
                'total_active' => $this->getTotalActive($modelClass),
                'total_deleted' => $this->getTotalDeleted($modelClass),
                'status_counts' => $this->getStatusCounts($modelClass),
                'available_filters' => $this->getAvailableFilters($entity),
                'sortable_fields' => $this->getSortableFields($entity)
            ];
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'metadata' => $metadata,
                'message' => ucfirst($entity) . ' retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            $errorMessage = "Error retrieving {$entity}: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine();
            return errorLog($errorMessage);
        }
    }
    
    /**
     * Apply filters to the query based on request parameters
     */
    private function applyFilters($query, Request $request, string $entity)
    {
        // Status filter
        if ($request->filled('status')) {
            $statuses = is_array($request->get('status')) ? $request->get('status') : [$request->get('status')];
            $query->whereIn('status', $statuses);
        }
        
        // Date range filters
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->get('created_from'));
        }
        
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->get('created_to') . ' 23:59:59');
        }
        
        if ($request->filled('updated_from')) {
            $query->where('updated_at', '>=', $request->get('updated_from'));
        }
        
        if ($request->filled('updated_to')) {
            $query->where('updated_at', '<=', $request->get('updated_to') . ' 23:59:59');
        }
        
        // Entity-specific filters
        switch ($entity) {
            case 'academic_years':
                if ($request->filled('start_year')) {
                    $query->where('start_year', $request->get('start_year'));
                }
                if ($request->filled('end_year')) {
                    $query->where('end_year', $request->get('end_year'));
                }
                break;
                
            case 'weeks':
                if ($request->filled('academic_year_id')) {
                    $query->where('academic_year_id', $request->get('academic_year_id'));
                }
                if ($request->filled('week_number')) {
                    $query->where('week_number', 'like', '%' . $request->get('week_number') . '%');
                }
                break;
                
            case 'years':
                if ($request->filled('name')) {
                    $query->where('name', $request->get('name'));
                }
                break;
        }
    }
    
    /**
     * Apply search functionality
     */
    private function applySearch($query, string $search, string $entity)
    {
        $searchTerm = '%' . $search . '%';
        
        switch ($entity) {
            case 'academic_years':
                $query->where(function($q) use ($searchTerm) {
                    $q->where('start_year', 'like', $searchTerm)
                      ->orWhere('end_year', 'like', $searchTerm);
                });
                break;
                
            case 'weeks':
                $query->where(function($q) use ($searchTerm) {
                    $q->where('week_number', 'like', $searchTerm);
                });
                break;
                
            default:
                // For most entities, search by name
                $query->where('name', 'like', $searchTerm);
                break;
        }
    }
    
    /**
     * Apply sorting to the query
     */
    private function applySorting($query, Request $request)
    {
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'asc');
        
        // Validate sort order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'asc';
        
        // Validate sort field (basic security check)
        $allowedSortFields = ['id', 'name', 'status', 'created_at', 'updated_at', 'deleted_at', 'start_year', 'end_year', 'week_number'];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            // Default sorting
            $query->orderBy('id', 'asc');
        }
    }
    
    /**
     * Get total active records count
     */
    private function getTotalActive($modelClass): int
    {
        try {
            return $modelClass::whereNull('deleted_at')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get total deleted records count
     */
    private function getTotalDeleted($modelClass): int
    {
        try {
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(new $modelClass()))) {
                return $modelClass::onlyTrashed()->count();
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get status counts for entities that have status field
     */
    private function getStatusCounts($modelClass): array
    {
        try {
            $model = new $modelClass();
            if (!$model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'status')) {
                return [];
            }
            
            return $modelClass::selectRaw('status, count(*) as count')
                ->whereNull('deleted_at')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get available filters for the entity
     */
    private function getAvailableFilters(string $entity): array
    {
        $baseFilters = [
            'status' => [
                'type' => 'select',
                'options' => [
                    ['value' => 1, 'label' => 'Pending'],
                    ['value' => 2, 'label' => 'Approved'],
                    ['value' => 3, 'label' => 'Rejected']
                ]
            ],
            'created_from' => ['type' => 'date'],
            'created_to' => ['type' => 'date'],
            'updated_from' => ['type' => 'date'],
            'updated_to' => ['type' => 'date'],
            'include_trashed' => ['type' => 'boolean'],
            'only_trashed' => ['type' => 'boolean']
        ];
        
        switch ($entity) {
            case 'academic_years':
                $baseFilters['start_year'] = ['type' => 'number'];
                $baseFilters['end_year'] = ['type' => 'number'];
                break;
                
            case 'weeks':
                $baseFilters['academic_year_id'] = ['type' => 'number'];
                $baseFilters['week_number'] = ['type' => 'text'];
                break;
        }
        
        return $baseFilters;
    }
    
    /**
     * Get sortable fields for the entity
     */
    private function getSortableFields(string $entity): array
    {
        $baseFields = [
            'id' => 'ID',
            'name' => 'Name',
            'status' => 'Status',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
            'deleted_at' => 'Deleted Date'
        ];
        
        switch ($entity) {
            case 'academic_years':
                $baseFields['start_year'] = 'Start Year';
                $baseFields['end_year'] = 'End Year';
                unset($baseFields['name']);
                break;
                
            case 'weeks':
                $baseFields['week_number'] = 'Week Number';
                $baseFields['start_date'] = 'Start Date';
                $baseFields['end_date'] = 'End Date';
                unset($baseFields['name']);
                break;
        }
        
        return $baseFields;
    }

    /**
     * Store a new record
     */
    public function store(Request $request, string $entity): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $modelClass = $this->getModelClass($entity);
            
            // Get validation rules for the entity
            $validationRules = $this->getValidationRules($entity, $request->method());
            
            // Validate the request
            $validatedData = $request->validate($validationRules);
            
            $record = $modelClass::create($validatedData);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => ucfirst($entity) . ' created successfully'
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = "Error creating {$entity}: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine();
            return errorLog($errorMessage);
        }
    }

    /**
     * Show a specific record
     */
    public function show(string $entity, int $id): JsonResponse
    {
        try {
            $modelClass = $this->getModelClass($entity);
            $record = $modelClass::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => ucfirst($entity) . ' retrieved successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($entity) . ' not found'
            ], 404);
        } catch (\Exception $e) {
            $errorMessage = "Error retrieving {$entity} with ID {$id}: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine();
            return errorLog($errorMessage);
        }
    }

    /**
     * Update a specific record
     */
    public function update(Request $request, string $entity, int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $modelClass = $this->getModelClass($entity);
            
            // Get validation rules for the entity
            $validationRules = $this->getValidationRules($entity, $request->method());
            
            // Validate the request
            $validatedData = $request->validate($validationRules);
            
            $record = $modelClass::findOrFail($id);
            $record->update($validatedData);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $record->fresh(),
                'message' => ucfirst($entity) . ' updated successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => ucfirst($entity) . ' not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = "Error updating {$entity} with ID {$id}: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine();
            return errorLog($errorMessage);
        }
    }

    /**
     * Delete a specific record
     */
    public function destroy(string $entity, int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $modelClass = $this->getModelClass($entity);
            $record = $modelClass::findOrFail($id);
            
            $record->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($entity) . ' deleted successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => ucfirst($entity) . ' not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = "Error deleting {$entity} with ID {$id}: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine();
            return errorLog($errorMessage);
        }
    }

    /**
     * Restore a soft-deleted record
     */
    public function restore(string $entity, int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $modelClass = $this->getModelClass($entity);
            $model = new $modelClass();
            
            if (!in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model))) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This entity does not support soft deletes'
                ], 400);
            }
            
            $record = $modelClass::withTrashed()->findOrFail($id);
            
            if (!$record->trashed()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Record is not deleted'
                ], 400);
            }
            
            $record->restore();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $record->fresh(),
                'message' => ucfirst($entity) . ' restored successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => ucfirst($entity) . ' not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = "Error restoring {$entity} with ID {$id}: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine();
            return errorLog($errorMessage);
        }
    }

    /**
     * Get available entities
     */
    public function getEntities(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => MasterFormRequestFactory::getAvailableEntities(),
            'message' => 'Available entities retrieved successfully'
        ]);
    }

    /**
     * Get model class for entity
     */
    private function getModelClass(string $entity): string
    {
        if (!isset($this->modelMappings[$entity])) {
            throw new \InvalidArgumentException("Entity '{$entity}' is not supported");
        }
        
        return $this->modelMappings[$entity];
    }

    /**
     * Get validation rules for the given entity
     */
    private function getValidationRules(string $entity, string $method = 'POST'): array
    {
        $isUpdate = in_array($method, ['PUT', 'PATCH']);
        
        switch ($entity) {
            case 'schools':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                    'address' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'logo' => 'nullable|string|max:255',
                    'website' => 'nullable|url|max:255',
                    'status' => 'nullable|string|in:active,inactive',
                ];
                
            case 'roles':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                ];
                
            case 'genders':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:60' : 'required|string|max:60',
                    'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
                ];
                
            case 'regions':
                $nameRule = $isUpdate ? 'sometimes|string|max:100' : 'required|string|max:100|unique:regions,name';
                if ($isUpdate) {
                    $id = request()->route('id');
                    $nameRule .= '|unique:regions,name,' . $id;
                }
                return [
                    'name' => $nameRule,
                    'status' => 'nullable|integer|in:1,2,3',
                ];
                
            case 'formats':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                    'description' => 'nullable|string',
                ];
                
            case 'target_schools':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:20' : 'required|string|max:20',
                    'status' => 'nullable|integer|in:1,2,3',
                ];
                
            case 'days':
                $nameRule = $isUpdate ? 'sometimes|string|max:2' : 'required|string|max:2|unique:days,name';
                if ($isUpdate) {
                    $id = request()->route('id');
                    $nameRule .= '|unique:days,name,' . $id;
                }
                return [
                    'name' => $nameRule,
                    'status' => 'nullable|integer|in:1,2,3',
                ];
                
            case 'months':
                $nameRule = $isUpdate ? 'sometimes|string|max:10' : 'required|string|max:10|unique:months,name';
                if ($isUpdate) {
                    $id = request()->route('id');
                    $nameRule .= '|unique:months,name,' . $id;
                }
                return [
                    'name' => $nameRule,
                    'status' => 'nullable|integer|in:1,2,3',
                ];
                
            case 'weekdays':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                    'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
                ];
                
            case 'years':
                $nameRule = $isUpdate ? 'sometimes|integer|min:1900|max:2100' : 'required|integer|min:1900|max:2100|unique:years,name';
                if ($isUpdate) {
                    $id = request()->route('id');
                    $nameRule .= '|unique:years,name,' . $id;
                }
                return [
                    'name' => $nameRule,
                    'status' => 'nullable|integer|in:1,2,3',
                ];
                
            case 'academic_years':
                $rules = [
                    'start_year' => $isUpdate ? 'sometimes|integer|min:1900|max:2100' : 'required|integer|min:1900|max:2100',
                    'end_year' => $isUpdate ? 'sometimes|integer|min:1900|max:2100' : 'required|integer|min:1900|max:2100|gt:start_year',
                    'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
                ];
                
                if ($isUpdate && !request()->has('start_year')) {
                    $rules['end_year'] = 'sometimes|integer|min:1900|max:2100';
                }
                
                return $rules;
                
            case 'weeks':
                return [
                    'academic_year_id' => $isUpdate ? 'sometimes|exists:acdemic_years,id' : 'required|exists:acdemic_years,id',
                    'week_number' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                    'start_date' => $isUpdate ? 'sometimes|date' : 'required|date',
                    'end_date' => $isUpdate ? 'sometimes|date|after:start_date' : 'required|date|after:start_date',
                    'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
                ];
                
            case 'locations':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                    'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
                ];
                
            case 'subjects':
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                    'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
                ];
                
            default:
                return [
                    'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
                ];
        }
    }

}
