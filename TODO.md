
## Third-Party Login
1. e.g. WeChat, Douyin, Taobao, etc.
2. Phone number login
3. Real-name verification
4. QR code login

## Payment Integration
1. Alipay integration
2. Douyin Pay integration

## Issue Feedback
1. Add an issue-reporting API and UI for users on each project

## Notification Improvements
1. Show a red badge with a count on items that have pending tasks

=============================================

## 待新增功能
1. 写一个程序，根据网址 能抓取网站的favicon，这个功能可以用到Directory项目里，其每个网址都能获取其favcion 显示在相应的地方

=============================================

## 安全设置TODO

1. **SSO / OIDC**：Strapi/Directus 都把 SSO 作为企业版卖点，自托管版可后续接入（如 Laravel Socialite + OIDC）。
2. **依赖与密钥管理**：CI 加 `composer audit` / `npm audit`；数据库里第三方存储密钥（OSS/COS/S3 secret）确认是配置项而非入库敏感明文，必要时加密字段。
3. **管理员登录告警**：后台异地 / 新设备登录邮件通知。
4. **内容操作二次确认**：高危操作（删除项目、批量删用户、改权限）要求密码确认或输入确认。

=============================================

## 待测试
1. 邮箱激活、邮箱验证码功能


=============================================

## 项目语言有些混乱


## 后台接口怎么这样？
比如Article列表的的接口：admin-api/content/1/2，这结构1/2 看不懂什么意思？？？正常不应该是这样的吗admin/project/1/content/2？