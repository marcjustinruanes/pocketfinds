<?php
return [
    // Keep the implementation and records, but disable all attendance pages/actions.
    // Seller AI reviews can be restored without removing their implementation.
    'admin_ai' => env('ADMIN_AI_ENABLED', false),
    'admin_ai_review' => env('ADMIN_AI_REVIEW_ENABLED', false),
    'attendance' => env('ATTENDANCE_ENABLED', false),
];
