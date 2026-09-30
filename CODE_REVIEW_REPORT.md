# Aine CMS 全量代码审查报告

> 审查对象：整个项目（Laravel 13 + Inertia/Vue 3 + Tailwind + Vite，Aine CMS / Headless 内容管理系统）
> 审查方式：按 7 个模块分片，逐文件、逐行人工通读；覆盖 `app/`、`routes/`、`config/`、`database/`、`resources/js/`、`resources/views/`、`installer/`、`lang/`、`tests/` 及根目录构建配置。
> 排除：`vendor/`、`node_modules/`、`public/build/`、`storage/framework/views/`、`resources/js/vendor/`、`public/js/tinymce/`。
> 审查日期：2026-09-19。本次为只读审查，未修改任何源码，未执行 git add/commit。

---

## 一、执行摘要

| 指标 | 数值 |
|---|---|
| 逐行审查文件数 | **424 个**（PHP 309 + JS/Vue 76 + Blade 39） |
| 🔴 严重问题 | **21** |
| 🟡 警告 | **68** |
| 🔵 建议 | **71** |
| 合计 | **160** |
| PHP 语法批量检查 | ✅ 0 错误 |
| 自动化测试 | ✅ **321 通过 / 1037 断言 / 0 失败**（11.96s） |
| 路由加载 | ✅ 251 条路由正常注册 |
| 迁移状态 | ✅ 25 个迁移全部已执行 |

**总体评价**：项目工程底子扎实——统一的 Policy 鉴权、AuditLogger、PublicCache 失效、UploadGuard 上传防护、TwoFactor、API 限流、HtmlSanitizer 均已落地，321 个测试全绿说明主链路是通的。**最需要优先处理的不是功能崩溃，而是一类系统性的"按 ID 取记录却漏做项目归属校验"的 IDOR 越权写入**（后台登出被中间件挡死、表单/Webhook/可重复字段 meta 三处跨项目篡改），以及**存储型 XSS 面**（前后台多处 `v-html` 直注富文本未消毒）。其余多为健壮性、N+1、配置一致性与测试质量问题。

### 优先修复 Top 10
1. 🔴 **后台管理员无法登出**——logout 路由被放进 `guest` 中间件组，已登录用户直接被弹回首页，到不了 `destroy()`。
2. 🔴 **跨项目 IDOR（3 处）**——FormController::save/delete、ProjectsController 的 webhook 系列、ContentMutationService 可重复字段 meta，均缺 `project_id` 归属校验。
3. 🔴 **SMTP 密码 / 云存储密钥经 API 明文下发**——`Setting` 模型 `mail_password` 在 `$fillable` 且无 `$hidden`、未加密。
4. 🔴 **存储型 XSS（前后台多处 v-html）**——ContentTable.vue、SsmlEditor.vue 直注富文本，前台无 DOMPurify。
5. 🔴 **webhook 三列用 VARCHAR(255) 存 JSON array**——数据稍多即被 MySQL 静默截断。
6. 🔴 **Webhook::collections() 关联 select 丢了主键 id**——关联模型无法 hydrate。
7. 🔴 **后台导出按钮 404**——ContentTable.vue 导出 URL 硬编码 `/admin-api` 前缀，与 axios baseURL 约定不一致。
8. 🔴 **表格渲染崩溃**——模板里裸调 `JSON.parse(field.options)`，options 已是对象时抛错整表崩。
9. 🔴 **Docker 生产部署起不来**——compose healthcheck 引用未定义变量，且 `.env.example` 的 `REDIS_CLIENT=predis` 未装包。
10. 🟡 **安装器 Classic 模式把用户原始 textarea 直接写 .env**，安装完成前任何人可注入任意环境变量。

---

## 二、联调验证结果（本报告作者实跑）

| 验证项 | 命令 / 方式 | 结果 |
|---|---|---|
| PHP 语法 | `find app routes config database installer tests -name "*.php" -exec php -l {} \;` | ✅ **0 错误** |
| 测试套件 | `php artisan test` | ✅ **321 passed / 1037 assertions / 0 failed**，11.96s |
| 路由列表 | `php artisan route:list` | ✅ 251 条路由全部注册，无 Controller 缺失（子代理另核对 204 个路由→方法引用，无失配） |
| 配置缓存 | `php artisan config:clear` | ✅ 无报错 |
| 迁移状态 | `php artisan migrate:status` | ✅ 25/25 全部 Ran |
| 语言 JSON | `python3 -m json.tool lang/{en,zh_CN}.json` | ✅ 均合法 |
| 框架版本 | `php artisan --version` | Laravel **13.32.0**，PHP **8.5.9**（本机） |

**环境注意**：本机 PHP 为 8.5.9，而 `Dockerfile` 锁定 `php:8.3-fpm-alpine`、`composer.json` platform 锁定 `php:8.3.0`。两者存在运行时差异，见严重 #20。
**第三方警告**：`php artisan` 每次都抛 `Qiniu\Config::__construct(): Implicitly marking parameter $z as nullable is deprecated`——来自 `vendor/qiniu/php-sdk`，非本项目代码，建议升级该 SDK 或屏蔽弃用提示。

### 对子代理结论的两处独立复核（避免误报）
- ❌→✅ **"composer.json 要求 Laravel ^13.0 会安装失败"——经核实为误报**：本机 `php artisan --version` 实际运行 **Laravel Framework 13.32.0**，Laravel 13 已发布，`^13.0` 约束正常。该条不计入问题。
- ✅ **`Pdo\Mysql` 类**：本机 PHP 8.5.9 上 `class_exists('Pdo\Mysql')` 为 `true`、常量存在，应用能跑；但该类是较新 PHP 才有的，Docker 目标 8.3 未验证，保留为部署风险（见严重 #20）。

---

## 三、🔴 严重问题（21 条）

### 后端鉴权 / 越权

#### [严重] routes/admin.php:248-251 —— 后台管理员无法登出
- **问题**：`POST /admin-api/logout`（`AdminLoginController@destroy`）被写在 `Route::middleware('guest')` 组内。请求先过 `RedirectIfAuthenticated`：已登录管理员发起登出时 `Auth::check()` 为真，直接 `redirect('/')`，请求根本到不了 `destroy()`。结果后台"退出登录"按钮把管理员弹回前台首页，永远登不掉。
- **建议**：把 logout 移出 `guest` 组，仅挂 `auth`（与 `routes/auth.php` 的 logout 写法一致）；或在 `guest` 中间件里对 logout 路由放行。

#### [严重] app/Http/Controllers/Frontend/FormController.php:90,108 —— 表单跨项目 IDOR
- **问题**：`save()`/`delete()` 只对 `$project_id` 做 `manageContent` 授权，随后 `Form::findOrFail($form_id)` **未校验 `$form->project_id === $project->id`**。项目 A 的编辑者把 URL 换成项目 B 的 form_id，即可改写/删除 B 的表单。
- **建议**：`Form::where('project_id', $project->id)->...->findOrFail($form_id)`（`store()` 已正确带 project_id，可对照）。

#### [严重] app/Http/Controllers/Admin/ProjectsController.php:806,847,870,889 —— Webhook 跨项目 IDOR
- **问题**：`updateWebhook`/`deleteWebhook`/`webhookLogs`/`deleteWebhookLogs` 用 `Webhook::findOrFail($id)`，只对 `$project_id` 授权，未校验 `$webhook->project_id === $project->id`。可改/删任意项目 webhook、读/清任意投递日志。（对照 `updateToken`/`deleteToken` 已按 tokenable 限定，无此问题。）
- **建议**：`Webhook::where('project_id', $project->id)->findOrFail($id)`。

#### [严重] app/Services/Content/ContentMutationService.php:173-181 —— 可重复字段 meta 水平越权写入
- **问题**：repeatable 字段更新分支定位旧 meta 行只 `where('id', ...)->where('field_name', ...)`，**缺 `where('content_id', $content->id)`**。编辑者传一个同 collection 下他人内容的 ContentMeta 主键，即可静默覆盖那条 meta 的 value（IDOR）。
- **建议**：追加 `->where('content_id', $content->id)`（最好再加 `->where('project_id', ...)`）。

### 后端安全 / 数据泄露

#### [严重] app/Models/Setting.php:34 —— SMTP 密码与云存储密钥明文下发
- **问题**：`mail_password` 在 `$fillable` 且模型无 `$hidden`；`media_storage_config`（text）存 OSS/S3/七牛 AccessKey/Secret，同样未加密未隐藏。API 序列化时随设置返回前端。
- **建议**：敏感字段加入 `$hidden` 并用 `encrypted` cast；控制器只回传非敏感字段。

#### [严重] database/seeders/DatabaseSeeder.php:21-29 —— 管理员弱密码 + seeder 不幂等
- **问题**：默认管理员 `admin@admin.com` / 密码 `admin`；用 `User::create()`、`Role::create()` 而非 `firstOrCreate`，二次运行撞唯一索引直接报错（与 DemoProjectsSeeder 用 `firstOrCreate` 不一致）。
- **建议**：改 `firstOrCreate`；生产强制首次登录改密；DemoProjectsSeeder 的 webhook `secret='demo-secret-12345'` 标注仅本地用。

#### [严重] installer/src/Helpers/EnvironmentManager.php:90-101 —— Classic 模式裸写 .env
- **问题**：`saveFileClassic()` 把 textarea 原始内容直接 `file_put_contents` 进 `.env`，无 key 白名单、无校验。安装完成前（`canInstall` 只查 installed 文件）任何能访问该页的人可写入 `APP_DEBUG=true`、恶意 `DB_*` 等任意环境变量。
- **建议**：限制可编辑 key 白名单；写入后校验关键安全项；生产可禁用 classic 模式。

### 后端数据 / 模型一致性

#### [严重] app/Models/ProjectUser.php:16 —— Pivot 自增主键误配
- **问题**：`ProjectUser extends Pivot` 却设 `$incrementing = true`，但 `project_user` 表是复合主键、无 `id` 列。`updateOrCreate()`/`$pivot->save()` 后 `$model->id` 为 null，后续操作异常。
- **建议**：删除该行（Pivot 基类默认 `$incrementing=false`），或迁移里加 `$table->id()`。

#### [严重] database/migrations/2026_01_01_000014_create_webhooks_table.php:24-26 —— JSON 列用 VARCHAR(255)
- **问题**：`collection_ids`/`events`/`sources` 定义为 `string()`，但模型 cast 为 `array`（json_encode 写入）。webhook 配置多 collection/event 时 JSON 串超 255 字符被 MySQL 静默截断 → 数据损坏。
- **建议**：三列改 `$table->json(...)`。

#### [严重] app/Models/Webhook.php:46 —— 关联 select 丢主键
- **问题**：`collections()` 用 `->select(['name'])`，没选 `collections.id`，Eloquent hydrate 关联模型缺主键，结果为空/不完整。
- **建议**：`->select(['collections.id', 'collections.name'])`。

### 部署 / 配置

#### [严重] docker-compose.production.yml:74 —— MySQL healthcheck 永远失败
- **问题**：healthcheck 引用未定义的 `${DB_ROOT_PASSWORD:-secret}`，实际 MySQL root 密码用 `${DB_PASSWORD:-secret}`。运维设了真实 `DB_PASSWORD`（≠secret）时，healthcheck 用错密码一直失败 → `depends_on: service_healthy` 永不满足 → 应用容器起不来。
- **建议**：healthcheck 与服务统一用 `${DB_PASSWORD:-secret}`；并把默认弱密码 `secret` 改为强制 `${DB_PASSWORD:?...}`。

#### [严重] .env.example:62 + composer.json —— Redis 客户端未安装
- **问题**：`.env.example` 设 `REDIS_CLIENT=predis`，但 `composer.json` 未 require `predis/predis`（本机 `vendor/predis` 不存在）。当前项目实际用 `file`/`database` 驱动未触发；一旦按示例切 Redis 即连接失败。
- **建议**：`.env.example` 改 `REDIS_CLIENT=phpredis`，或 composer 加 `"predis/predis": "^2.0"`。

#### [严重] config/database.php:4,63 —— `Pdo\Mysql` 类与部署 PHP 版本不一致
- **问题**：`use Pdo\Mysql as PdoMysql;` 并引用 `PdoMysql::ATTR_SSL_CA`。本机 PHP 8.5.9 上类存在、应用正常；但 Dockerfile 锁 `php:8.3-fpm-alpine`、composer platform 锁 `php:8.3.0`，该命名空间 PDO 类在 8.3 未必存在，目标环境启动即 fatal。
- **建议**：做版本兼容兜底——`defined('Pdo\\Mysql::class') ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA`。

### 前端

#### [严重] resources/js/admin/views/Project.Content/sections/ContentTable.vue:596 —— 后台富文本存储型 XSS
- **问题**：`<div v-html="textRecord">` 直注 richtext 字段，无 DOMPurify 消毒。富文本里的 `<script>`/`onerror` 会在管理员视图执行。
- **建议**：渲染前 `DOMPurify.sanitize()`，或服务端入库即白名单过滤。

#### [严重] resources/js/admin/views/Project.Content/sections/ContentTable.vue:691,726,733 —— 模板裸调 JSON.parse 整表崩
- **问题**：模板里 `JSON.parse(field.options)`，而 `options` 在另一条链路已被转成对象；对象再 `JSON.parse` 抛 `Unexpected token o`，且无 try/catch → 表格渲染崩溃。
- **建议**：computed/方法里安全解析：`typeof x==='string' ? JSON.parse(x) : x`。

#### [严重] resources/js/admin/views/Project.Content/sections/ContentTable.vue:1075 —— 导出 URL 前缀错误导致 404
- **问题**：导出拼 `appUrl + apiBase + "/content/export/..."`，`apiBase=/admin-api`，但全站其它 axios 走 baseURL `/admin`，导出请求落到 `/admin-api/...` → 404。
- **建议**：统一走 axios 实例 baseURL（`axios.get(url, {responseType:'blob'})`）。

#### [严重] resources/js/admin/views/Project.Collection/CollectionList.vue:749-766, 1265 —— 重复键死代码 + 删除后跳转错参
- **问题**：`new_field.options` 对象字面量重复定义两次（后者覆盖前者，第一段是死代码）；`clearData()` 重置结构与初始 data 不一致。删除 collection 后跳 `{name:'projects', params:{id: project.id}}`，但 `projects` 路由无参、参数名也不对。
- **建议**：删重复 `options`；统一重置结构；跳转改 `{name:'projects'}`。

#### [严重] resources/js/frontend/views/SearchPage.vue:116-121 —— 搜索结果回退路由错误
- **问题**：directory 缺分类时拼单段 `/directory/<slug>`（实际详情路由是两段 `/directory/:category/:listing`）→ 404；note 缺分类时拼 `/note/<slug>` 错落到 note 单页详情，内容类型错配。
- **建议**：缺分类时落回列表页/隐藏该项，不拼错误详情路由。

#### [严重] resources/js/frontend/views/cms/ArticleDetail.vue:98,118 与 directory/ListingDetail.vue:185 —— name 为 null 时整页崩
- **问题**：`comment.name.charAt(0).toUpperCase()` 等对 name 直接 `charAt`；匿名/游客 name 为 null 时 `TypeError` 崩评论区。
- **建议**：`(name || '').charAt(0).toUpperCase()`，或抽 `initial()` 工具函数。

### 国际化

#### [严重] lang/en.json —— 英文语言文件为空对象
- **问题**：`lang/en.json` 是 `{}`（0 key），而 `zh_CN.json` 有 76 key。当前靠"缺 key 回退显示 key 本身"让英文界面正常；一旦改了某条英文源串，zh_CN.json 还挂在旧 key 上，中英文静默漂移无报错。
- **建议**：把 en.json 补成 identity 映射作英文权威清单，或加 CI 脚本校验 zh_CN 每个 key 都能在源码 `__()`/`$t()` 找到出处。

---

## 四、🟡 警告（68 条，按主题归并）

### A. 后端健壮性 / 业务逻辑（Shard1+Shard3）
1. **Admin/ContentController.php:837-916 importContent**：批量导入直接写 `published_at`，绕过字段校验与审核工作流开关（开启 workflow 的项目也能靠导入直接发布）。→ 导入复用 store() 的校验/工作流闸门，或统一草稿落库。
2. **Admin/ContentController.php:218-237 index orderBy**：`orderBy($request->get('orderBy'))` 无列名白名单，非法列名直接 500。→ 建白名单，非法值回退默认列。
3. **多处上传文件名**（MediaLibraryController.php:248/302、API/MediaController、FormController::upload）：落盘直接用 `$file->getClientOriginalName()`，未 `basename()` 净化，存在 `../` 路径面（Flysystem 默认兜底但代码自身不防御）。
4. **API 错误响应格式不统一**（ValidateProjectAccess.php:54、API/ContentController 多处 404、CommentsController）：有的走 `{success,code,message,data}`，有的裸 `{error,message}`，前端按统一结构解析会拿到 undefined。
5. **Middleware/DynamicCors.php:44-54**：缓存未命中时 `Project::all()->contains()` 把全表拉进内存。→ 改 `whereIn/uuid` 索引查询或 `exists()`。
6. **Frontend/FormController.php:256,409 submit**：`json_decode($form->fields)` 后直接 `foreach`，非法 JSON 时为 null，公开匿名提交路径可触发 500。→ 加 `is_array()` 守卫。
7. **ContentMutationService.php:80-96**：`update()` 在 `published=false` 时无条件清 `published_at/published_by`，正确性依赖调用方传入草稿分支模型，契约脆弱。
8. **Jobs/PublishScheduledContent.php:53-57**：Job 早于到点被取出时直接 return，不重排队，该条定时发布永久丢失。→ `$this->release(秒数)` 重新延迟。
9. **WebhookHelper.php:34-65**：`$wh->payload` 为假时发**空请求体** webhook，接收方拿不到上下文。
10. **Webhook 监听器未判空**（WebhookCallSucceededListener:28、FinalWebhookCallFailedListener:28）：直接取 `$event->payload['project_id']`、`$event->response->getBody()` 无 isset/null 判断，且无 try/catch。
11. **EventServiceProvider.php:65-68**：`FormSubmitted` 误绑 `BumpPublicCache`（表单提交不带内容结构，bump 语义错误）。
12. **ContentQueryService.php:121-126 全文搜索**：`LIKE '%q%'` 未转义 `%`/`_` 通配符（搜 `%` 即匹配全部），且前后通配不走索引，表大时全表扫描。
13. **ContentQueryService.php:35,118**：常量 `MAX_PAGE_LIMIT=100` 定义了却在 search() 里手写 `min($limit,100)`，死代码/不一致。
14. **PublicCache.php:50-55 bump()**：读-改-写自增，并发下丢计数，写后旧缓存可能仍命中。→ Redis 用原子 `Cache::increment()`。
15. **TwoFactor.php:111-127**：恢复码用 `===` 非常量时间比较，建议 `hash_equals`。
16. **UploadGuard.php:68-77**：内容特征只扫文件前 1MB，多面体文件可把特征藏在 1MB 后绕过。
17. **HtmlSanitizer.php:189-191**：所有 `data-*` 无差别放行，前端若拼进 JS 上下文是潜在 XSS 落点。
18. **ContentNotification.php:9**：`use Queueable` 但未 `implements ShouldQueue`，通知仍同步发送，trait 空转。
19. **AdminPath.php:53,60-83**：静态属性每请求缓存但 Octane 常驻 worker 下不 reset。→ terminating 里 `AdminPath::flush()`。

### B. 数据层（Shard2）
20. **CollectionField.php:14-18**：迁移里 `options`/`validations` 是 json 列，模型 `$casts` 却没声明为 array，访问得到原始 JSON 字符串。
21. **Project.php:19-36**：`locales` 是 string 逗号分隔、`domain_whitelist` 却 cast 为 array，同一模型两种序列化风格易误用。
22. **Project.php:7**：Model `use App\Http\Controllers\Admin\LocalizationController` 违反分层；第 8 行多余 `use ...User as AppUser`。
23. **Project.php:129 / Project.php:42 / Form.php:25**：模型用 `Str::uuid()->getHex()`（32 位无连字符），迁移列是 `$table->uuid()`（CHAR(36) 带连字符），格式不一致，`webhook_logs()` 按 uuid 关联可能匹配不上。
24. **Media.php:46**：`$appends=['full_url','full_url_thumb']`，accessor 里每个都查一次 project uuid，未 eager load 时 N+1（50 条媒体 = 100 次额外查询）。
25. **ContentRevision.php:65-90**：`is_current`/`change_summary` accessor 每次访问都查一次库，列表页 N+1。
26. **多张表缺外键约束**：`collections/collection_fields/content/media/content_meta/webhooks/webhook_logs/forms/audit_logs` 的 `project_id` 等仅普通 int，无 `constrained/cascadeOnDelete`，删项目/用户产生孤儿记录。
27. **User.php:67-69**：`sendPasswordResetNotification` 判断 `hasRole('admin')`，但 seeder 从没建 `admin` 角色（只有 super_admin/editor/user），该分支永远 false。
28. **Content.php:38**：`$hidden=['deleted_at']` 把软删除时间戳隐藏，前端无法判断回收站状态。
29. **Collection.php:33-35**：`meta()` hasMany(ContentMeta) 语义错位（meta 属于 content，不是 collection），易误导调用。

### C. 配置 / 安装器（Shard4）
30. **config/app.php:29**：注释说"读 config 不用 env 以扛 config:cache"，代码却用 `env('APP_VERSION')`，cache 后永远回退 `0.0.1`。
31. **config/app.php:62 CSP**：`script-src` 同时允许 `'unsafe-inline'`+`'unsafe-eval'`，XSS 防护形同虚设。
32. **.env.example:5**：`APP_URL=https://localhost` 本地应 http。
33. **installer/ConfirmController.php:20-37**：运行时代码直接 `env('APP_NAME')` 等，config:cache 后安装摘要页空白。
34. **docker-compose.example.yml:4**：Sail 用 PHP 8.2 runtime，与项目要求 8.3 不符。
35. **PermissionsChecker.php:51**：目录不存在时 `fileperms` 返回 false，`substr(sprintf('%o',false),-4)` 给出无意义权限值，可能"通过"一个不存在的目录。
36. **docker-compose.production.yml:35,67,70**：DB 密码默认 `"secret"`，未设环境变量时生产用弱密码且无警告。
37. **config/installer.php:186**：`'updaterEnabled'=>'true'` 用字符串而非布尔。
38. **EnvironmentController.php:146**：手动拼 redirect URL，反代/子目录部署可能错。
39. **phpunit.xml:31**：定义 `TELESCOPE_ENABLED=false` 但项目未装 telescope。
40. **installer/routes/web.php:6**：路由组仍传 `namespace` 参数，Laravel 11+ 已不再自动前缀。

### D. 前端后台（Shard5）
41. **ContentTable.vue:1380**：Options API 的 computed 里调 Composition API 的 `useAttrs()`，不会返回正确值。
42. **WebhookLogs.vue:80**：模板里 `JSON.parse(log.request).collection` 无 try/catch，坏 JSON 崩整行。
43. **API.vue:798 / FormsDetail.vue:547**：`document.querySelector('meta[name=APP_URL]').content` 无 null 判断，meta 缺失即 TypeError。
44. **Dashboard.vue:68,217**：`router-link to="/projects"` 硬编码路径而非命名路由。
45. **Settings.vue:273**：HTML 注释同一行重复两次。
46. **约 15 个后台文件用全局 `axios` 却未 `import axios`**（Dashboard/Topbar/Edit/ContentTable/Forms/WebhookLogs/Webhooks/API/AuditLogs/Locales/Preferences/ProjectTranslations/Settings/Users 等），仅 Comments.vue 正确 import，依赖 window 全局。
47. **ContentTable.vue:952**：`if (this.content.data == 0)` 数组与数字松散比较，恒不符合意图。
48. **Edit.vue:1228**：`isSavingEnable` 用 `diff(newData, newDataClone)`，但 clone 初始缺 `deleted/published/scheduled_at` 等动态字段，漏检未保存变更。
49. **CollectionList.vue:1119**：`collection.fields.map(...)` 返回值未用，应为 `forEach`。
50. **WebhookLogs.vue:227-235**：`cmOptions` CodeMirror 配置是死代码（页面用的是 textarea）。
51. **admin/routes.js:182-190**：大段注释掉的旧路由死代码。

### E. 前端前台（Shard6）
52. **SsmlEditor.vue:3**：文章/笔记正文 `v-html` 直注，前台无 DOMPurify，存储型 XSS 面（服务端若有历史脏数据即爆）。
53. **ListingDetail.vue:130**：商家 `website` 未做协议校验即 `target=_blank`，`rel=noopener` 挡不住 `javascript:` 伪协议；`tel:`/`mailto:` 同理未洗。
54. **http.js:159-179 + 各视图 catch**：拦截器"永不 reject、失败返回 null"，但各视图仍按传统写法 catch 里读 `error.response.data.message`——这些内联错误处理全是死代码，失败只走全局 toast。
55. **前台 CSRF 隐式依赖**（Layout.vue:408 / app.js:24 / app.blade.php）：前台 POST（logout、评论、改密）只靠 axios 自动带 XSRF cookie，blade 无 `<meta name="csrf-token">`、app.js 未显式设 header，与 form.js 不一致；cookie 过期/首屏异常时 419。
56. **Form.vue:1237**：`catch(error){ error.response.status==422 }`，断网时 `error.response` 为 undefined 再抛 TypeError 且不复位 processing，按钮永远转圈。
57. **store.js:76 + http.js:164**：`_suppressError:true` 只在 HTTP 错误分支生效，业务错误（200 但 success:false）仍弹 toast，"静默加载"并不静默。
58. **Profile.vue:394**：用裸 `window.axios.post` 绕过统一 http 实例，且未 import。

### F. 测试 / 语言（Shard7）
59. **AuditLogTest.php:132 与 :160**：两个 unpublish 审计用例几乎完全重复，复制粘贴死测试。
60. **SecurityHeadersTest.php:24-26**：setUp 向真实 `storage/installed` 写文件且不清理，污染运行环境、测试顺序耦合。
61. **Feature/ExampleTest.php:17**：`get('/')->assertStatus(200)` 依赖安装态且按文件名先于 SecurityHeadersTest 运行，未安装必红。
62. **AuthThrottleTest.php:21-56**：把 419/422 放进"限流前应通过"白名单，掩盖路由/中间件回归，断言偏弱。
63. **PaginationLimitTest.php:20-24**：反射直调私有 `validatePagination()`，白盒测试与真实 HTTP 链路脱钩。
64. **installer_messages.php 品牌名不一致**：en 叫 "Aine Installer"，zh_CN 叫 "Laravel安装程序"，同一产品两名。
65. **ContentSerializerTest.php:122-126**：catch 里 `fwrite(STDERR,...)` 调试残留。

---

## 五、🔵 建议（71 条，按主题归并）

### 模型 / 关联（Shard2）
1. CollectionField 缺 `HasFactory`；Favorite/Like/AuditLog/WebhookLog 有外键却没定义 `belongsTo(Project)` 关联；Content::meta 建议显式写外键名；关联写法混用 `'App\Models\X'` 与 `X::class`，统一 `::class`。
2. Media.php accessor 移除未用的 `$value` 参数；未知 disk 应记日志而非静默 null。
3. ContentRevision.php:138 `ucfirst` 对非拉丁字符不可靠，action 应写入即白名单。
4. DatabaseSeeder.php:31 与 RolePermissionSeeder 重复创建 `user` 角色，职责应收敛。
5. content 迁移手动定义 nullable timestamps，统一用 `$table->timestamps()`。
6. Setting `media_thumbnail_sizes` 列格式不明确。
7. **DemoProjectsSeeder 演示中文内容大量错别字**（"南作→协作""平翇舌→平翘舌""资子→荀子""劵学→劝学""锶而舍之→锲而舍之""条木不折→朽木不折""金石可镯→金石可镂""杜画→杜甫""谤语→谚语""精惫→精辟""嗇嘛→喇嘛""鳅目→泥鳅""嗇叭→喇叭"）。

### 服务 / 工具（Shard3）
8. AineHelpers.php:13 `post_max_size=0`（无限）被算成上传上限 0。
9. AuditLogger.php 应对 details 里 password/token/secret/recovery_code 自动脱敏。
10. ProjectTemplates 模板字段 `order` 全是 1，应 1,2,3 递增。
11. ContentSerializer 对非数字关系 id 静默丢弃，建议记 debug。
12. 三家云适配器 `readStream()` 先整对象读成字符串再写 tmpfile，大文件爆内存，应走 SDK 流式下载。
13. QiniuAdapter `deleteDirectory()` 翻页不带 marker，与 listContents 风格不一致。
14. 8 个 Content 事件类结构雷同，可抽抽象基类；docblock `@var array` 与实际可传 Content 模型不符。
15. WebhookHelper.php:57 确认 FormSubmitted 的 array content 不会误入 ContentResource 构造。
16. ContentValidationService.php:177 唯一字段判重未排除回收站内容。
17. RouteServiceProvider 未登录读接口按 NAT 出口 IP 限流可能误伤。

### 控制器 / 路由（Shard1）
18. **ApiProxyController.php 整文件死代码**：7 个路由文件均无引用，且内部转发 Authorization 头，将来误挂路由会成开放代理/SSRF。建议删除或补鉴权+host 白名单。
19. **API/ProfileController.php:158**：`method_exists($token,'expires_at')` 恒为 false（是属性不是方法），导致 token `expires_at` 永远 null。→ `$token->expires_at?->toIso8601String()`。
20. bootstrap/app.php:18 未安装时每请求正则改写 .env，逻辑依赖文件已有 APP_KEY 行。
21. Admin/SystemController.php:40 把 `config('app.debug')` 回传前端（仅超管可见，轻微信息泄露）。
22. ContentResource.php:64-88 collection/meta 为 null、json_decode 失败时未兜底即遍历/取 `->media->type`。
23. Admin/CommentsController.php:156 transition 未限定 collection_id=comments。
24. MediaLibraryController.php:122 upload 无 file 时无 else，控制器返回 null 报错。
25. RedirectIfAuthenticated.php:20 硬编码 `redirect('/')`，未复用后台落点。
26. SettingsController.php:45 缩进风格不一致。

### 配置 / 安装器（Shard4）
27. .env.example 缺 45 个 config 引用到的键（FILESYSTEM_DISK、SANCTUM_STATEFUL_DOMAINS、SESSION_SECURE_COOKIE 等），建议补注释版。
28. session.php:166 `secure` 无默认值，显式 `env(...,false)`。
29. EnvironmentManager::formatEnvValue 未处理 `${VAR}` 插值。
30. installer master.blade.php:37 用 `cdn.tailwindcss.com` 原型 CDN。
31. .env.example MAIL_FROM_ADDRESS 空，建议占位值。
32. scripts/extract-admin-strings.js:176 正则不匹配嵌套括号。
33. database.php:62 `array_filter` 把空字符串也滤掉，建议 `'strlen'` 回调。
34. Dockerfile 注意 .dockerignore 对 docker/ 目录的保留。

### 后台前端（Shard5）
35. SettingsIndex.vue 整组件是空占位符，确认是否可删。
36. Edit.vue:998 字符串拼接 `<img>` 进富文本，URL 未编码。
37. 约 6 个数据加载方法缺 `.catch()` 错误反馈。
38. ContentTable.vue:1015 localStorage key 含项目 id，项目删除后不清理。
39. ProjectTranslations.vue:195 重复造 `labelOf`，已有 `localeDisplayName()` 可复用。
40. admin.blade.php:15 注释里残留 `env("APP_NAME")`。
41. app-logo.blade.php:2 手动拼 URL 易双斜杠，用 `asset()`。
42. ContentTable.vue:417 `td` 上 `min-w-full` 不合理。
43. Edit.vue:855,865 forEach 内外变量同名 `element`。

### 前台前端（Shard6）
44. routes.js:87 每次导航都 await 两个已缓存 Promise，应缓存后放行。
45. api.js:787 `api.me()` 用裸 axios，应统一 http 实例。
46. checkrole.js:1 共享 utils 引入 admin store，前台 bundle 耦合 admin 代码。
47. Dropdown.vue:17 死内联 `display:none` 与 v-show 冗余。
48. Button.vue:37 运行时拼 Tailwind 类名 JIT 扫不到，应用静态映射或 safelist。
49. cms/directory/note 三套平行视图大量复制，建议收敛为一个带 project 配置的组件。
50. Layout.vue 命令式 DOM 测量做菜单折叠，建议 ResizeObserver。
51. api.js:642 GET 去重返回值两层 `.data` 取值，建议统一 unwrap。
52. locales.json 建议按 PROJECTS 返回的 locale 子集裁剪。

### 测试 / 语言（Shard7）
53. installer_messages key 拼写 `app_environment_label_developement`（应为 development）。
54. TimezoneFlowTest 中英混排注释，统一英文。
55. ContentImportExportTest/ContentSearchTest 行内注释 `//An` 缺空格。
56. HealthTest:16 只断言 200 未断言空响应体。
57. NotificationTest:182 只测节流生效未测节流解除（加时间旅行断言）。
58. MediaTransformTest 自述成功路径无测试，建议补 resize 成功用例。
59. DraftExposureTest:69 重复两次请求。
60. ApiRateLimitingTest:118 用对象伪造 user resolver，建议补 actingAs 真路由用例。
61. UploadGuardTest try/catch+assertTrue(true) 建议 `expectNotToPerformAssertions()`。
62. 两个 ExampleTest 是脚手架残留。
63. 建议加脚本 diff zh_CN.json key 与源码 `__()`/`$t()` 调用。

---

## 六、覆盖范围说明（确认无遗漏）

本次逐行通读 **424 个业务文件**，与项目实际盘点一致：

- **PHP 后端**：app/Http 全部（Controllers 48 + Middleware 17 + Requests/Resources 6）、Models 21、Aine 工具 9、Services/Content 3、Events 8、Listeners 6、Jobs 1、Notifications 1、Policies 1、Providers 5、Support 1、View/Components 3、Filesystem 4；routes 7；bootstrap 2。
- **配置/安装器**：config 19、installer 全部（Controllers 8 + Helpers 7 + Middleware 3 + Provider/Events/Routes）、安装器 Blade 13、根目录构建配置 15、scripts 1。
- **数据层**：migrations 25、seeders 4 + data 1、factories 1。
- **前端**：admin JS 45 + admin/auth/components Blade 22；frontend JS 39 + 共享组件 8 + utils 4 + 根入口 3 + frontend/layout Blade 4。
- **测试**：tests 42；**语言**：lang 12。

已按要求排除 `vendor/`、`node_modules/`、`public/build/`、`storage/framework/views/`、`resources/js/vendor/`、`public/js/tinymce/`。

---

*报告生成于 2026-09-19；所有行号基于审查时的工作区状态。建议按"严重 → 警告 → 建议"顺序修复，优先处理第一、二类越权与 XSS。*
