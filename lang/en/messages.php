<?php

return [
    // ---- API Responses ----
    'Success' => 'Success',
    'Error' => 'Error',
    'Not found' => 'Not found',
    'Unauthorized' => 'Unauthorized',
    'Forbidden' => 'Forbidden',
    'Validation failed' => 'Validation failed',
    'Created' => 'Created',
    'Updated' => 'Updated',
    'Deleted' => 'Deleted',
    'Unauthenticated' => 'Unauthenticated',

    // ---- Projects ----
    'Project not found' => 'Project not found',
    'Project not found!' => 'Project not found!',
    'Project not resolved' => 'Project not resolved',
    'Project name is required' => 'Project name is required',
    'Project slug is required' => 'Project slug is required',
    'Slug already exists' => 'Slug already exists',
    'Slug can only contain lowercase letters, numbers, and hyphens' => 'Slug can only contain lowercase letters, numbers, and hyphens',
    'Domain not in whitelist' => 'Domain not in whitelist',
    'Domain whitelist updated successfully' => 'Domain whitelist updated successfully',
    'Please reactivate the project before deleting.' => 'Please reactivate the project before deleting.',
    'The project owner cannot be removed.' => 'The project owner cannot be removed.',
    'The default language cannot be removed.' => 'The default language cannot be removed.',

    // ---- Collections ----
    'Collection not found' => 'Collection not found',
    'Collection not found!' => 'Collection not found!',
    'Schema imported.' => 'Schema imported.',
    'Schema must include a collection name and slug.' => 'Schema must include a collection name and slug.',
    'Invalid schema file. Expected { "collection": {...}, "fields": [...] }.' => 'Invalid schema file. Expected { "collection": {...}, "fields": [...] }.',

    // ---- Content ----
    'Content not found' => 'Content not found',
    'Record not found' => 'Record not found',
    'Content is already published or already under review.' => 'Content is already published or already under review.',
    'Only content currently under review can be approved.' => 'Only content currently under review can be approved.',
    'Only content currently under review can be rejected.' => 'Only content currently under review can be rejected.',
    'This content is not a draft branch.' => 'This content is not a draft branch.',
    'This project has the editorial workflow enabled — submit the content for review and approve it via the workflow endpoints instead of publishing directly.' => 'This project has the editorial workflow enabled — submit the content for review and approve it via the workflow endpoints instead of publishing directly.',
    'Draft discarded.' => 'Draft discarded.',
    'Submitted for review.' => 'Submitted for review.',
    'Approved and published.' => 'Approved and published.',
    'Rejected.' => 'Rejected.',
    'Restoring this revision will permanently delete data from fields that do not exist in that snapshot.' => 'Restoring this revision will permanently delete data from fields that do not exist in that snapshot.',
    'Revision data is corrupted.' => 'Revision data is corrupted.',
    'Revision restored successfully.' => 'Revision restored successfully.',
    'Unsupported export format.' => 'Unsupported export format.',
    'Only .json and .csv files are supported.' => 'Only .json and .csv files are supported.',
    'Invalid JSON file.' => 'Invalid JSON file.',
    'Import failed: ' => 'Import failed: ',
    'No file uploaded.' => 'No file uploaded.',
    'Search query must be at least 2 characters.' => 'Search query must be at least 2 characters.',
    'Search query cannot exceed 100 characters.' => 'Search query cannot exceed 100 characters.',
    'Invalid limit parameter.' => 'Invalid limit parameter.',
    'Invalid offset parameter.' => 'Invalid offset parameter.',
    'Incorrect limit statement.' => 'Incorrect limit statement.',
    'Incorrect offset statement.' => 'Incorrect offset statement.',
    'Incorrect sort statement' => 'Incorrect sort statement',

    // ---- Comments ----
    'Comments are not enabled for this project' => 'Comments are not enabled for this project',
    'Comments are disabled for this article' => 'Comments are disabled for this article',
    'Article not found' => 'Article not found',
    'Comment submitted and awaiting moderation' => 'Comment submitted and awaiting moderation',
    'Comment approved' => 'Comment approved',
    'Comment marked as spam' => 'Comment marked as spam',
    'Comment moved to trash' => 'Comment moved to trash',
    'Comment restored' => 'Comment restored',
    'Comments deleted' => 'Comments deleted',
    'Comments updated' => 'Comments updated',
    'Invalid action' => 'Invalid action',
    'No comments selected' => 'No comments selected',
    'Deleted article' => 'Deleted article',

    // ---- Media ----
    'Media not found' => 'Media not found',
    'Failed to delete media' => 'Failed to delete media',
    'File not found! Attach a file to your request.' => 'File not found! Attach a file to your request.',
    'Chunk upload is disabled.' => 'Chunk upload is disabled.',

    // ---- API Tokens ----
    'API token is not valid for this project' => 'API token is not valid for this project',
    'API token does not have the required permissions' => 'API token does not have the required permissions',

    // ---- Users & Auth ----
    'You cannot delete your own account.' => 'You cannot delete your own account.',
    'Bulk actions cannot include your own account.' => 'Bulk actions cannot include your own account.',
    'Cannot delete the last super admin.' => 'Cannot delete the last super admin.',
    'Cannot remove the last super admin.' => 'Cannot remove the last super admin.',
    'No matching users found.' => 'No matching users found.',
    'Please log in through the admin area.' => 'Please log in through the admin area.',
    'These credentials do not grant access to the admin area.' => 'These credentials do not grant access to the admin area.',
    'Password updated.' => 'Password updated.',
    'The provided password is incorrect.' => 'The provided password is incorrect.',
    'The provided code was invalid.' => 'The provided code was invalid.',
    'The provided two factor authentication code was invalid.' => 'The provided two factor authentication code was invalid.',
    'Two factor authentication is already enabled.' => 'Two factor authentication is already enabled.',
    'Two factor authentication is not enabled.' => 'Two factor authentication is not enabled.',
    'Two factor authentication has been disabled.' => 'Two factor authentication has been disabled.',
    'Enable two factor authentication first.' => 'Enable two factor authentication first.',

    // ---- Notifications ----
    'Notifications marked as read.' => 'Notifications marked as read.',

    // ---- Preview ----
    'Preview not found or the link has been revoked.' => 'Preview not found or the link has been revoked.',
    'Preview link has expired.' => 'Preview link has expired.',

    // ---- Translations ----
    'Placeholder mismatch' => 'Placeholder mismatch',
    'Source string is empty' => 'Source string is empty',

    // ---- Collection Fields ----
    'Add at least one option to enumeration list' => 'Add at least one option to enumeration list',
    'Select a collection' => 'Select a collection',
    'This field is required' => 'This field is required',
    'Must be numeric' => 'Must be numeric',
    'Enter a value' => 'Enter a value',
    'Must be less than max' => 'Must be less than max',

    // ---- System ----
    'Cleared route cache' => 'Cleared route cache',
    'Route cache cleared.' => 'Route cache cleared.',
    'Cleared config cache' => 'Cleared config cache',
    'Config cache cleared.' => 'Config cache cleared.',
    'Cleared view cache' => 'Cleared view cache',
    'View cache cleared.' => 'View cache cleared.',
    'Cleared application cache' => 'Cleared application cache',
    'Application cache cleared.' => 'Application cache cleared.',
    'Rebuilt config & route cache' => 'Rebuilt config & route cache',
    'Config and route cache rebuilt.' => 'Config and route cache rebuilt.',
    'Storage link already exists' => 'Storage link already exists',
    'Storage link already exists.' => 'Storage link already exists.',
    'Storage link created.' => 'Storage link created.',
    'Recreated storage link' => 'Recreated storage link',
    'Failed to create storage link: ' => 'Failed to create storage link: ',
    'public/storage already exists as a file or folder. Move it away first, then try again.' => 'public/storage already exists as a file or folder. Move it away first, then try again.',
    'PHP symlink() is disabled on this server (disable_functions). Enable it in php.ini, or create the link manually from the terminal: ln -s ' => 'PHP symlink() is disabled on this server (disable_functions). Enable it in php.ini, or create the link manually from the terminal: ln -s ',
    'symlink() failed. Check directory permissions.' => 'symlink() failed. Check directory permissions.',
    'Cleared :count old log file(s)' => 'Cleared :count old log file(s)',
    ':count old log file(s) cleared.' => ':count old log file(s) cleared.',
    'Enabled maintenance mode' => 'Enabled maintenance mode',
    'Maintenance mode enabled.' => 'Maintenance mode enabled.',
    'Disabled maintenance mode' => 'Disabled maintenance mode',
    'Maintenance mode disabled.' => 'Maintenance mode disabled.',
    'Ran pending migrations' => 'Ran pending migrations',
    'Migrations executed.' => 'Migrations executed.',
    'Migration failed: ' => 'Migration failed: ',
    ':ran ran, :pending pending' => ':ran ran, :pending pending',

    // ---- Settings ----
    'My Website' => 'My Website',
    'Aine is a content management system built with Laravel and Vue.js' => 'Aine is a content management system built with Laravel and Vue.js',
    'Admin path must be a single lowercase slug (letters, digits, hyphens), not a reserved word, and must start with a letter.' => 'Admin path must be a single lowercase slug (letters, digits, hyphens), not a reserved word, and must start with a letter.',

    // ---- Revision Actions ----
    'Published' => 'Published',
    'Unpublished' => 'Unpublished',
    'Draft updated' => 'Draft updated',
    'Restored' => 'Restored',
    'Imported' => 'Imported',
    'Unknown' => 'Unknown',
    'Untitled' => 'Untitled',

    // ---- Additional System ----
    'All caches cleared.' => 'All caches cleared.',
    'Cleared all caches (optimize:clear)' => 'Cleared all caches (optimize:clear)',
    'Database fresh + seed (non-production)' => 'Database fresh + seed (non-production)',
    'Database reset and seeded.' => 'Database reset and seeded.',
    'Fresh + seed failed: ' => 'Fresh + seed failed: ',
    'This operation is not allowed in production.' => 'This operation is not allowed in production.',
    'Embed Form' => 'Embed Form',
    'Preview' => 'Preview',
];
