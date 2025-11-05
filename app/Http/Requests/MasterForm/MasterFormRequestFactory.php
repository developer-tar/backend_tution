<?php

namespace App\Http\Requests\MasterForm;

use Illuminate\Http\Request;

class MasterFormRequestFactory
{
    /**
     * Entity to Form Request mapping
     */
    private static array $requestMappings = [
        'schools' => SchoolRequest::class,
        'roles' => SimpleEntityRequest::class,
        'genders' => SimpleEntityRequest::class,
        'regions' => SimpleEntityRequest::class,
        'formats' => SimpleEntityRequest::class,
        'target_schools' => SimpleEntityRequest::class,
        'days' => SimpleEntityRequest::class,
        'months' => SimpleEntityRequest::class,
        'weekdays' => SimpleEntityRequest::class,
        'years' => YearRequest::class,
        'academic_years' => AcademicYearRequest::class,
        'weeks' => WeekRequest::class,
        'locations' => SimpleEntityRequest::class,
        'subjects' => SimpleEntityRequest::class,
    ];

    /**
     * Get the appropriate Form Request class for the given entity
     */
    public static function getRequestClass(string $entity): string
    {
        if (!isset(self::$requestMappings[$entity])) {
            throw new \InvalidArgumentException("No Form Request defined for entity: {$entity}");
        }

        return self::$requestMappings[$entity];
    }

    /**
     * Create and validate a Form Request instance for the given entity
     */
    public static function createAndValidate(string $entity, Request $request): BaseMasterFormRequest
    {
        $requestClass = self::getRequestClass($entity);
        
        // Create new instance of the Form Request
        $formRequest = new $requestClass();
        
        // Set the request data and properties
        $formRequest->merge($request->all());
        $formRequest->setMethod($request->method());
        $formRequest->setRouteResolver($request->getRouteResolver());
        
        // Set additional properties needed for validation
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        
        // Copy route parameters
        if ($request->route()) {
            $formRequest->setRoute($request->route());
        }
        
        // Validate the request
        $formRequest->validateResolved();
        
        return $formRequest;
    }

    /**
     * Get available entities
     */
    public static function getAvailableEntities(): array
    {
        return array_keys(self::$requestMappings);
    }
}
