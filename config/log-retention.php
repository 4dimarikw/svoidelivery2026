<?php

return [
    'event_days' => (int) env('EVENT_LOG_RETENTION_DAYS', 90),
    'failed_job_days' => (int) env('FAILED_JOB_RETENTION_DAYS', 7),
];
