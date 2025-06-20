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
    'completed_reverse' => [
        1 => "YES",
        0 => "NO",
    ],
    'path' => [
        'image' => 1,
        'video' => 2,
        'pdf' => 3,
        'others' => 4,
    ],
    'modes' => [
        'in_person' => 'In person',
        'online' => 'Online',
    ],
    'months' => [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ],
    'regions' => [
        'Berkshire',
        'Bexley and Bromley',
        'Birmingham, Walsall, Wolverhamption and Wrekin (West Midlands)',
        'Buckinghamshire',
        'Devon',
        'Dorset',
        'Essex',
        'Essex-Redbridge',
        'Gloucestershire',
        'Hertfordshire (Other and North London)',
        'Hertfordshire (South West)',
        'Kent',
        'Lancashire & Cumbria',
        'Lincolnshire',
        'Medway',
        'Northern Ireland',
        'Surrey (Sutton, Kingston and Wandsworth)',
        'Trafford',
        'Warwickshire',
        'Wiltshire',
        'Wirral',
        'Yorkshire',
    ],
    'genders' => [
        'male',
        'female',
        'Prefer not to say'
    ],
    'target_schools' => [
        'Both',
        'Grammer',
        'Independent',
    ],
    'assignment_content' => [
        'TopicContent' => 'App\Models\CourseTopic',
        'SubTopicContent' => 'App\Models\CourseSubTopic',
        'TopicTest' => 'App\Models\CourseTopicTest',
        'SubTopicTest' => 'App\Models\CourseSubTopicTest'
    ],

];
