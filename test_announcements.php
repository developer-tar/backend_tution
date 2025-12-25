<?php

/**
 * Test script to verify announcements setup
 * Run this from command line: php test_announcements.php
 * Or access via browser if placed in public folder
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Announcement;
use App\Models\Role;
use App\Models\User;

echo "=== Announcements Test ===\n\n";

// 1. Check if tables exist
echo "1. Checking database tables...\n";
try {
    $announcementsCount = Announcement::count();
    $rolesCount = Role::count();
    echo "   ✓ Announcements table exists: {$announcementsCount} announcements\n";
    echo "   ✓ Roles table exists: {$rolesCount} roles\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. List all roles
echo "2. Available roles:\n";
$roles = Role::all();
foreach ($roles as $role) {
    echo "   - ID: {$role->id}, Name: {$role->name}\n";
}
echo "\n";

// 3. Check announcements
echo "3. Announcements in database:\n";
$announcements = Announcement::with('roles')->get();
if ($announcements->isEmpty()) {
    echo "   ⚠ No announcements found in database!\n";
    echo "   → Create announcements via admin panel first.\n\n";
} else {
    foreach ($announcements as $announcement) {
        $roleNames = $announcement->roles->pluck('name')->implode(', ');
        echo "   - ID: {$announcement->id}, Title: {$announcement->title}\n";
        echo "     Status: {$announcement->status}, Target Audience: {$roleNames}\n";
    }
    echo "\n";
}

// 4. Check active announcements with Student role
echo "4. Active announcements with 'Student' role:\n";
$studentRole = Role::where('name', 'Student')->first();
if ($studentRole) {
    $studentAnnouncements = Announcement::active()
        ->whereHas('roles', function ($query) use ($studentRole) {
            $query->where('roles.id', $studentRole->id);
        })
        ->with('roles')
        ->get();

    if ($studentAnnouncements->isEmpty()) {
        echo "   ⚠ No active announcements found with 'Student' role!\n";
        echo "   → Make sure you have created announcements and selected 'Student' in target audience.\n\n";
    } else {
        echo "   ✓ Found {$studentAnnouncements->count()} announcement(s):\n";
        foreach ($studentAnnouncements as $announcement) {
            echo "     - {$announcement->title}\n";
        }
        echo "\n";
    }
} else {
    echo "   ✗ 'Student' role not found in database!\n\n";
}

// 5. Check student users
echo "5. Student users:\n";
$students = User::whereHas('roles', function ($query) {
    $query->where('name', 'Student');
})->get();

if ($students->isEmpty()) {
    echo "   ⚠ No users with 'Student' role found!\n";
    echo "   → Make sure student users have the 'Student' role attached.\n\n";
} else {
    echo "   ✓ Found {$students->count()} student user(s):\n";
    foreach ($students as $student) {
        $studentRoles = $student->roles->pluck('name')->implode(', ');
        echo "     - ID: {$student->id}, Email: {$student->email}, Roles: {$studentRoles}\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
echo "\nIf announcements are not showing:\n";
echo "1. Make sure announcements exist in database\n";
echo "2. Make sure announcements have 'Student' in target audience\n";
echo "3. Make sure announcements are 'active'\n";
echo "4. Make sure student users have 'Student' role attached\n";
