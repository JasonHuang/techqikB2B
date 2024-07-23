<?php

/**
 * Sets a transient to lock the update process.
 * 
 * @param int $duration_in_minutes The duration of the lock in minutes. Default is 30 minutes.
 */
function techqik_set_update_lock($duration_in_minutes = 30) {
    $duration_in_seconds = $duration_in_minutes * 60; // Convert minutes to seconds
    set_transient('techqik_price_update_in_progress', time(), $duration_in_seconds);
    error_log("update is locked!");
}

/**
 * Checks if the update process is currently locked.
 * 
 * @return bool True if locked, false otherwise.
 */
function techqik_is_update_locked() {
    $lock_time = get_transient('techqik_price_update_in_progress');
    if ($lock_time === false) {
        return false;
    }
    
    $lock_duration_in_seconds = 30 * 60; // 30 minutes in seconds
    // Check if the lock has expired (more than 30 minutes old)
    if (time() - $lock_time > $lock_duration_in_seconds) {
        techqik_release_update_lock();
        return false;
    }
    
    return true;
}

/**
 * Releases the update lock by deleting the transient.
 */
function techqik_release_update_lock() {
    delete_transient('techqik_price_update_in_progress');
    error_log("update lock is released!");
}