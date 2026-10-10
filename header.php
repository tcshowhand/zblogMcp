<?php
/**
 * zblogMcp 后台公共头部
 * @var object $zbp
 * @var string $current_file
 * @var string $title
 * @var string $bloghost
 * @var string $blogpath
 * @var $zblogmcp_config
 */
require '../../../zb_system/function/c_system_base.php';
require '../../../zb_system/function/c_system_admin.php';
$zbp->Load();
$action = 'root';
if (!$zbp->CheckRights($action)) {
    $zbp->ShowError(6);
    die();
}
$zblogmcp_plugin_info = include __DIR__ . '/config/plugin_info.php';
if (!$zbp->CheckPlugin($zblogmcp_plugin_info['id'])) {
    $zbp->ShowError(48);
    die();
}
require $blogpath . 'zb_system/admin/admin_header.php';
require $blogpath . 'zb_system/admin/admin_top.php';
?>
<!-- Vue 3 + Element Plus（本地静态文件） -->
<link rel="stylesheet" href="<?php echo $bloghost; ?>zb_users/plugin/zblogMcp/static/css/element-plus.css">
<link rel="stylesheet" href="<?php echo $bloghost; ?>zb_users/plugin/zblogMcp/static/fontawesome/css/all.min.css">
<script src="<?php echo $bloghost; ?>zb_users/plugin/zblogMcp/static/js/vue.global.prod.js"></script>
<script src="<?php echo $bloghost; ?>zb_users/plugin/zblogMcp/static/js/element-plus.js"></script>
<script src="<?php echo $bloghost; ?>zb_users/plugin/zblogMcp/static/js/icons-vue.js"></script>
<style>
/* 基础布局 */
.plugin-area .divHeader { font-size: 18px; font-weight: bold; }
#divMain2 { padding: 0 10px; }

/* 子菜单间距 */
.plugin-area .SubMenu { margin-bottom: 16px; }

/* 美化子菜单 Tab */
.plugin-area .SubMenu { display: flex; gap: 4px; }
.plugin-area .SubMenu a { text-decoration: none; }
.plugin-area .SubMenu .m-left,
.plugin-area .SubMenu .m-right {
    display: inline-block; padding: 5px 14px; font-size: 13px; color: #303133;
    background: transparent; cursor: pointer; transition: all .2s ease;
}
.plugin-area .SubMenu .m-left:hover,
.plugin-area .SubMenu .m-right:hover {
    color: #409eff; background: #ecf5ff;
}
.plugin-area .SubMenu .m-now {
    color: #fff; font-weight: 600; background: #409eff;
}

/* Vue 挂载点 */
.zblogmcp-app { margin-top: 0; }
.plugin-settings-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px; align-items: stretch;
}
.plugin-settings-grid .el-card { min-width: 0; height: 100%; }
@media (max-width: 700px) {
    .plugin-settings-grid { grid-template-columns: minmax(0, 1fr); }
}

/* 统计卡片 */
.stats-row { margin-bottom: 20px; }

/* ApiKey 脱敏 */
.key-text { font-family: monospace; font-size: 13px; }
.zblogmcp-action-buttons {
    display: flex; align-items: center; justify-content: center;
    gap: 6px; flex-wrap: nowrap;
}
.zblogmcp-action-buttons .el-button { margin-left: 0; }
.zblogmcp-app .el-button .fa-solid { font-size: 12px; margin-right: 4px; }

/* 日志详情 */
.log-detail-json {
    background: #f5f7fa; border-radius: 4px; padding: 12px;
    font-family: monospace; font-size: 12px; word-break: break-all;
    white-space: pre-wrap; max-height: 300px; overflow-y: auto;
}

/* MCP 信息卡片 */
.mcp-info-card { margin-bottom: 20px; }
.mcp-url-text {
    font-size: 13px; background: #f0f2f5; padding: 6px 12px;
    border-radius: 4px; font-family: monospace; display: inline-block;
}
</style>
<script>
$(function () {
    $('#nav_plugin').removeClass('on');
    $('#nav_zblogMcp').addClass('on');
});
</script>
