<?php
return [
    'roles' => [
        'ADMIN' => 'Admin',
        'STUDENT' => 'Student',
        'PARENT' => 'Parent',
        'TUTOR' => 'Tutor',
    ],
    'statuses' => [
        'PENDING' => 1,
        'APPROVED' => 2,
        'REJECTED' => 3,
    ],
    'subjects' => [
        'ENGLISH' => 'English',
        'MATH' => 'Math',
        'VERBAL_NON_VERBAL_REASONING' => 'Verbal & Non-Verbal Reasoning',
    ],
    'locations' => [
        'EALING' => 'Ealing',
        'KINGSTON' => 'Kingston',
        'NEW_MALDEN' => 'New Malden',
        'SLOUGH' => 'Slough',
        'WIMBLEDON' => 'Wimbledon',
        'SUTTON' => 'Sutton',
        'ONLINE' => 'online',
    ],
    'mode' => [
        'IN_PERSON' => 'in person',
        'ONLINE' => 'online',
    ],
    'course_type' => [
        'WEEKLY' => 1,
        'DAILY' => 2,
        'MONTHLY' => 3
    ],
    'acdemic_start_year' => [
        2025,
        2026,
        2027,
        2028,
    ],
    'acdemic_end_year' => [
        2026,
        2027,
        2028,
        2029,
    ],
    'completed' => [
        'YES' => 1,
        'NO' => 0,
    ],
    'product_id' => env('PRODUCT_ID'),
    'sk_test' => env('SK_TEST'),
    'pk_test' => env('PK_TEST'),
    'path' => [
        'image' => 1,
        'video' => 2,
        'pdf' => 3,
        'others' => 4,
    ],
    'modes' => [
        'in_person' => 'In person',
        'online' => 'Online',
    ]
]
    ?>