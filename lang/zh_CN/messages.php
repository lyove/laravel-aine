<?php

return [
    // ---- API Responses ----
    'Success' => '成功',
    'Error' => '错误',
    'Not found' => '未找到',
    'Unauthorized' => '未授权',
    'Forbidden' => '禁止访问',
    'Validation failed' => '验证失败',
    'Created' => '已创建',
    'Updated' => '已更新',
    'Deleted' => '已删除',
    'Unauthenticated' => '未认证',

    // ---- Projects ----
    'Project not found' => '项目未找到',
    'Project not found!' => '项目未找到！',
    'Project not resolved' => '项目未解析',
    'Project name is required' => '项目名称为必填项',
    'Project slug is required' => '项目别名为必填项',
    'Slug already exists' => '别名已存在',
    'Slug can only contain lowercase letters, numbers, and hyphens' => '别名只能包含小写字母、数字和连字符',
    'Domain not in whitelist' => '域名不在白名单中',
    'Domain whitelist updated successfully' => '域名白名单已更新成功',
    'Please reactivate the project before deleting.' => '请在删除前重新激活项目。',
    'The project owner cannot be removed.' => '项目所有者无法被移除。',
    'The default language cannot be removed.' => '默认语言无法被移除。',

    // ---- Collections ----
    'Collection not found' => '集合未找到',
    'Collection not found!' => '集合未找到！',
    'Schema imported.' => 'Schema 已导入。',
    'Schema must include a collection name and slug.' => 'Schema 必须包含集合名称和别名。',
    'Invalid schema file. Expected { "collection": {...}, "fields": [...] }.' => '无效的 Schema 文件。预期格式：{ "collection": {...}, "fields": [...] }。',

    // ---- Content ----
    'Content not found' => '内容未找到',
    'Record not found' => '记录未找到',
    'Content is already published or already under review.' => '内容已发布或已在审核中。',
    'Only content currently under review can be approved.' => '只有当前在审核中的内容可以被批准。',
    'Only content currently under review can be rejected.' => '只有当前在审核中的内容可以被拒绝。',
    'This content is not a draft branch.' => '此内容不是草稿分支。',
    'This project has the editorial workflow enabled — submit the content for review and approve it via the workflow endpoints instead of publishing directly.' => '此项目启用了编辑工作流 — 请提交内容审核并通过工作流端点批准，而不是直接发布。',
    'Draft discarded.' => '草稿已丢弃。',
    'Submitted for review.' => '已提交审核。',
    'Approved and published.' => '已批准并发布。',
    'Rejected.' => '已拒绝。',
    'Restoring this revision will permanently delete data from fields that do not exist in that snapshot.' => '恢复此版本将永久删除该快照中不存在的字段数据。',
    'Revision data is corrupted.' => '版本数据已损坏。',
    'Revision restored successfully.' => '版本恢复成功。',
    'Unsupported export format.' => '不支持的导出格式。',
    'Only .json and .csv files are supported.' => '仅支持 .json 和 .csv 文件。',
    'Invalid JSON file.' => '无效的 JSON 文件。',
    'Import failed: ' => '导入失败：',
    'No file uploaded.' => '未上传文件。',
    'Search query must be at least 2 characters.' => '搜索查询至少需要 2 个字符。',
    'Search query cannot exceed 100 characters.' => '搜索查询不能超过 100 个字符。',
    'Invalid limit parameter.' => '无效的 limit 参数。',
    'Invalid offset parameter.' => '无效的 offset 参数。',
    'Incorrect limit statement.' => '错误的 limit 语句。',
    'Incorrect offset statement.' => '错误的 offset 语句。',
    'Incorrect sort statement' => '错误的排序语句',

    // ---- Comments ----
    'Comments are not enabled for this project' => '此项目未启用评论',
    'Comments are disabled for this article' => '此文章已禁用评论',
    'Article not found' => '文章未找到',
    'Comment submitted and awaiting moderation' => '评论已提交，等待审核',
    'Comment approved' => '评论已批准',
    'Comment marked as spam' => '评论已标记为垃圾',
    'Comment moved to trash' => '评论已移至回收站',
    'Comment restored' => '评论已恢复',
    'Comments deleted' => '评论已删除',
    'Comments updated' => '评论已更新',
    'Invalid action' => '无效操作',
    'No comments selected' => '未选择评论',
    'Deleted article' => '已删除的文章',

    // ---- Media ----
    'Media not found' => '媒体未找到',
    'Failed to delete media' => '删除媒体失败',
    'File not found! Attach a file to your request.' => '文件未找到！请在请求中附加文件。',
    'Chunk upload is disabled.' => '分块上传已禁用。',

    // ---- API Tokens ----
    'API token is not valid for this project' => 'API 令牌对此项目无效',
    'API token does not have the required permissions' => 'API 令牌没有所需权限',

    // ---- Users & Auth ----
    'You cannot delete your own account.' => '您无法删除自己的账户。',
    'Bulk actions cannot include your own account.' => '批量操作不能包含您自己的账户。',
    'Cannot delete the last super admin.' => '无法删除最后一个超级管理员。',
    'Cannot remove the last super admin.' => '无法移除最后一个超级管理员。',
    'No matching users found.' => '未找到匹配的用户。',
    'Please log in through the admin area.' => '请通过管理后台登录。',
    'These credentials do not grant access to the admin area.' => '这些凭据无法访问管理后台。',
    'Password updated.' => '密码已更新。',
    'The provided password is incorrect.' => '提供的密码不正确。',
    'The provided code was invalid.' => '提供的验证码无效。',
    'The provided two factor authentication code was invalid.' => '提供的双因素认证码无效。',
    'Two factor authentication is already enabled.' => '双因素认证已启用。',
    'Two factor authentication is not enabled.' => '双因素认证未启用。',
    'Two factor authentication has been disabled.' => '双因素认证已禁用。',
    'Enable two factor authentication first.' => '请先启用双因素认证。',

    // ---- Notifications ----
    'Notifications marked as read.' => '通知已标记为已读。',

    // ---- Preview ----
    'Preview not found or the link has been revoked.' => '预览未找到或链接已被撤销。',
    'Preview link has expired.' => '预览链接已过期。',

    // ---- Translations ----
    'Placeholder mismatch' => '占位符不匹配',
    'Source string is empty' => '源字符串为空',

    // ---- Collection Fields ----
    'Add at least one option to enumeration list' => '请至少添加一个枚举选项',
    'Select a collection' => '请选择一个集合',
    'This field is required' => '此字段为必填项',
    'Must be numeric' => '必须为数字',
    'Enter a value' => '请输入值',
    'Must be less than max' => '必须小于最大值',

    // ---- System ----
    'Cleared route cache' => '已清除路由缓存',
    'Route cache cleared.' => '路由缓存已清除。',
    'Cleared config cache' => '已清除配置缓存',
    'Config cache cleared.' => '配置缓存已清除。',
    'Cleared view cache' => '已清除视图缓存',
    'View cache cleared.' => '视图缓存已清除。',
    'Cleared application cache' => '已清除应用缓存',
    'Application cache cleared.' => '应用缓存已清除。',
    'Rebuilt config & route cache' => '已重建配置和路由缓存',
    'Config and route cache rebuilt.' => '配置和路由缓存已重建。',
    'Storage link already exists' => '存储链接已存在',
    'Storage link already exists.' => '存储链接已存在。',
    'Storage link created.' => '存储链接已创建。',
    'Recreated storage link' => '已重新创建存储链接',
    'Failed to create storage link: ' => '创建存储链接失败：',
    'public/storage already exists as a file or folder. Move it away first, then try again.' => 'public/storage 已作为文件或文件夹存在。请先将其移开，然后重试。',
    'PHP symlink() is disabled on this server (disable_functions). Enable it in php.ini, or create the link manually from the terminal: ln -s ' => '此服务器已禁用 PHP symlink()（disable_functions）。请在 php.ini 中启用它，或从终端手动创建链接：ln -s ',
    'symlink() failed. Check directory permissions.' => 'symlink() 失败。请检查目录权限。',
    'Cleared :count old log file(s)' => '已清除 :count 个旧日志文件',
    ':count old log file(s) cleared.' => '已清除 :count 个旧日志文件。',
    'Enabled maintenance mode' => '已启用维护模式',
    'Maintenance mode enabled.' => '维护模式已启用。',
    'Disabled maintenance mode' => '已禁用维护模式',
    'Maintenance mode disabled.' => '维护模式已禁用。',
    'Ran pending migrations' => '已执行待处理的迁移',
    'Migrations executed.' => '迁移已执行。',
    'Migration failed: ' => '迁移失败：',
    ':ran ran, :pending pending' => ':ran 已执行，:pending 待执行',

    // ---- Settings ----
    'My Website' => '我的网站',
    'Aine is a content management system built with Laravel and Vue.js' => 'Aine 是一个基于 Laravel 和 Vue.js 构建的内容管理系统',
    'Admin path must be a single lowercase slug (letters, digits, hyphens), not a reserved word, and must start with a letter.' => '管理路径必须是一个小写别名（字母、数字、连字符），不能是保留字，且必须以字母开头。',

    // ---- Revision Actions ----
    'Published' => '已发布',
    'Unpublished' => '已取消发布',
    'Draft updated' => '草稿已更新',
    'Restored' => '已恢复',
    'Imported' => '已导入',
    'Unknown' => '未知',
    'Untitled' => '无标题',

    // ---- Additional System ----
    'All caches cleared.' => '所有缓存已清除。',
    'Cleared all caches (optimize:clear)' => '已清除所有缓存（optimize:clear）',
    'Database fresh + seed (non-production)' => '数据库重置并填充（非生产环境）',
    'Database reset and seeded.' => '数据库已重置并填充。',
    'Fresh + seed failed: ' => '重置并填充失败：',
    'This operation is not allowed in production.' => '此操作在生产环境中不允许。',
    'Embed Form' => '嵌入表单',
    'Preview' => '预览',
];
